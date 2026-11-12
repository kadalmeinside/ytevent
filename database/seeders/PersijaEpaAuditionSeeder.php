<?php

namespace Database\Seeders;

use App\Models\AuditionAttendance;
use App\Models\AuditionParticipant;
use App\Models\AuditionStage;
use App\Models\Event;
use App\Models\Payment;
use App\Models\ScoutingEvaluation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PersijaEpaAuditionSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Pastikan aset banner tersimpan di public disk
        File::ensureDirectoryExists(storage_path('app/public/events'));
        File::ensureDirectoryExists(storage_path('app/public/banners'));

        $targetBanner = storage_path('app/public/events/persija-epa-future-stars-2026.jpg');
        $sourceAsset = database_path('seeders/assets/persija-epa-future-stars-2026.jpg');
        $altSource = '/Users/macbook/.gemini/antigravity/brain/fdad4be1-49e6-447e-a227-50de012ac19f/.user_uploaded/media_1794504951819_3cc3e895.jpg';

        if (! File::exists($targetBanner)) {
            if (File::exists($sourceAsset)) {
                File::copy($sourceAsset, $targetBanner);
            } elseif (File::exists($altSource)) {
                File::copy($altSource, $targetBanner);
            }
        }

        // 2. Buat atau update Event Audisi "Persija EPA Future Stars Scouting Fest 2026"
        $event = Event::updateOrCreate(
            ['slug' => 'persija-epa-future-stars-2026'],
            [
                'name' => 'Persija EPA Future Stars Scouting Fest 2026: Goes to Spain',
                'banner_path' => 'events/persija-epa-future-stars-2026.jpg',
                'short_description' => 'Audisi terbuka talenta muda sepak bola usia 14+ (Kelahiran 2012–2013) untuk menembus skuad Persija Elite Pro Academy (EPA). Pemain terbaik yang lolos seleksi final akan diberangkatkan GRATIS mengikuti Program Pemusatan Latihan & Trial ke Spanyol. Seleksi bertahap 3 hari di Persija Training Ground Bojongsari meliputi screening fisik, tes keterampilan teknis, game internal, dan penilaian panel scouting terpadu.',
                'venue_name' => 'Persija Training Ground, Bojongsari',
                'venue_maps_url' => 'https://maps.google.com/?q=Persija+Training+Ground+Nirwana+Park+Sawangan',
                'city' => 'Kota Depok',
                'event_type' => 'audition',
                'participant_type' => 'individual',
                'quota_type' => 'fixed',
                'max_participants' => 150,
                'max_teams' => 150,
                'position_quota_caps' => [
                    'GK' => 16,
                    'CB' => 30,
                    'FB' => 30,
                    'MF' => 44,
                    'FW' => 30,
                ],
                'form_presets' => [
                    'photo' => true,
                    'position' => true,
                    'foot_dominance' => true,
                    'team_origin' => true,
                    'body_measurements' => true,
                    'experience' => true,
                    'jersey_size' => true,
                ],
                'format' => 'audition',
                'age_category' => 'U-14',
                'min_birth_date' => '2012-01-01',
                'max_birth_date' => '2013-12-31',
                'required_documents' => [
                    'birth_certificate',
                    'family_card',
                    'student_card_kia',
                    'report_card_diploma',
                ],
                'registration_fee' => 250000,
                'prize_info' => 'GRATIS bagi seluruh pemain terpilih mengikuti Program Pemusatan Latihan & Trial Internasional ke Spanyol bersama Persija Elite Pro Academy (EPA).',
                'lock_roster_until_paid' => true,
                'payment_deadline_hours' => 24,
                'reupload_grace_hours' => 12,
                'registration_open_at' => Carbon::create(2026, 10, 26, 0, 0, 0),
                'registration_close_at' => Carbon::create(2026, 11, 20, 23, 59, 59),
                'match_start_date' => '2026-11-10',
                'match_end_date' => '2026-11-12',
                'contact_person' => 'Panitia Pelaksana Persija EPA Scouting Fest',
                'contact_whatsapp' => '081128021928',
                'status' => 'open',
            ]
        );

        // 3. Buat 3 Tahap Audisi (10, 11, 12 Nov 2026)
        $stage1 = AuditionStage::updateOrCreate(
            ['event_id' => $event->id, 'stage_number' => 1],
            [
                'stage_name' => 'Tahap 1: Screening Fisik, Medis & Tes Keterampilan Teknik',
                'stage_date' => '2026-11-10',
                'start_time' => '07:30:00',
                'end_time' => '17:00:00',
                'venue_name' => 'Persija Training Ground Lapangan A & B',
                'target_quota' => 150,
                'is_active' => true,
                'is_completed' => false,
                'instructions' => 'Seluruh peserta wajib hadir 30 menit sebelum jadwal, membawa Bib Tag / E-Tiket dan mengenakan perlengkapan sepak bola lengkap (sepatu bola & shin guards).',
            ]
        );

        $stage2 = AuditionStage::updateOrCreate(
            ['event_id' => $event->id, 'stage_number' => 2],
            [
                'stage_name' => 'Tahap 2: Game Internal 11v11 & Evaluasi Taktikal',
                'stage_date' => '2026-11-11',
                'start_time' => '08:00:00',
                'end_time' => '16:00:00',
                'venue_name' => 'Persija Training Ground Lapangan Utama',
                'target_quota' => 60,
                'is_active' => false,
                'is_completed' => false,
                'instructions' => 'Khusus peserta yang dinyatakan Lolos Tahap 1 (Day 1 Passed). Format pertandingan 11 vs 11 dengan pemantauan langsung tim pelatih EPA.',
            ]
        );

        $stage3 = AuditionStage::updateOrCreate(
            ['event_id' => $event->id, 'stage_number' => 3],
            [
                'stage_name' => 'Tahap 3: Final Selection Match (Persija EPA Goes to Spain)',
                'stage_date' => '2026-11-12',
                'start_time' => '08:30:00',
                'end_time' => '13:00:00',
                'venue_name' => 'Persija Training Ground Stadion Utama',
                'target_quota' => 22,
                'is_active' => false,
                'is_completed' => false,
                'instructions' => 'Babak puncak penentuan talenta masa depan yang berhak mendapatkan tiket dan beasiswa penuh pemusatan latihan ke Spanyol.',
            ]
        );

        // 4. Pastikan akun Scout / Pemandu Bakat tersedia
        $scoutUser = User::firstOrCreate(
            ['email' => 'scout.spain@persijadevelopment.id'],
            [
                'name' => 'Coach Antonio Hernandez (EPA Scout)',
                'password' => Hash::make('Scout12345'),
                'is_active' => true,
            ]
        );
        if (! $scoutUser->hasRole('talent_scout')) {
            $scoutUser->assignRole('talent_scout');
        }

        $officerUser = User::role('screening_officer')->first()
            ?? User::role('superadmin')->first()
            ?? $scoutUser;

        // 5. Inject 8 Peserta Atlet Representatif (Kelahiran 2012–2013)
        $sampleAthletes = [
            [
                'name' => 'Muhammad Rayhan Al-Fatih',
                'nick' => 'Rayhan',
                'nik' => '3171021205120001',
                'birth' => '2012-05-12',
                'pos' => 'Attacking Midfielder (AMF)',
                'sec_pos' => 'Winger Kiri (LWF)',
                'foot' => 'right',
                'height' => 165,
                'weight' => 54,
                'club' => 'SSB Jayakarta Muda',
                'exp' => 'Kapten Tim Juara 1 Liga Kompas Gramedia U-14 2025',
                'grade' => 'A',
                'stars' => 5,
                'tags' => ['Vision', 'First Touch', 'Leadership', 'Through Pass'],
                'notes' => 'Visi bermain luar biasa, first touch tenang di bawah tekanan, memiliki kepemimpinan tinggi.',
                'stage_status' => 'day_1_passed',
            ],
            [
                'name' => 'Bagas Arya Pratama',
                'nick' => 'Bagas',
                'nik' => '3174051808120002',
                'birth' => '2012-08-18',
                'pos' => 'Center Forward (CF)',
                'sec_pos' => 'Second Striker (SS)',
                'foot' => 'right',
                'height' => 172,
                'weight' => 61,
                'club' => 'Akademi Persija Muda Bojongsari',
                'exp' => 'Top Skorer Piala Soeratin U-13 DKI Jakarta 2025 (14 Gol)',
                'grade' => 'A',
                'stars' => 5,
                'tags' => ['Finishing', 'Speed', 'Heading', 'Off-ball Run'],
                'notes' => 'Naluri gol tajam, penempatan posisi cerdas, duel udara sangat tangguh.',
                'stage_status' => 'day_1_passed',
            ],
            [
                'name' => 'Dimas Farhan Ramadhan',
                'nick' => 'Farhan',
                'nik' => '3276010403130003',
                'birth' => '2013-03-04',
                'pos' => 'Goalkeeper (GK)',
                'sec_pos' => 'Goalkeeper (GK)',
                'foot' => 'right',
                'height' => 178,
                'weight' => 66,
                'club' => 'SSB Bina Taruna Rawamangun',
                'exp' => 'Kiper Terbaik Danone Nations Cup Regional Jabodetabek',
                'grade' => 'A',
                'stars' => 5,
                'tags' => ['Reflexes', 'Handling', 'Distribution', 'Aerial Command'],
                'notes' => 'Refleks cepat, jangkauan tangkapan aman, berani memotong umpan silang.',
                'stage_status' => 'day_1_passed',
            ],
            [
                'name' => 'Alvaro Dwi Kurniawan',
                'nick' => 'Alvaro',
                'nik' => '3172082211120004',
                'birth' => '2012-11-22',
                'pos' => 'Center Back (CB)',
                'sec_pos' => 'Defensive Midfielder (DMF)',
                'foot' => 'both',
                'height' => 174,
                'weight' => 63,
                'club' => 'SSB ASIOP Apacinti',
                'exp' => 'Bek Inti Piala Menpora U-14 2025',
                'grade' => 'A',
                'stars' => 4,
                'tags' => ['Tackling', 'Interception', 'Strength', 'Aerial Duel'],
                'notes' => 'Tekel bersih tanpa pelanggaran ceroboh, build-up dari lini belakang rapi.',
                'stage_status' => 'day_1_passed',
            ],
            [
                'name' => 'Rizky Mahendra Putra',
                'nick' => 'Rizky',
                'nik' => '3275031501130005',
                'birth' => '2013-01-15',
                'pos' => 'Left Winger (LWF)',
                'sec_pos' => 'Left Back (LB)',
                'foot' => 'left',
                'height' => 163,
                'weight' => 52,
                'club' => 'SSB Macan Kemayoran',
                'exp' => 'Pemain Terbaik Festival Grassroots PSSI Persija 2024',
                'grade' => 'B',
                'stars' => 4,
                'tags' => ['Dribbling', 'Pace', 'Crossing', 'Workrate'],
                'notes' => 'Akselerasi sprint eksplosif, umpan silang akurat menggunakan kaki kiri murni.',
                'stage_status' => 'stage_1',
            ],
            [
                'name' => 'Kenzo Rafael Siregar',
                'nick' => 'Kenzo',
                'nik' => '3173090909120006',
                'birth' => '2012-09-09',
                'pos' => 'Central Midfielder (CMF)',
                'sec_pos' => 'Defensive Midfielder (DMF)',
                'foot' => 'right',
                'height' => 167,
                'weight' => 57,
                'club' => 'SSB Persigawa Timur',
                'exp' => 'Gelandang Inti Liga Topskor U-13 2025',
                'grade' => 'B',
                'stars' => 4,
                'tags' => ['Stamina', 'Short Pass', 'Pressing', 'Recovery'],
                'notes' => 'Daya jelajah lapangan luar biasa (box-to-box), agresif saat kehilangan bola.',
                'stage_status' => 'stage_1',
            ],
            [
                'name' => 'Fathir Ahmad Fadillah',
                'nick' => 'Fathir',
                'nik' => '3175061404130007',
                'birth' => '2013-04-14',
                'pos' => 'Right Back (RB)',
                'sec_pos' => 'Right Winger (RWF)',
                'foot' => 'right',
                'height' => 166,
                'weight' => 55,
                'club' => 'SSB Garuda Putra Bekasi',
                'exp' => 'Peringkat 2 Liga Sentra Jalur Prestasi 2025',
                'grade' => 'B',
                'stars' => 3,
                'tags' => ['Speed', 'Overlapping', 'Tackling'],
                'notes' => 'Disiplin bertahan cukup baik, overlap aktif membantu serangan sayap kanan.',
                'stage_status' => 'stage_1',
            ],
            [
                'name' => 'Danendra Satria Wibowo',
                'nick' => 'Danen',
                'nik' => '3276052807120008',
                'birth' => '2012-07-28',
                'pos' => 'Goalkeeper (GK)',
                'sec_pos' => 'Goalkeeper (GK)',
                'foot' => 'right',
                'height' => 176,
                'weight' => 64,
                'club' => 'SSB Tunas Betawi Jakarta',
                'exp' => 'Semifinalis Walikota Cup Jakarta Selatan 2024',
                'grade' => 'B',
                'stars' => 4,
                'tags' => ['Shot Stopping', 'Communication', 'Bravery'],
                'notes' => 'Komunikasi vokal mengorganisasi bek, tangguh dalam situasi 1-on-1.',
                'stage_status' => 'stage_1',
            ],
        ];

        foreach ($sampleAthletes as $index => $item) {
            $bibNumber = 101 + $index;

            // Akun orang tua / wali
            $parentEmail = 'wali.' . Str::slug($item['nick']) . '@persijadevelopment.id';
            $parentUser = User::firstOrCreate(
                ['email' => $parentEmail],
                [
                    'name' => 'Wali dari ' . $item['name'],
                    'password' => Hash::make('Wali12345'),
                    'phone' => '0812' . str_pad((string) ($bibNumber * 777), 8, '0', STR_PAD_LEFT),
                    'is_active' => true,
                ]
            );
            if (! $parentUser->hasRole('participant_parent')) {
                $parentUser->assignRole('participant_parent');
            }

            // Peserta audisi
            $participant = AuditionParticipant::updateOrCreate(
                [
                    'event_id' => $event->id,
                    'nik' => $item['nik'],
                ],
                [
                    'user_id' => $parentUser->id,
                    'registration_number' => 'AUD-2026-ESP' . str_pad((string) $bibNumber, 4, '0', STR_PAD_LEFT),
                    'bib_number' => $bibNumber,
                    'full_name' => $item['name'],
                    'nickname' => $item['nick'],
                    'birth_date' => $item['birth'],
                    'gender' => 'male',
                    'preferred_position' => $item['pos'],
                    'secondary_position' => $item['sec_pos'],
                    'dominant_foot' => $item['foot'],
                    'height_cm' => $item['height'],
                    'weight_kg' => $item['weight'],
                    'current_club_ssb' => $item['club'],
                    'playing_experience' => $item['exp'],
                    'jersey_size' => 'M',
                    'emergency_contact_name' => 'Orang Tua ' . $item['nick'],
                    'emergency_contact_phone' => $parentUser->phone,
                    'emergency_contact_relation' => 'Orang Tua Kandung',
                    'liability_accepted_at' => now(),
                    'screening_status' => 'passed',
                    'screening_notes' => 'Berkas akta kelahiran, kartu keluarga, dan rapor lengkap & terverifikasi sah.',
                    'screening_verified_by' => $officerUser->id,
                    'screening_verified_at' => now(),
                    'screening_code' => 'AUD-SPAIN-PASS-' . $bibNumber,
                    'stage_status' => $item['stage_status'],
                    'status' => 'active',
                    'slot_reserved_at' => now()->subDays(2),
                ]
            );

            // Pembayaran lunas Rp 250.000
            Payment::updateOrCreate(
                [
                    'event_id' => $event->id,
                    'audition_participant_id' => $participant->id,
                ],
                [
                    'invoice_number' => 'INV-ESP-2026-' . str_pad((string) $bibNumber, 5, '0', STR_PAD_LEFT),
                    'amount' => 250000,
                    'gateway_fee' => 0,
                    'total_amount' => 250000,
                    'method' => 'manual',
                    'payment_channel' => 'manual_transfer',
                    'status' => 'paid',
                    'paid_at' => now()->subDays(2),
                    'verified_at' => now()->subDays(2),
                ]
            );

            // Presensi Check-in Tahap 1
            AuditionAttendance::updateOrCreate(
                [
                    'audition_participant_id' => $participant->id,
                    'audition_stage_id' => $stage1->id,
                ],
                [
                    'scanned_by' => $officerUser->id,
                    'check_in_at' => Carbon::create(2026, 11, 10, 7, 45, 0),
                    'status' => 'present',
                    'notes' => 'Check-in gatekeeper sah di gerbang Lapangan Utama.',
                ]
            );

            // Penilaian Pemandu Bakat (Scouting Evaluation)
            ScoutingEvaluation::updateOrCreate(
                [
                    'audition_participant_id' => $participant->id,
                    'audition_stage_id' => $stage1->id,
                    'scout_id' => $scoutUser->id,
                ],
                [
                    'quick_grade' => $item['grade'],
                    'rating_stars' => $item['stars'],
                    'tags' => $item['tags'],
                    'coach_notes' => $item['notes'],
                    'is_locked' => false,
                ]
            );
        }
    }
}
