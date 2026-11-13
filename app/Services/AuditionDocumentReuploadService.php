<?php

namespace App\Services;

use App\Models\AuditionParticipant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AuditionDocumentReuploadService
{
    public function replace(AuditionParticipant $participant, array $files): void
    {
        $disk = Storage::disk('private');
        $oldPaths = [];
        $newPaths = [];

        try {
            foreach ([
                'birth_certificate' => ['column' => 'birth_certificate_path', 'directory' => 'birth_cert'],
                'family_card' => ['column' => 'family_card_path', 'directory' => 'family_card'],
                'additional_doc' => ['column' => 'additional_doc_path', 'directory' => 'additional'],
            ] as $key => $definition) {
                if (! ($files[$key] ?? null) instanceof UploadedFile) {
                    continue;
                }

                $file = $files[$key];
                $extension = $file->getClientOriginalExtension() ?: 'pdf';
                $path = $file->storeAs(
                    'auditions/documents/'.$definition['directory'],
                    Str::random(40).'.'.$extension,
                    'private'
                );
                $newPaths[] = $path;
                $oldPaths[] = $participant->{$definition['column']};
                $participant->{$definition['column']} = $path;
            }

            DB::transaction(function () use ($participant): void {
                $participant->screening_status = 'submitted';
                $participant->screening_notes = null;
                $participant->screening_verified_by = null;
                $participant->screening_verified_at = null;
                $participant->save();
            });

            foreach (array_filter($oldPaths) as $oldPath) {
                $disk->delete($oldPath);
            }
        } catch (\Throwable $exception) {
            foreach ($newPaths as $newPath) {
                $disk->delete($newPath);
            }

            throw $exception;
        }
    }
}
