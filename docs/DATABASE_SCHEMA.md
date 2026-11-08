# DATABASE_SCHEMA.md - Kamus Data & Arsitektur Database MySQL

Dokumen ini mendefinisikan struktur tabel, kolom, tipe data, kunci relasi, dan aturan indeks pada database MySQL untuk menjamin integritas data dan kecepatan pemindaian tabel berdensitas tinggi.

---

## 1. Standar Konvensi Database
- **Engine & Charset:** Seluruh tabel wajib menggunakan mesin penyimpanan transaksional InnoDB dengan karakter set UTF8MB4.
- **Audit Timestamps:** Seluruh tabel utama wajib memiliki kolom waktu pembuatan dan waktu pembaruan otomatis, serta fitur penghapusan lunak (Soft Deletes) pada tabel transaksi, tim, event, dan pemain agar histori kompetisi tidak hilang secara permanen.
- **Indexing:** Kolom yang sering digunakan untuk pencarian dan pemfilteran tabel (seperti status, nomor identitas, kode invoice, dan kunci asing) wajib diberikan indeks database.

---

## 2. Rincian Tabel Utama

### 2.1 Tabel `users` (Pengguna Sistem)
Menyimpan seluruh akun pengguna baik Superadmin, Staf Finance, maupun Manajer Tim.
- **Kolom Utama:** ID Pengguna, Nama Lengkap, Alamat Email (Unik), Nomor Telepon/WhatsApp, Kata Sandi Terenkripsi, Waktu Verifikasi Email, Status Akun Aktif/Nonaktif, Waktu Login Terakhir, serta IP Login Terakhir.

### 2.2 Tabel Standar Spatie RBAC (`roles`, `permissions`, `model_has_roles`, `role_has_permissions`)
Menyimpan struktur peran dan izin akses dinamis yang terhubung langsung ke tabel pengguna.
- **Peran Bawaan:** `superadmin`, `finance`, dan `team_manager`.

### 2.3 Tabel `events` (Master Kompetisi / Turnamen / Liga)
Menyimpan pengaturan per kompetisi yang dibuat oleh Superadmin.
- **Kolom Identitas:** ID Event, Nama Kompetisi, Slug URL (Unik), Poster/Banner Path, Deskripsi Singkat, Lokasi/Nama Stadion Utama, Tautan Google Maps Stadion (`venue_maps_url`), Kota Penyelenggaraan.
- **Kolom Format & Aturan Teknis:**
  - Format Turnamen / Match Format (`match_format` — `5_vs_5`, `7_vs_7`, `8_vs_8`, `9_vs_9`, `11_vs_11`, default `11_vs_11`).
  - Kuota Pemain Inti DSP (`starting_players_count` — Unsigned TinyInteger, menentukan jumlah tepat starter yang wajib dipilih manajer, contoh: 5, 7, 8, 9, atau 11 pemain).
  - Batas Menit Penutupan DSP (`dsp_cutoff_minutes` — Unsigned SmallInteger, default: 30 menit sebelum kick-off).
  - Status Kunci Grup Turnamen (`is_group_locked` — Boolean, mengunci hasil drawing grup agar tidak terubah saat TM).
  - Status Rilis E-Sertifikat (`certificates_released` — Boolean, gerbang kontrol rilis unduh sertifikat bagi manajer).
  - Format Kompetisi Tingkat Tinggi (`cup`, `league`, `group_knockout`, `festival`).
  - Label Kategori Usia (contoh: U-10, U-12, U-15, U-17, Senior).
  - Batas Tanggal Lahir Minimal dan Maksimal Pemain (digunakan untuk validasi otomatis usia saat input pemain).
  - Batas Minimal dan Maksimal Jumlah Pemain per Tim (contoh: Minimal 14, Maksimal 25 pemain).
  - Syarat Minimal Penjaga Gawang / Kiper per Tim (`min_gk_per_team` — `1` atau `2` GK, default `2`).
  - Batas Maksimal Kuota Ofisial/Pelatih per Tim (`max_officials_per_team`).
  - Pengaturan Lisensi Pelatih Kepala (`show_coach_license` Boolean untuk menampilkan/menyembunyikan input lisensi, serta `require_coach_license` Boolean untuk mewajibkan pengisian nomor & sertifikat lisensi pelatih kepala).
  - Daftar Jenis Dokumen Wajib (disimpan dalam format terstruktur, misal wajib Akta Lahir, KK, dan KIA).
- **Kolom Kuota & Keuangan:**
  - Kuota Maksimal Tim (`max_teams` — Unsigned Integer, menjadi acuan batas keras *Pessimistic Row Locking* `SELECT ... FOR UPDATE` pada baris `events.id`).
  - Biaya Pendaftaran (Nominal Desimal), Status Pembayaran Mengunci Input Pemain (`lock_roster_until_paid` Boolean), Batas Waktu Pembayaran Reservasi Slot (`payment_deadline_hours`, contoh: 24 jam), serta Masa Tenggang Unggah Ulang Bukti Ditolak (`reupload_grace_hours`, contoh: 6 jam).
- **Kolom Jadwal & Status:** Tanggal Buka Pendaftaran, Tanggal Tutup Pendaftaran, Tanggal Pertandingan Dimulai, Tanggal Selesai, Berkas PDF Regulasi Teknis, dan Status Event (`draft`, `open`, `quota_full`, `closed`, `ongoing`, `completed`).

### 2.4 Tabel `teams` (Data Pendaftaran Tim per Event)
Menyimpan data klub/SSB/akademi yang mendaftar pada suatu event.
- **Kolom Relasi:** ID Tim, ID Event (Foreign Key), ID User Manajer (Foreign Key).
- **Kolom Profil Tim:** Nama Tim (Constraint Unik `UNIQUE(event_id, name)`), Slug Tim, Path Berkas Logo Tim, Kota / Asosiasi Asal (Askot / Askab PSSI), Alamat Sekretariat/Markas Tim.
- **Kolom Seragam & Prestasi:** Warna Kostum Utama (`home_jersey_color`), Warna Kostum Tandang (`away_jersey_color`), Gelar Prestasi Turnamen (`achievement_title`, misal: "Juara 1 (Champions)", "Runner-up").
- **Kolom Penanggung Jawab:** Nama Manajer Tim, Nomor WhatsApp Manajer, Email Manajer, Nama Pelatih Kepala, Lisensi Pelatih Kepala (opsional di awal, dilengkapi di dashboard), Waktu Persetujuan Syarat & Ketentuan.
- **Kolom Reservasi Slot & Status Operasional:**
  - Status Pendaftaran (`pending_payment`, `active`, `locked_roster`, `expired`, `disqualified`) — Indeks komposit `INDEX(event_id, status)` untuk perhitungan cepat `occupied_slots` saat baris event dikunci.
  - Waktu Reservasi Slot (`slot_reserved_at`) dan Batas Waktu Reservasi Slot (`slot_expires_at`).
  - Status Keseluruhan Screening Tim (`not_started`, `in_review`, `partially_rejected`, `all_passed`).
  - Catatan Internal Panitia (`admin_notes`).

### 2.5 Tabel `payments` (Tagihan & Transaksi Pembayaran)
Menyimpan histori tagihan pendaftaran dan rekonsiliasi pembayaran.
- **Kolom Relasi & Identitas:** ID Pembayaran, Nomor Invoice Unik (`UNIQUE(invoice_number)`), ID Tim (Foreign Key), ID Event (Foreign Key).
- **Kolom Nilai & Metode:** Nominal Tagihan, Biaya Admin Gateway, Total Bayar, Jenis Metode (`midtrans`, `xendit`, `manual`), Saluran Pembayaran Spesifik (BCA VA, Mandiri VA, QRIS, Transfer Manual, dll).
- **Kolom Data Gateway Otomatis:** Referensi ID Transaksi Eksternal, Token Snap / Tautan Pembayaran, Waktu Kedaluwarsa Tagihan (`expires_at`), Payload Mentah Webhook (untuk audit rekonsiliasi).
- **Kolom Data Transfer Manual:** Nama Bank Pengirim, Nama Pemilik Rekening Pengirim, Nomor Rekening Pengirim, Tanggal Transfer, Path Berkas Bukti Transfer (Private Disk).
- **Kolom Verifikasi & Status:** Status Pembayaran (`unpaid`, `waiting_verification`, `paid`, `rejected`, `expired`, `refund_required`, `refunded`) — Indeks komposit `INDEX(status, expires_at)` untuk penyapuan *Lazy Expiration* dan *Scheduler*, ID User Pemeriksa (Finance/Superadmin), Alasan Penolakan Pembayaran, Waktu Pelunasan.

### 2.6 Tabel `players` (Biodata & Status Screening Pemain)
Menyimpan daftar pemain yang didaftarkan oleh Manajer Tim.
- **Kolom Relasi:** ID Pemain, ID Tim (Foreign Key), ID Event (Foreign Key).
- **Kolom Biodata & Teknis:** Nama Lengkap Pemain, Nama Punggung, Nomor Punggung (Unik dalam satu tim yang sama), Posisi Bermain (`GK`, `DF`, `MF`, `FW`), Kaki Dominan (`Right`, `Left`, `Both`), Tempat Lahir, Tanggal Lahir, Nomor Induk Kependudukan (NIK - diindeks untuk deteksi duplikasi pemain antar-tim dalam event yang sama), Nomor Induk Siswa Nasional (NISN), Tinggi Badan (cm), Berat Badan (kg), Path Pas Foto Resmi.
- **Kolom Screening & Penguncian:**
  - Status Screening (`draft`, `submitted`, `passed`, `rejected`).
  - Status Kunci Data (`is_locked` — bernilai aktif otomatis jika status `passed`).
  - Catatan Penolakan / Alasan Gagal Screening (diisi oleh Admin Verifikator).
  - ID User Verifikator (Foreign Key), Waktu Pemeriksaan Terakhir, Kode QR / Nomor Pengesahan Pemain.

### 2.7 Tabel `player_documents` (Berkas Lampiran Identitas Pemain)
Menyimpan berkas fisik hasil pindaian (scan/foto) milik masing-masing pemain.
- **Kolom Utama:** ID Dokumen, ID Pemain (Foreign Key), Jenis Dokumen (`birth_certificate`, `family_card`, `student_card_kia`, `report_card_diploma`, `national_id_ktp`, `passport`, `domicile_letter`), Nomor Dokumen Resmi, Path Penyimpanan Privat, Ukuran Berkas, Tipe MIME, Status Keabsahan Per Dokumen (`pending`, `valid`, `invalid`), Catatan Spesifik Dokumen.

### 2.8 Tabel `team_officials` (Data Pelatih & Ofisial Tim)
Menyimpan daftar staf kepelatihan dan medis yang mendampingi tim di bangku cadangan.
- **Kolom Utama:** ID Ofisial, ID Tim (Foreign Key), Nama Lengkap, Jabatan (`Head Coach`, `Assistant Coach`, `Goalkeeper Coach`, `Physiotherapist`, `Team Manager`, `Kitman`), Nomor Lisensi Kepelatihan, Path Berkas Sertifikat Lisensi, Path Pas Foto, Nomor Telepon, Status Screening Ofisial (`draft`, `submitted`, `passed`, `rejected`), Catatan Verifikasi.

### 2.9 Tabel `screening_logs` & `email_notification_logs` (Tabel Audit)
- **Screening Logs:** Mencatat riwayat perubahan status screening pemain (siapa admin yang menolak/meloloskan, kapan, dan apa alasan sebelumnya) agar manajer tidak bisa mengklaim belum diberi tahu.
- **Email Notification Logs:** Mencatat setiap antrean email yang dikirim ke Manajer Tim (Jenis Email, Alamat Tujuan, Status Pengiriman `Queued`/`Sent`/`Failed`, Waktu Kirim, dan Pesan Error jika gagal).

---

## 3. Tabel Pelaksanaan Turnamen & Hari Pertandingan (Fase 5)

### 3.1 Tabel `event_groups` (Pembagian Grup Kompetisi)
Menyimpan daftar grup (Grup A, Grup B, dst.) pada suatu event.
- **Kolom Utama:** `id`, `event_id` (Foreign Key ke `events`), `name` (misal: "Grup A"), `sort_order` (Integer, urutan tampilan grup), `timestamps`.

### 3.2 Tabel `event_group_teams` (Klasemen & Anggota Grup)
Menyimpan posisi tim dalam grup beserta kalkulasi statistik klasemen.
- **Kolom Utama:** `id`, `event_group_id` (Foreign Key ke `event_groups`), `team_id` (Foreign Key ke `teams`).
- **Kolom Statistik Laga:** `played` (Main), `won` (Menang), `drawn` (Seri), `lost` (Kalah).
- **Kolom Gol & Poin:** `goals_for` (Memasukkan), `goals_against` (Kebobolan), `goal_difference` (Selisih Gol), `points` (Total Poin).
- **Kolom Fair Play & Override:** `yellow_cards` (Total Kartu Kuning), `red_cards` (Total Kartu Merah), `manual_rank_override` (Intervensi Manual Peringkat jika ada keputusan komisi disiplin), `timestamps`.
- **Constraint Unik:** `UNIQUE(event_group_id, team_id)`.

### 3.3 Tabel `tournament_matches` (Jadwal & Hasil Pertandingan)
Menyimpan seluruh jadwal laga baik fase grup maupun fase gugur.
- **Kolom Utama:** `id`, `event_id` (Foreign Key), `event_group_id` (Foreign Key, nullable untuk fase gugur), `stage` (`group_stage`, `round_of_16`, `quarter_final`, `semi_final`, `third_place`, `final`), `match_number` (Nomor Urut Pertandingan).
- **Kolom Tim & Jadwal:** `home_team_id` (Foreign Key ke `teams`, nullable untuk placeholder bracket), `away_team_id` (Foreign Key ke `teams`, nullable), `kickoff_at` (Datetime Waktu Kick-Off), `pitch_name` (Nama Lapangan / Venue), `referee_name` (Nama Wasit).
- **Kolom Skor & Status:** `home_score` (Gol Tim Kandang), `away_score` (Gol Tim Tandang), `home_penalty_score` (Adu Penalti Kandang), `away_penalty_score` (Adu Penalti Tandang), `status` (`scheduled`, `ongoing`, `completed`, `postponed`, `cancelled`), `match_notes` (Catatan Pertandingan / Alasan Penundaan), `timestamps`.

### 3.4 Tabel `match_events` (Kejadian Spesifik Pertandingan)
Menyimpan rekaman gol, kartu kuning, dan kartu merah per pertandingan.
- **Kolom Utama:** `id`, `match_id` (Foreign Key ke `tournament_matches`), `team_id` (Foreign Key ke `teams`), `player_id` (Foreign Key ke `players`, nullable untuk gol bunuh diri pihak lawan), `event_type` (`goal`, `penalty_goal`, `own_goal`, `yellow_card`, `second_yellow_card`, `red_card`), `minute` (Menit Terjadinya Peristiwa), `notes` (Catatan), `timestamps`.

### 3.5 Tabel `match_lineups` (Daftar Susunan Pemain / DSP)
Menyimpan daftar pemain yang didaftarkan manajer untuk suatu pertandingan.
- **Kolom Utama:** `id`, `match_id` (Foreign Key ke `tournament_matches`), `team_id` (Foreign Key ke `teams`), `player_id` (Foreign Key ke `players`), `is_starting` (Boolean: `true` Pemain Inti / Starter, `false` Pemain Cadangan), `is_captain` (Boolean: Kapten Tim), `position_label` (Snapshot Label Posisi: GK, DF, MF, FW), `jersey_number` (Snapshot Nomor Punggung), `timestamps`.
- **Constraint Unik:** `UNIQUE(match_id, player_id)`.

### 3.6 Tabel `tournament_announcements` (Pengumuman Resmi Turnamen)
Menyimpan buletin pengumuman resmi dan instruksi Technical Meeting dari panitia.
- **Kolom Utama:** `id`, `event_id` (Foreign Key ke `events`), `author_id` (Foreign Key ke `users`), `title` (Judul Pengumuman), `content` (Isi Pengumuman / URL Tautan), `is_pinned` (Boolean, sematkan di atas), `published_at` (Waktu Publikasi), `timestamps`.