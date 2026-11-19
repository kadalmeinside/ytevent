<?php

namespace App\Services;

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
        protected QuotaExpirationService $quotaExpiration,
        protected ImageOptimizationService $imageOptimization,
        protected InvoiceService $invoiceService
    ) {}

    public function resolvePrimaryTeamForUser(User $user): ?Team
    {
        return Team::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();
    }

    public function findConflictingActiveRegistration(User $user, Event $targetEvent): ?Team
    {
        if ($targetEvent->status === 'cancelled') {
            return null;
        }

        $activeTeams = Team::query()
            ->with('event')
            ->where('user_id', $user->id)
            ->where('event_id', '!=', $targetEvent->id)
            ->whereIn('status', ['pending_payment', 'active', 'locked_roster'])
            ->whereHas('event', fn ($q) => $q->whereNotIn('status', ['completed', 'cancelled']))
            ->get();

        return $this->findConflictingTeamInCollection($activeTeams, $targetEvent);
    }

    public function findConflictingTeamInCollection(\Illuminate\Support\Collection $activeTeams, Event $targetEvent): ?Team
    {
        if (! $targetEvent->match_start_date || $targetEvent->status === 'cancelled') {
            return null;
        }

        $targetStart = $targetEvent->match_start_date->copy()->startOfDay();
        $targetEnd = ($targetEvent->match_end_date ?? $targetEvent->match_start_date)->copy()->endOfDay();

        return $activeTeams->first(function (Team $existingTeam) use ($targetEvent, $targetStart, $targetEnd) {
            if ($existingTeam->event_id === $targetEvent->id) {
                return false;
            }

            if ($existingTeam->status === 'pending_payment' && $existingTeam->slot_expires_at?->isPast()) {
                return false;
            }

            $existingEvent = $existingTeam->event;
            if (! $existingEvent?->match_start_date || in_array($existingEvent->status, ['completed', 'cancelled'], true)) {
                return false;
            }

            $existingStart = $existingEvent->match_start_date->copy()->startOfDay();
            $existingEnd = ($existingEvent->match_end_date ?? $existingEvent->match_start_date)->copy()->endOfDay();

            return $targetStart->lte($existingEnd) && $targetEnd->gte($existingStart);
        });
    }

    public function formatEventDateRange(Event $event): string
    {
        if (! $event->match_start_date) {
            return 'Jadwal menyusul';
        }

        $start = $event->match_start_date->translatedFormat('d M Y');
        if ($event->match_end_date && ! $event->match_end_date->isSameDay($event->match_start_date)) {
            return $start.' - '.$event->match_end_date->translatedFormat('d M Y');
        }

        return $start;
    }

    public function registerNewTeam(
        Event $event,
        array $validated,
        ?UploadedFile $logoFile = null,
        ?User $authenticatedUser = null,
        ?string $acceptedIp = null,
        ?string $acceptedUserAgent = null
    ): Team {
        $primaryTeam = $authenticatedUser ? $this->resolvePrimaryTeamForUser($authenticatedUser) : null;
        $uploadedNewLogo = $logoFile !== null;

        if ($logoFile !== null) {
            $logo = $this->imageOptimization->storeWithOptimizedVariant($logoFile, 'teams/logos', 'public', 1200, 85);
        } elseif ($primaryTeam && ($primaryTeam->logo_path || $primaryTeam->logo_optimized_path)) {
            $logo = [
                'original_path' => $primaryTeam->logo_path,
                'optimized_path' => $primaryTeam->logo_optimized_path,
            ];
        } else {
            throw ValidationException::withMessages([
                'logo' => 'Logo resmi tim wajib diunggah.',
            ]);
        }

        try {
            $result = DB::transaction(function () use ($event, $validated, $logo, $authenticatedUser, $primaryTeam, $acceptedIp, $acceptedUserAgent) {
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

                if (! $lockedEvent->isUnlimitedQuota() && $lockedEvent->max_teams && $occupied >= $lockedEvent->max_teams) {
                    $lockedEvent->syncQuotaStatus($occupied);

                    return [
                        'ok' => false,
                        'field' => 'event_quota',
                        'message' => 'Maaf, kuota pendaftaran untuk kompetisi ini baru saja terpenuhi oleh tim lain.',
                    ];
                }

                // 4. Pastikan aturan 1 tim per akun, anti bentrok jadwal, serta keunikan nama tim & email
                $teamName = $primaryTeam ? $primaryTeam->name : trim($validated['team_name']);

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

                    $conflictTeam = $this->findConflictingActiveRegistration($authenticatedUser, $lockedEvent);
                    if ($conflictTeam && $conflictTeam->event) {
                        return [
                            'ok' => false,
                            'field' => 'event_quota',
                            'message' => "Tim Anda ({$conflictTeam->name}) sudah terdaftar pada kompetisi {$conflictTeam->event->name} ({$this->formatEventDateRange($conflictTeam->event)}) yang tanggal penyelenggaraannya bertabrakan dengan kompetisi ini.",
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

                $nameExists = $lockedEvent->teams()
                    ->where('name', $teamName)
                    ->exists();

                if ($nameExists) {
                    return [
                        'ok' => false,
                        'field' => 'team_name',
                        'message' => 'Nama tim ini sudah terdaftar pada kompetisi yang dipilih.',
                    ];
                }

                // 5. Reservasi Slot & Pembuatan Tim + Invoice
                $now = now();
                $deadlineHours = max(1, (int) ($lockedEvent->payment_deadline_hours ?: 24));
                $expiresAt = $now->copy()->addHours($deadlineHours);
                $isFree = ((float) $lockedEvent->registration_fee) <= 0;

                $baseSlug = Str::slug($teamName);
                $slug = $baseSlug.'-'.Str::lower(Str::random(5));

                $tncVersion = $lockedEvent->resolveTncVersion();

                $team = Team::create([
                    'event_id' => $lockedEvent->id,
                    'user_id' => $managerUser->id,
                    'name' => $teamName,
                    'slug' => $slug,
                    'logo_path' => $logo['original_path'],
                    'logo_optimized_path' => $logo['optimized_path'],
                    'origin_association' => trim($validated['origin_association']),
                    'secretariat_address' => $validated['secretariat_address'] ?? null,
                    'manager_name' => trim($validated['manager_name']),
                    'manager_whatsapp' => trim($validated['manager_whatsapp']),
                    'manager_email' => $authenticatedUser ? $managerUser->email : strtolower(trim($validated['email'])),
                    'head_coach_name' => trim($validated['head_coach_name']),
                    'head_coach_license' => $validated['head_coach_license'] ?? null,
                    'terms_accepted_at' => $now,
                    'accepted_tnc_version_id' => $tncVersion->id,
                    'accepted_tnc_version' => $tncVersion->version,
                    'accepted_tnc_hash' => $tncVersion->contentHashForEvent($lockedEvent),
                    'accepted_ip' => $acceptedIp,
                    'accepted_user_agent' => $acceptedUserAgent ? Str::limit($acceptedUserAgent, 500, '') : null,
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

                $payment = Payment::create([
                    'invoice_number' => $invoiceNumber,
                    'team_id' => $team->id,
                    'event_id' => $lockedEvent->id,
                    'billing_snapshot' => $this->invoiceService->buildSnapshot($lockedEvent, $team, null, $managerUser),
                    'amount' => $lockedEvent->registration_fee,
                    'gateway_fee' => 0,
                    'total_amount' => $lockedEvent->registration_fee,
                    'method' => $isFree ? 'manual' : null,
                    'payment_channel' => $isFree ? 'Pendaftaran Gratis' : null,
                    'expires_at' => $isFree ? null : $expiresAt,
                    'issued_at' => $now,
                    'status' => $isFree ? 'paid' : 'unpaid',
                    'paid_at' => $isFree ? $now : null,
                ]);

                // 6. Sinkronisasi status open -> quota_full bila slot terakhir terambil
                $lockedEvent->syncQuotaStatus($occupied + 1);

                $emailService = app(EmailNotificationService::class);
                if ($isFree) {
                    $emailService->sendPaymentConfirmed($team, $payment);
                } else {
                    $emailService->sendTeamRegistered($team, $payment);
                }

                return [
                    'ok' => true,
                    'team' => $team,
                ];
            });
        } catch (\Throwable $e) {
            if ($uploadedNewLogo) {
                Storage::disk('public')->delete(array_values(array_filter(array_unique([
                    $logo['original_path'],
                    $logo['optimized_path'],
                ]))));
            }
            throw $e;
        }

        if (! $result['ok']) {
            if ($uploadedNewLogo) {
                Storage::disk('public')->delete(array_values(array_filter(array_unique([
                    $logo['original_path'],
                    $logo['optimized_path'],
                ]))));
            }
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

            if (! $lockedEvent->isUnlimitedQuota() && $lockedEvent->max_teams && $occupied >= $lockedEvent->max_teams) {
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

            $now = now();
            $payment = Payment::create([
                'invoice_number' => $this->generateInvoiceNumber($lockedEvent),
                'team_id' => $lockedTeam->id,
                'event_id' => $lockedEvent->id,
                'billing_snapshot' => $this->invoiceService->buildSnapshot($lockedEvent, $lockedTeam, null, $lockedTeam->manager),
                'amount' => $lockedEvent->registration_fee,
                'gateway_fee' => 0,
                'total_amount' => $lockedEvent->registration_fee,
                'expires_at' => $expiresAt,
                'issued_at' => $now,
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
        return $this->invoiceService->generateNumber($lockedEvent);
    }
}
