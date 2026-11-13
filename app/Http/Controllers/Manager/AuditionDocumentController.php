<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\ReuploadAuditionDocumentsRequest;
use App\Models\AuditionParticipant;
use App\Services\AuditionDocumentReuploadService;
use Illuminate\Http\RedirectResponse;

class AuditionDocumentController extends Controller
{
    public function update(
        ReuploadAuditionDocumentsRequest $request,
        AuditionParticipant $participant,
        AuditionDocumentReuploadService $service
    ): RedirectResponse {
        $participant->loadMissing('event');
        abort_unless($participant->event?->participant_type === 'individual', 404);
        abort_unless(
            $participant->event->canModifyTeamRoster(),
            422,
            'Masa revisi berkas telah berakhir karena event sedang berlangsung atau sudah selesai.'
        );
        abort_unless($participant->screening_status === 'rejected', 422, 'Dokumen peserta tidak sedang menunggu revisi.');

        $rejectedAt = $participant->screening_verified_at;
        $graceHours = max(0, (int) ($participant->event->reupload_grace_hours ?? 0));
        abort_unless(
            $rejectedAt && $rejectedAt->copy()->addHours($graceHours)->isFuture(),
            422,
            'Batas waktu unggah ulang dokumen telah berakhir.'
        );

        $service->replace($participant, $request->validated());

        return back()->with('success', 'Dokumen revisi berhasil diunggah dan menunggu pemeriksaan panitia.');
    }
}
