<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\AuditionParticipant;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuditionRegistrationService
{
    public function __construct(
        protected ImageOptimizationService $imageOptimization
    ) {}

    public function registerAuditionParticipant(
        Event $event,
        array $validated,
        UploadedFile $photoFile,
        UploadedFile $birthCertFile,
        UploadedFile $familyCardFile,
        ?UploadedFile $additionalDocFile = null,
        ?User $authenticatedUser = null
    ): array {
        return DB::transaction(function () use (
            $event,
            $validated,
            $photoFile,
            $birthCertFile,
            $familyCardFile,
            $additionalDocFile,
            $authenticatedUser
        ) {
            /** @var Event $lockedEvent */
            $lockedEvent = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();

            if (! $lockedEvent->canAcceptRegistration()) {
                throw ValidationException::withMessages([
                    'event' => 'Pendaftaran untuk event ini sedang ditutup atau kuota telah terpenuhi.',
                ]);
            }

            // Quota Check untuk mode fixed
            if (! $lockedEvent->isUnlimitedQuota() && $lockedEvent->max_participants) {
                $occupied = AuditionParticipant::query()
                    ->where('event_id', $lockedEvent->id)
                    ->whereIn('status', ['pending_payment', 'in_review', 'active'])
                    ->count();

                if ($occupied >= $lockedEvent->max_participants) {
                    $lockedEvent->update(['status' => 'quota_full']);
                    throw ValidationException::withMessages([
                        'quota' => 'Mohon maaf, kuota peserta untuk audisi ini telah penuh.',
                    ]);
                }
            }

            // Session Quota Check (jika memilih gelombang/sesi waktu)
            $sessionId = $validated['event_session_id'] ?? null;
            if ($sessionId) {
                /** @var EventSession|null $session */
                $session = EventSession::query()
                    ->where('id', $sessionId)
                    ->where('event_id', $lockedEvent->id)
                    ->lockForUpdate()
                    ->first();

                if ($session && $session->isFull()) {
                    throw ValidationException::withMessages([
                        'event_session_id' => 'Sesi waktu / gelombang yang dipilih telah penuh. Silakan pilih sesi lainnya.',
                    ]);
                }
            }

            // Position Soft-Cap Check
            $posCaps = $lockedEvent->position_quota_caps;
            $prefPos = $validated['preferred_position'] ?? null;
            if (! empty($posCaps) && ! empty($prefPos) && isset($posCaps[$prefPos])) {
                $posCount = AuditionParticipant::query()
                    ->where('event_id', $lockedEvent->id)
                    ->where('preferred_position', $prefPos)
                    ->whereIn('status', ['pending_payment', 'in_review', 'active'])
                    ->count();

                if ($posCount >= (int) $posCaps[$prefPos]) {
                    throw ValidationException::withMessages([
                        'preferred_position' => "Mohon maaf, kuota untuk posisi {$prefPos} pada audisi ini telah penuh. Anda dapat memilih posisi alternatif.",
                    ]);
                }
            }

            // Resolve / Create Parent / Guardian User Account
            $user = $authenticatedUser;
            if (! $user) {
                $user = User::firstOrCreate(
                    ['email' => $validated['guardian_email']],
                    [
                        'name' => $validated['guardian_name'],
                        'phone' => $validated['guardian_phone'],
                        'password' => Hash::make($validated['guardian_password']),
                        'is_active' => true,
                    ]
                );

                if (! $user->hasRole('participant_parent') && ! $user->hasRole('team_manager')) {
                    $user->assignRole('participant_parent');
                }
            } else {
                if (! $user->hasRole('participant_parent') && ! $user->hasRole('team_manager')) {
                    $user->assignRole('participant_parent');
                }
            }

            // Upload Files
            // Pas Foto (Public Storage dengan WebP/JPEG optimization)
            $photoStored = $this->imageOptimization->storeWithOptimizedVariant(
                $photoFile,
                'auditions/photos',
                disk: 'public',
                maxDimension: 800,
                quality: 85
            );
            $photoPath = $photoStored['optimized_path'] ?: $photoStored['original_path'];

            // Dokumen Keabsahan (Private Storage)
            $birthCertExt = $birthCertFile->getClientOriginalExtension() ?: 'pdf';
            $birthCertPath = $birthCertFile->storeAs(
                'auditions/documents/birth_cert',
                Str::random(40) . '.' . $birthCertExt,
                'private'
            );

            $familyCardExt = $familyCardFile->getClientOriginalExtension() ?: 'pdf';
            $familyCardPath = $familyCardFile->storeAs(
                'auditions/documents/family_card',
                Str::random(40) . '.' . $familyCardExt,
                'private'
            );

            $additionalDocPath = null;
            if ($additionalDocFile) {
                $addExt = $additionalDocFile->getClientOriginalExtension() ?: 'pdf';
                $additionalDocPath = $additionalDocFile->storeAs(
                    'auditions/documents/additional',
                    Str::random(40) . '.' . $addExt,
                    'private'
                );
            }

            // Generate Registration Number: AUD-{YEAR}-{EVENT_ID_PADDED}-{SEQUENCE_PADDED}
            $year = now()->format('Y');
            $nextSeq = AuditionParticipant::query()->where('event_id', $lockedEvent->id)->count() + 1;
            $regNumber = sprintf('AUD-%s-%03d-%04d', $year, $lockedEvent->id, $nextSeq);

            $deadlineHours = max(1, (int) ($lockedEvent->payment_deadline_hours ?: 24));
            $slotExpiresAt = now()->addHours($deadlineHours);

            $fee = (float) $lockedEvent->registration_fee;
            $status = $fee > 0 ? 'pending_payment' : 'active';

            // Create AuditionParticipant
            /** @var AuditionParticipant $participant */
            $participant = AuditionParticipant::create([
                'event_id' => $lockedEvent->id,
                'user_id' => $user->id,
                'event_session_id' => $sessionId,
                'registration_number' => $regNumber,
                'bib_number' => null, // Diterbitkan setelah lolos screening & lunas
                'full_name' => $validated['full_name'],
                'nickname' => $validated['nickname'] ?? null,
                'nik' => $validated['nik'],
                'birth_date' => $validated['birth_date'],
                'gender' => $validated['gender'],
                'preferred_position' => $validated['preferred_position'] ?? null,
                'secondary_position' => $validated['secondary_position'] ?? null,
                'dominant_foot' => $validated['dominant_foot'] ?? null,
                'height_cm' => isset($validated['height_cm']) ? (int) $validated['height_cm'] : null,
                'weight_kg' => isset($validated['weight_kg']) ? (int) $validated['weight_kg'] : null,
                'current_club_ssb' => $validated['current_club_ssb'] ?? null,
                'playing_experience' => $validated['playing_experience'] ?? null,
                'jersey_size' => $validated['jersey_size'] ?? null,
                'emergency_contact_name' => $validated['emergency_contact_name'],
                'emergency_contact_phone' => $validated['emergency_contact_phone'],
                'emergency_contact_relation' => $validated['emergency_contact_relation'] ?? null,
                'medical_notes' => $validated['medical_notes'] ?? null,
                'custom_answers' => $validated['custom_answers'] ?? null,
                'liability_accepted_at' => now(),
                'photo_path' => $photoPath,
                'birth_certificate_path' => $birthCertPath,
                'family_card_path' => $familyCardPath,
                'additional_doc_path' => $additionalDocPath,
                'screening_status' => 'submitted',
                'stage_status' => 'registered',
                'status' => $status,
                'slot_reserved_at' => now(),
                'slot_expires_at' => $slotExpiresAt,
            ]);

            // Increment session occupied slots jika ada
            if ($sessionId) {
                EventSession::where('id', $sessionId)->increment('occupied_slots');
            }

            // Create Payment Invoice jika berbayar
            $payment = null;
            if ($fee > 0) {
                $invoiceNumber = sprintf('INV-%s-AUD%d-%04d', $year, $lockedEvent->id, $participant->id);

                $payment = Payment::create([
                    'invoice_number' => $invoiceNumber,
                    'audition_participant_id' => $participant->id,
                    'event_id' => $lockedEvent->id,
                    'amount' => $fee,
                    'gateway_fee' => 0,
                    'total_amount' => $fee,
                    'status' => 'unpaid',
                    'expires_at' => $slotExpiresAt,
                ]);
            }

            AuditLog::record(
                entity: $participant,
                action: 'audition.participant.registered',
                actor: $user,
                metadata: [
                    'registration_number' => $regNumber,
                    'fee' => $fee,
                    'invoice_number' => $payment?->invoice_number,
                ]
            );

            return [
                'participant' => $participant,
                'payment' => $payment,
                'user' => $user,
            ];
        });
    }
}
