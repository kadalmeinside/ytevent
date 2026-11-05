<?php

namespace App\Services;

use App\Models\EmailNotificationLog;
use App\Models\Event;
use App\Models\Payment;
use App\Models\Team;
use App\Models\TeamOfficial;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TeamRegistrationService
{
    public function __construct(
        protected QuotaExpirationService $quotaExpiration
    ) {}

    public function registerNewTeam(
        Event $event,
        array $validated,
        UploadedFile $logoFile,
        ?User $authenticatedUser = null
    ): Team {
        $logoPath = $logoFile->store('teams/logos', 'public');

        try {
            $result = DB::transaction(function () use ($event, $validated, $logoPath, $authenticatedUser) {
                $lockedEvent = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();

                // 1. Lazy Expiration Sweep agar slot yang baru saja lewat expires_at langsung tersedia
                $this->quotaExpiration->sweepExpiredForEvent($lockedEvent);
                $lockedEvent->refresh();

                // 2. Periksa jendela pendaftaran dan status event
                if (
                    ! in_array($lockedEvent->status, ['open', 'quota_full'], true) ||
                    ! $lockedEvent->isWithinRegistrationWindow()
                ) {
                    return [
                        'ok' => false,
                        'field' => 'event_quota',
                        'message' => 'Pendaftaran untuk kompetisi ini belum dibuka atau telah ditutup oleh panitia.',
                    ];
                }

                // 3. Periksa sisa slot kuota di bawah kunci eksklusif (SELECT ... FOR UPDATE)
                $occupied = $lockedEvent->teams()->occupyingQuotaAt(now())->count();

                if ($occupied >= $lockedEvent->max_teams) {
                    $lockedEvent->syncQuotaStatus($occupied);

                    return [
                        'ok' => false,
                        'field' => 'event_quota',
                        'message' => 'Maaf, kuota pendaftaran untuk kompetisi ini baru saja terpenuhi oleh tim lain.',
                    ];
                }

                // 4. Pastikan keunikan nama tim & email di dalam transaksi terkunci
                $nameExists = $lockedEvent->teams()
                    ->where('name', $validated['team_name'])
                    ->exists();

                if ($nameExists) {
                    return [
                        'ok' => false,
                        'field' => 'team_name',
                        'message' => 'Nama tim ini sudah terdaftar pada kompetisi yang dipilih.',
                    ];
                }

                if ($authenticatedUser) {
                    $alreadyInSameEvent = $lockedEvent->teams()
                        ->where('user_id', $authenticatedUser->id)
                        ->whereIn('status', ['pending_payment', 'active', 'locked_roster'])
                        ->exists();

                    if ($alreadyInSameEvent) {
                        return [
                            'ok' => false,
                            'field' => 'event_quota',
                            'message' => 'Akun Anda sudah memiliki tim aktif pada kompetisi ini.',
                        ];
                    }

                    $managerUser = $authenticatedUser;
                    if (! $managerUser->hasRole('team_manager')) {
                        $managerUser->assignRole('team_manager');
                    }
                } else {
                    if (User::withTrashed()->where('email', $validated['email'])->exists()) {
                        return [
                            'ok' => false,
                            'field' => 'email',
                            'message' => 'Email ini sudah terdaftar. Silakan login atau gunakan email lain.',
                        ];
                    }

                    $managerUser = User::create([
                        'name' => $validated['manager_name'],
                        'email' => strtolower(trim($validated['email'])),
                        'phone' => $validated['manager_whatsapp'],
                        'password' => Hash::make($validated['password']),
                        'is_active' => true,
                    ]);
                    $managerUser->assignRole('team_manager');
                }

                // 5. Reservasi Slot & Pembuatan Tim + Invoice
                $now = now();
                $deadlineHours = max(1, (int) ($lockedEvent->payment_deadline_hours ?: 24));
                $expiresAt = $now->copy()->addHours($deadlineHours);
                $isFree = ((float) $lockedEvent->registration_fee) <= 0;

                $baseSlug = Str::slug($validated['team_name']);
                $slug = $baseSlug . '-' . Str::lower(Str::random(5));

                $team = Team::create([
                    'event_id' => $lockedEvent->id,
                    'user_id' => $managerUser->id,
                    'name' => trim($validated['team_name']),
                    'slug' => $slug,
                    'logo_path' => $logoPath,
                    'origin_association' => trim($validated['origin_association']),
                    'secretariat_address' => $validated['secretariat_address'] ?? null,
                    'manager_name' => trim($validated['manager_name']),
                    'manager_whatsapp' => trim($validated['manager_whatsapp']),
                    'manager_email' => $authenticatedUser ? $managerUser->email : strtolower(trim($validated['email'])),
                    'head_coach_name' => trim($validated['head_coach_name']),
                    'head_coach_license' => $validated['head_coach_license'] ?? null,
                    'terms_accepted_at' => $now,
                    'status' => $isFree ? 'active' : 'pending_payment',
                    'slot_reserved_at' => $now,
                    'slot_expires_at' => $isFree ? null : $expiresAt,
                    'screening_status' => 'not_started',
                ]);

                TeamOfficial::create([
                    'team_id' => $team->id,
                    'event_id' => $lockedEvent->id,
                    'full_name' => trim($validated['head_coach_name']),
                    'role' => 'Head Coach',
                    'license_number' => $validated['head_coach_license'] ?? null,
                    'screening_status' => 'draft',
                ]);

                $invoiceNumber = $this->generateInvoiceNumber($lockedEvent);

                Payment::create([
                    'invoice_number' => $invoiceNumber,
                    'team_id' => $team->id,
                    'event_id' => $lockedEvent->id,
                    'amount' => $lockedEvent->registration_fee,
                    'gateway_fee' => 0,
                    'total_amount' => $lockedEvent->registration_fee,
                    'method' => $isFree ? 'manual' : null,
                    'payment_channel' => $isFree ? 'Pendaftaran Gratis' : null,
                    'expires_at' => $isFree ? null : $expiresAt,
                    'status' => $isFree ? 'paid' : 'unpaid',
                    'paid_at' => $isFree ? $now : null,
                ]);

                // 6. Sinkronisasi status open -> quota_full bila slot terakhir terambil
                $lockedEvent->syncQuotaStatus($occupied + 1);

                EmailNotificationLog::create([
                    'event_id' => $lockedEvent->id,
                    'team_id' => $team->id,
                    'mailable_type' => 'TeamRegisteredMail',
                    'recipient_email' => $team->manager_email,
                    'status' => 'queued',
                ]);

                return [
                    'ok' => true,
                    'team' => $team,
                ];
            });
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($logoPath);
            throw $e;
        }

        if (! $result['ok']) {
            Storage::disk('public')->delete($logoPath);
            throw ValidationException::withMessages([
                $result['field'] => $result['message'],
            ]);
        }

        return $result['team'];
    }

    /**
     * Klaim ulang slot pendaftaran untuk tim yang berstatus expired menggunakan lockForUpdate yang sama.
     */
    public function reclaimExpiredSlot(Team $team): Payment
    {
        $result = DB::transaction(function () use ($team) {
            $lockedEvent = Event::query()->whereKey($team->event_id)->lockForUpdate()->firstOrFail();
            $lockedTeam = Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();

            $this->quotaExpiration->sweepExpiredForEvent($lockedEvent);
            $lockedEvent->refresh();

            if ($lockedTeam->status !== 'expired') {
                return [
                    'ok' => false,
                    'field' => 'event_quota',
                    'message' => 'Hanya pendaftaran berstatus kedaluwarsa yang dapat mengajukan klaim ulang slot.',
                ];
            }

            if (
                ! in_array($lockedEvent->status, ['open', 'quota_full'], true) ||
                ! $lockedEvent->isWithinRegistrationWindow()
            ) {
                return [
                    'ok' => false,
                    'field' => 'event_quota',
                    'message' => 'Pendaftaran untuk kompetisi ini belum dibuka atau telah ditutup oleh panitia.',
                ];
            }

            $occupied = $lockedEvent->teams()->occupyingQuotaAt(now())->count();

            if ($occupied >= $lockedEvent->max_teams) {
                $lockedEvent->syncQuotaStatus($occupied);

                return [
                    'ok' => false,
                    'field' => 'event_quota',
                    'message' => 'Maaf, kuota pendaftaran untuk kompetisi ini baru saja terpenuhi oleh tim lain.',
                ];
            }

            $now = now();
            $deadlineHours = max(1, (int) ($lockedEvent->payment_deadline_hours ?: 24));
            $expiresAt = $now->copy()->addHours($deadlineHours);

            $lockedTeam->update([
                'status' => 'pending_payment',
                'slot_reserved_at' => $now,
                'slot_expires_at' => $expiresAt,
            ]);

            $payment = Payment::create([
                'invoice_number' => $this->generateInvoiceNumber($lockedEvent),
                'team_id' => $lockedTeam->id,
                'event_id' => $lockedEvent->id,
                'amount' => $lockedEvent->registration_fee,
                'gateway_fee' => 0,
                'total_amount' => $lockedEvent->registration_fee,
                'expires_at' => $expiresAt,
                'status' => 'unpaid',
            ]);

            $lockedEvent->syncQuotaStatus($occupied + 1);

            return [
                'ok' => true,
                'payment' => $payment,
            ];
        });

        if (! $result['ok']) {
            throw ValidationException::withMessages([
                $result['field'] => $result['message'],
            ]);
        }

        return $result['payment'];
    }

    public function generateInvoiceNumber(Event $lockedEvent): string
    {
        $year = now()->format('Y');
        $evtCode = 'EVT' . str_pad((string) $lockedEvent->id, 2, '0', STR_PAD_LEFT);
        $seq = Payment::withTrashed()->where('event_id', $lockedEvent->id)->count() + 1;

        do {
            $candidate = sprintf('INV-%s-%s-%04d', $year, $evtCode, $seq);
            $exists = Payment::withTrashed()->where('invoice_number', $candidate)->exists();
            $seq++;
        } while ($exists);

        return $candidate;
    }
}

