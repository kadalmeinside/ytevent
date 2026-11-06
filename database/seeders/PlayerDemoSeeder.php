<?php

namespace Database\Seeders;

use App\Models\Player;
use App\Models\PlayerDocument;
use App\Models\Team;
use App\Services\ScreeningService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class PlayerDemoSeeder extends Seeder
{
    /**
     * Seed dummy players with status 'submitted' and complete documents
     * for manual screening verification testing.
     */
    public function run(): void
    {
        $team = Team::where('status', 'active')->first();

        if (! $team) {
            $this->command?->error('Tidak ditemukan tim dengan status active.');
            return;
        }

        $samplePhotoPath = 'players/photos/A5qWBc2nReCMNRv6XgnqaEGMmVr5yAoZZkqBYm5X.jpg';
        $sampleDocs = [
            'birth_certificate' => [
                'path' => 'players/documents/BKEvUuhPJ2L6h64fNzo1B0WfjfLfUBijaOYZC0sN.jpg',
                'prefix' => 'Akta_Kelahiran_',
                'num_prefix' => '3174-LT-',
            ],
            'family_card' => [
                'path' => 'players/documents/VcBB4yx5ccNwcqhCha4TYGsHuhwFtxw5wCHiVAkK.jpg',
                'prefix' => 'Kartu_Keluarga_',
                'num_prefix' => '3174010101',
            ],
            'student_card_kia' => [
                'path' => 'players/documents/44RS5VAttl9e7r2OovZ8TuMDZTkiu8hfPsrm09Qr.jpg',
                'prefix' => 'KIA_',
                'num_prefix' => 'KIA-3174-',
            ],
            'report_card_diploma' => [
                'path' => 'players/documents/D7du29PuS0wz54enrAXe8CaYw80V3Oky2xa0EvhL.jpg',
                'prefix' => 'Rapor_SD_',
                'num_prefix' => 'RAPOR-SD-',
            ],
        ];

        // Pastikan file dummy tersedia di storage privat
        $privateDisk = Storage::disk('private');
        if (! $privateDisk->exists($samplePhotoPath)) {
            $privateDisk->put($samplePhotoPath, 'DUMMY_PHOTO_BYTES');
        }
        foreach ($sampleDocs as $doc) {
            if (! $privateDisk->exists($doc['path'])) {
                $privateDisk->put($doc['path'], 'DUMMY_DOC_BYTES');
            }
        }

        $playersData = [
            [
                'full_name' => 'Muhammad Alfaridzi',
                'jersey_name' => 'ALFARIDZI',
                'jersey_number' => 1,
                'position' => 'GK',
                'birth_date' => '2014-04-12',
                'birth_place' => 'Jakarta',
                'nik' => '3174011204140001',
                'nisn' => '0145892001',
                'height_cm' => 156,
                'weight_kg' => 48,
            ],
            [
                'full_name' => 'Bagas Pratama Putra',
                'jersey_name' => 'BAGAS P.',
                'jersey_number' => 2,
                'position' => 'DF',
                'birth_date' => '2014-06-25',
                'birth_place' => 'Tangerang',
                'nik' => '3671012506140002',
                'nisn' => '0145892002',
                'height_cm' => 152,
                'weight_kg' => 44,
            ],
            [
                'full_name' => 'Rizky Ramadhan',
                'jersey_name' => 'RIZKY',
                'jersey_number' => 3,
                'position' => 'DF',
                'birth_date' => '2014-08-14',
                'birth_place' => 'Bekasi',
                'nik' => '3275011408140003',
                'nisn' => '0145892003',
                'height_cm' => 154,
                'weight_kg' => 45,
            ],
            [
                'full_name' => 'Dimas Dwi Anggoro',
                'jersey_name' => 'DIMAS',
                'jersey_number' => 4,
                'position' => 'DF',
                'birth_date' => '2014-02-19',
                'birth_place' => 'Depok',
                'nik' => '3276011902140004',
                'nisn' => '0145892004',
                'height_cm' => 150,
                'weight_kg' => 42,
            ],
            [
                'full_name' => 'Farel Aditya Wijaya',
                'jersey_name' => 'FAREL',
                'jersey_number' => 5,
                'position' => 'DF',
                'birth_date' => '2014-11-03',
                'birth_place' => 'Jakarta',
                'nik' => '3175010311140005',
                'nisn' => '0145892005',
                'height_cm' => 153,
                'weight_kg' => 43,
            ],
            [
                'full_name' => 'Aldo Saputra',
                'jersey_name' => 'ALDO',
                'jersey_number' => 6,
                'position' => 'MF',
                'birth_date' => '2014-05-18',
                'birth_place' => 'Bogor',
                'nik' => '3271011805140006',
                'nisn' => '0145892006',
                'height_cm' => 148,
                'weight_kg' => 40,
            ],
            [
                'full_name' => 'Nathan Putra Pratama',
                'jersey_name' => 'NATHAN',
                'jersey_number' => 8,
                'position' => 'MF',
                'birth_date' => '2014-09-09',
                'birth_place' => 'Jakarta',
                'nik' => '3171010909140007',
                'nisn' => '0145892007',
                'height_cm' => 151,
                'weight_kg' => 42,
            ],
            [
                'full_name' => 'Bintang Pamungkas',
                'jersey_name' => 'BINTANG',
                'jersey_number' => 10,
                'position' => 'MF',
                'birth_date' => '2014-03-21',
                'birth_place' => 'Tangerang Selatan',
                'nik' => '3674012103140008',
                'nisn' => '0145892008',
                'height_cm' => 149,
                'weight_kg' => 41,
            ],
            [
                'full_name' => 'Danendra Arya Sena',
                'jersey_name' => 'DANENDRA',
                'jersey_number' => 12,
                'position' => 'MF',
                'birth_date' => '2014-07-30',
                'birth_place' => 'Jakarta',
                'nik' => '3173013007140009',
                'nisn' => '0145892009',
                'height_cm' => 150,
                'weight_kg' => 43,
            ],
            [
                'full_name' => 'Haikal Firdaus',
                'jersey_name' => 'HAIKAL',
                'jersey_number' => 7,
                'position' => 'FW',
                'birth_date' => '2014-10-15',
                'birth_place' => 'Bekasi',
                'nik' => '3216011510140010',
                'nisn' => '0145892010',
                'height_cm' => 155,
                'weight_kg' => 46,
            ],
            [
                'full_name' => 'Rafi Ahmad Fauzan',
                'jersey_name' => 'RAFI',
                'jersey_number' => 11,
                'position' => 'FW',
                'birth_date' => '2014-01-28',
                'birth_place' => 'Depok',
                'nik' => '3276012801140011',
                'nisn' => '0145892011',
                'height_cm' => 153,
                'weight_kg' => 44,
            ],
        ];

        foreach ($playersData as $data) {
            $player = Player::updateOrCreate(
                [
                    'team_id' => $team->id,
                    'event_id' => $team->event_id,
                    'nik' => $data['nik'],
                ],
                [
                    'full_name' => $data['full_name'],
                    'jersey_name' => $data['jersey_name'],
                    'jersey_number' => $data['jersey_number'],
                    'position' => $data['position'],
                    'dominant_foot' => 'Right',
                    'birth_date' => Carbon::parse($data['birth_date']),
                    'birth_place' => $data['birth_place'],
                    'nisn' => $data['nisn'],
                    'height_cm' => $data['height_cm'],
                    'weight_kg' => $data['weight_kg'],
                    'photo_path' => $samplePhotoPath,
                    'screening_status' => 'submitted',
                    'is_locked' => false,
                    'verified_by' => null,
                    'verified_at' => null,
                    'rejection_reason' => null,
                    'accreditation_code' => null,
                ]
            );

            foreach ($sampleDocs as $type => $info) {
                PlayerDocument::updateOrCreate(
                    [
                        'player_id' => $player->id,
                        'document_type' => $type,
                    ],
                    [
                        'document_number' => $info['num_prefix'] . $data['jersey_number'] . '00' . $player->id,
                        'file_path' => $info['path'],
                        'original_name' => $info['prefix'] . str_replace(' ', '_', $data['full_name']) . '.jpg',
                        'file_size' => 134472,
                        'mime_type' => 'image/jpeg',
                        'validation_status' => 'pending',
                        'notes' => null,
                    ]
                );
            }
        }

        // Sinkronisasi status screening tim
        app(ScreeningService::class)->syncTeamScreeningStatus($team);

        $this->command?->info("Berhasil menambahkan " . count($playersData) . " pemain berstatus 'submitted' untuk tim {$team->name}.");
    }
}
