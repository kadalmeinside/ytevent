<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Payment;
use App\Models\Player;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class EventSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Pastikan file banner poster Young Tiger Cup tersimpan di storage/app/public/events
        $targetBanner = storage_path('app/public/events/young-tiger-cup-u12-banner.jpg');
        $bundledAsset = database_path('seeders/assets/young-tiger-cup-u12-banner.jpg');
        $altSource = '/Users/macbook/.gemini/antigravity/brain/fdad4be1-49e6-447e-a227-50de012ac19f/.user_uploaded/media_1793868996233_29d232ff.jpg';

        File::ensureDirectoryExists(storage_path('app/public/events'));
        if (! File::exists($targetBanner)) {
            if (File::exists($bundledAsset)) {
                File::copy($bundledAsset, $targetBanner);
            } elseif (File::exists($altSource)) {
                File::copy($altSource, $targetBanner);
            }
        }

        $currentYear = now()->year;
        $matchStartDate = Carbon::create($currentYear, 12, 19, 8, 0, 0);
        if ($matchStartDate->isPast() && now()->month === 12 && now()->day > 20) {
            $matchStartDate = Carbon::create($currentYear + 1, 12, 19, 8, 0, 0);
        }
        $matchEndDate = (clone $matchStartDate)->addDay();
        $registrationClose = (clone $matchStartDate)->subDays(4)->endOfDay();

        Event::updateOrCreate(
            ['slug' => 'young-tiger-cup-u12'],
            [
                'name' => 'Young Tiger Cup U-12',
                'banner_path' => 'events/young-tiger-cup-u12-banner.jpg',
            'short_description' => 'Turnamen resmi Young Tiger Cup Kategori U-12 (Kelahiran 2014-2015) format 7 vs 7 (Bola Ukuran 4, durasi 2x15 menit) di Persija Academy Training Ground. Babak Grup (Grup A, B, C, D) dilanjutkan babak gugur menuju Juara 1, 2, 3, Pemain Terbaik, dan Pelatih Terbaik.',
                'venue_name' => 'Persija Academy Training Ground',
                'venue_maps_url' => 'https://maps.google.com/?q=Persija+Training+Ground+Nirwana+Park+Sawangan',
                'city' => 'DKI Jakarta',
            'format' => 'group_knockout',
            'age_category' => 'U-12',
            'min_birth_date' => '2014-01-01',
            'max_birth_date' => '2015-12-31',
            'min_players_per_team' => 10,
            'max_players_per_team' => 20,
            'max_officials_per_team' => 4,
            'required_documents' => [
                'birth_certificate',
                'family_card',
                'student_card_kia',
                'report_card_diploma',
            ],
            'max_teams' => 16,
            'registration_fee' => 2500000,
            'prize_info' => 'Juara 1: Piala, Medali, Equipment, Merchandise | Juara 2: Piala, Medali, Equipment | Juara 3: Piala, Medali | Pemain Terbaik: Medali, Merchandise | Pelatih Terbaik: Piala, Merchandise',
            'lock_roster_until_paid' => true,
            'payment_deadline_hours' => 24,
            'reupload_grace_hours' => 6,
            'registration_open_at' => now()->subDays(5),
            'registration_close_at' => $registrationClose,
            'match_start_date' => $matchStartDate->toDateString(),
            'match_end_date' => $matchEndDate->toDateString(),
            'contact_person' => 'Panitia Pelaksana Young Tiger Cup',
            'contact_whatsapp' => '081128021928',
            'status' => 'open',
        ]);
    }
}
