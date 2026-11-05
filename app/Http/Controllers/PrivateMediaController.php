<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Player;
use App\Models\PlayerDocument;
use App\Models\TeamOfficial;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrivateMediaController extends Controller
{
    public function paymentProof(Payment $payment): StreamedResponse
    {
        $payment->loadMissing('team');
        $this->authorize('view', $payment);

        return $this->streamPrivateFile($payment->proof_file_path);
    }

    public function playerPhoto(Player $player): StreamedResponse
    {
        $player->loadMissing('team');
        $this->authorize('view', $player);

        return $this->streamPrivateFile($player->photo_path);
    }

    public function playerDocument(PlayerDocument $document): StreamedResponse
    {
        $document->loadMissing('player.team');
        $this->authorize('view', $document->player);

        return $this->streamPrivateFile($document->file_path, $document->original_name);
    }

    public function officialPhoto(TeamOfficial $official): StreamedResponse
    {
        $official->loadMissing('team');
        $this->authorize('view', $official->team);

        return $this->streamPrivateFile($official->photo_path);
    }

    public function officialLicense(TeamOfficial $official): StreamedResponse
    {
        $official->loadMissing('team');
        $this->authorize('view', $official->team);

        return $this->streamPrivateFile($official->license_file_path);
    }

    protected function streamPrivateFile(?string $path, ?string $filename = null): StreamedResponse
    {
        if (! $path || ! Storage::disk('private')->exists($path)) {
            abort(404, 'Berkas dokumen tidak ditemukan.');
        }

        $mime = Storage::disk('private')->mimeType($path) ?: 'application/octet-stream';
        $safeName = $filename ?: basename($path);

        return Storage::disk('private')->response($path, $safeName, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}

