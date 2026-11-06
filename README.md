# YTEvent — Multi-Event Football Tournament & League Management Platform

Platform manajemen kompetisi sepak bola multi-event enterprise (Format Cup/Knockout, League, Group + Knockout, dan Grassroots Festival Usia Dini) yang terintegrasi penuh mulai dari pendaftaran tim anti-*race condition*, pembayaran multi-kanal (Midtrans Gateway & Transfer Manual Bank), hingga verifikasi berkas (*screening*) pemain dan akreditasi turnamen.

Sistem ini dibangun dengan filosofi **High-Density Operational Utility** dipadukan dengan estetika **Liquid Glass Persija Red-Orange** (`#EA580C` & `#DC2626`).

---

## 🚀 Ringkasan Capaian Fase 1 (Selesai & Terverifikasi)

### 1. Engine Pendaftaran Tim Anti-Race Condition
- **Pessimistic Row Locking (`SELECT ... FOR UPDATE`)**: Menjamin penghitungan kuota slot `occupied_slots` dilakukan secara atomik di dalam `DB::transaction()` untuk mencegah *over-booking* pada detik-detik terakhir kuota turnamen.
- **Lazy Expiration Sweep & Scheduler**: Otomatis membersihkan slot pendaftaran yang tagihan `unpaid`-nya melewati batas waktu (`slot_expires_at`), serta fitur **Reclaim Slot** bagi manajer untuk mengaktifkan kembali slot yang sempat kedaluwarsa.
- **Engine Resync Kuota Real-time**: Endpoint dan tombol aksi cepat di Admin (`admin/teams` dan `admin/events`) untuk menyinkronkan status ketersediaan slot (`open` ↔ `quota_full`) terhadap transaksi aktif.

### 2. Multi-Kanal Pembayaran & Verifikasi Finance
- **Mode Pembayaran Eksklusif**: Penegakan ketat di backend antara mode `manual` (transfer rekening bank resmi BCA PT Persija Jakarta Hebat) dan mode `midtrans` (Snap Token & Webhook SHA-512 signature).
- **Portal Verifikasi Pembayaran Manual (`/admin/payments/manual-verification`)**: Modal interaktif `PaymentProofModal.vue` dengan zoom/fullscreen, aksi *Approve* (membuka roster pemain) dan *Reject* (wajib alasan penolakan + masa tenggang unggah ulang), serta *Dual Empty State*.
- **Keamanan Sandbox Simulator**: Simulator terkunci otomatis di lingkungan produksi (`403 Forbidden`).

### 3. Portal Manajer Tim & Manajemen Skuad (`/manager/*`)
- **Desain Responsif Card List View**: Seluruh tabel kaku digantikan dengan kartu modular informatif (`Players.vue` & `Officials.vue`) yang nyaman digunakan di perangkat desktop maupun ponsel.
- **Validasi Ketat Sepak Bola**:
  - *Age Cut-Off Filter*: Penolakan otomatis pemain yang tanggal lahirnya tidak sesuai kategori usia turnamen.
  - *NIK Duplicate Lock*: Pencegahan pendaftaran NIK 16 digit yang sama pada dua tim berbeda di event yang sama.
  - Batas kuota pemain (`min_players` s/d `max_players`) dan nomor punggung unik (`1–99`).
- **Komponen Universal FileInput & Berkas Privat**:
  - Validasi reaktif client & server (JPG, PNG, PDF maks 2 MB).
  - Tampilan kartu *Berkas Terunggah Saat Ini* dengan tombol langsung **Lihat Berkas** (*View Document*) yang terhubung ke rute media privat.
- **Alur Tarik Pengajuan (*Recall to Draft*)**:
  - Saat pemain berstatus `submitted` (diajukan), form dan berkas dikunci demi menjaga integritas data selama antrean panitia.
  - Manajer dapat menekan tombol **Tarik Pengajuan** (`/manager/players/{player}/recall`) untuk mengembalikan pemain ke `draft` jika ingin merevisi data sebelum panitia memverifikasi.

### 4. Portal Administrasi Panitia & Keamanan RBAC (`/admin/*`)
- **Manajemen Akun Panitia & Manajer (`/admin/users`)**: Tampilan Card List View dengan dukungan multi-method `POST` dan `PUT`, reset kata sandi, pembekuan status akun (`is_active`), dan pencegahan crash validasi email.
- **Matriks Hak Akses Spatie (`/admin/roles`)**: 4 peran bawaan (`superadmin`, `finance`, `screening_officer`, `team_manager`) dengan kontrol izin granular.
- **Pengaturan Global Branding (`/admin/settings`)**: Upload logo web, favicon, nama platform, opsi gaya logo header, dan pengaturan rekening bank panitia.
- **Visual Rich-Text Editor**: Integrasi editor deskripsi event dengan format blok, list, dan link web, serta integrasi Google Maps lokasi stadion (`venue_maps_url`).

---

## ⚡ Kemajuan Fase 2 — Screening Pemain, Siklus Revisi, Akreditasi & Antrean Email (Milestone 2.1, 2.2, 2.3, 2.4 Selesai)

### 1. Panel Verifikasi & Screening Dokumen Admin (`/admin/screening`)
- **Dashboard Antrean Tim (`Admin/Screening/Index.vue`)**: Card list view tim-tim aktif dengan progress bar pemeriksaan berkas (`X / Y Pemain Lolos`), filter event & status, serta indikator antrean di sidebar panitia.
- **Split-View Reviewer (`Admin/Screening/TeamReview.vue`)**:
  - *Panel Kiri*: Biodata pemain, pas foto resmi, nomor punggung, posisi, NIK, NISN, histori log screening, serta **Age Cut-off Check Otomatis** terhadap batas kelahiran resmi turnamen.
  - *Panel Kanan*: Viewer dokumen scan asli (Akta, KK, KIA/Rapor) dengan verifikasi per berkas (`Dokumen Sah` / `Butuh Revisi`).
  - *Sticky Decision Bar*: Tombol **"Loloskan Pemain (Sah)"** (dilengkapi proteksi otomatis jika usia tidak lolos atau ada berkas bermasalah) dan **"Tolak / Minta Revisi"** (wajib mengisi alasan penolakan minimal 10 karakter).
  - *Emergency Unlock*: Mekanisme buka kunci pemain sah jika terjadi kekeliruan panitia.

### 2. Siklus Revisi Terarah di Portal Manajer Tim (*Selective Re-submission*)
- **Highlight Tegas Pemain Ditolak**: Kartu pemain `rejected` di `Manager/Players.vue` diberi border merah tegas, status badge, dan banner memuat catatan spesifik petugas screening.
- **Perbaikan Khusus & Proteksi Pemain Sah**: Form edit dan upload berkas HANYA terbuka untuk pemain yang berstatus `rejected` atau `draft`. Pemain yang sudah sah (`passed`) tetap terkunci aman.
- **Ajukan Ulang Perbaikan**: Tombol *Ajukan Ulang Perbaikan* menyinkronkan kembali status pemain ke `submitted` dan status tim ke `in_review`.
- **Penghapusan Pemain Ditolak**: Manajer diizinkan menghapus pemain ditolak apabila pemain tersebut permanen tidak memenuhi batas usia agar dapat diganti pemain lain.

### 3. Komponen DocumentViewerModal & Standar Icon SVG
- **Modal View Terintegrasi (`DocumentViewerModal.vue`)**: Pratinjau berkas gambar (dengan kontrol Zoom In/Out, Reset 1:1, dan Rotasi 90°) serta dokumen PDF (dengan embedded iframe responsif) langsung di dalam modal tanpa perlu meninggalkan halaman.
- **Penghapusan Total Emoticon Emoji**: Seluruh simbol emoji (jam pasir, centang, silang) dihapus total dan distandarkan menggunakan Heroicons SVG.
- **Best-Practice Decision Rules**: Penegakan aturan tombol (disable state, form processing spinner, dan informative hover tooltips).

### 4. Validasi Skuad Final & Akreditasi Resmi (`/manager/accreditation`)
- **Portal Akreditasi Manajer (`Manager/Accreditation/Index.vue`)**: Checklist kelayakan skuad (minimal kuota pemain, minimal 2 GK lolos, ofisial terdaftar, bebas antrean pending).
- **Aksi Penguncian Skuad Final (*Lock Roster*)**: Dialog konfirmasi penguncian permanen (`status = 'locked_roster'`), mengisi `roster_locked_at`, dan menutup akses edit skuad.
- **Generator PDF Team Roster Sheet**: Lembar album pengesahan wasit landscape A4 memuat logo tim, identitas ofisial, kisi foto pemain, nomor punggung, TTL, NIK, dan Kode QR verifikasi digital.
- **Generator PDF ID Cards**: Kartu lanyard turnamen portrait A4 (layout 4 kartu per halaman siap cetak dengan garis potong putus-putus).
- **Pemindaian QR Code Publik Lapangan (`/verify/player/{code}`)**: Halaman publik verifikasi instan untuk wasit/match commissioner di pinggir lapangan dengan sensor NIK parsial dan streaming foto pemain.

### 5. Antrean Notifikasi Email Transaksional & Audit Logs (`/admin/system/email-logs`)
- **Antrean Latar Belakang Fail-Safe (`SendLoggedEmailJob.php`)**: Seluruh email transaksional dicatat ke tabel `email_notification_logs` dengan payload terserialisasi sebelum dikirim via queue worker, menjamin kegagalan koneksi email tidak memutus proses pendaftaran atau verifikasi.
- **Mailable Otomatis**:
  - `ScreeningResultMail`: Rekapitulasi otomatis hasil screening pemain sah vs pemain revisi yang dikirimkan saat panitia menuntaskan review tim.
  - `RosterLockedMail`: Konfirmasi skuad terkunci resmi dan akreditasi turnamen siap diunduh.
- **Panel Monitoring & Retry Admin (`Admin/EmailLogs/Index.vue`)**: Pemantauan status email (`sent`, `queued`, `failed`), pencarian penerima, filter jenis mailable & event, tombol kirim ulang per email (*retry*), serta tombol *Bulk Retry* seluruh email gagal.

---

## 🛠️ Tech Stack & Konvensi Arsitektur

- **Backend:** Laravel 11.x (PHP 8.3+)
- **Frontend:** Vue 3 (Composition API `<script setup>`) via Inertia.js
- **Styling:** Tailwind CSS (Custom Theme *Persija Red-Orange Liquid Glass*)
- **Routing:** Ziggy (`route('name')`)
- **Database:** MySQL 8.0+ (Strict Mode, InnoDB, Foreign Key Constraints & Composite Indexes)
- **Authorization:** `spatie/laravel-permission` + Laravel Policies
- **Form Handling:** Inertia `useForm()` + Composable `useFormValidator.js` (Form selalu `<form novalidate>`)
- **PDF & QR Code:** `barryvdh/laravel-dompdf` & `simplesoftwareio/simple-qrcode`
- **Testing:** PHPUnit (57 Test Cases, 348 Assertions — 100% PASS)

---

## 📂 Struktur Dokumentasi Proyek (`docs/`)

| File Dokumen | Deskripsi & Cakupan |
| :--- | :--- |
| [PROGRESS_TASKS.md](docs/PROGRESS_TASKS.md) | Catatan progres pengerjaan Fase 1 dan rincian breakdown rencana kerja Fase 2. |
| [PRD.md](docs/PRD.md) | Dokumen Kebutuhan Produk (Fitur Publik, Kuota Anti-Race, Pembayaran, Regulasi). |
| [FLOW.md](docs/FLOW.md) | Arsitektur end-to-end, alur data, transisi status (*State Machine*), dan siklus revisi. |
| [DATABASE_SCHEMA.md](docs/DATABASE_SCHEMA.md) | Kamus data MySQL, skema tabel, tipe data, indeks, dan relasi *Foreign Keys*. |
| [FOOTBALL_OPERATIONS.md](docs/FOOTBALL_OPERATIONS.md) | Regulasi sepak bola operasional (Format turnamen, batas usia, NIK lock, komposisi tim). |
| [RBAC_SECURITY_MATRIX.md](docs/RBAC_SECURITY_MATRIX.md) | Peta rute URL, matriks izin Spatie per peran, dan aturan otorisasi Policy. |
| [UI_UX.md](docs/UI_UX.md) | Standar desain visual *Persija Liquid Glass*, palet warna, tipografi, dan larangan wajib (anti-dot, anti-pulse). |
| [VALIDATION_AND_ERRORS.md](docs/VALIDATION_AND_ERRORS.md) | Standar penanganan validasi dua arah (frontend composable + backend FormRequest). |
| [PAYMENT_AND_QUEUE.md](docs/PAYMENT_AND_QUEUE.md) | Arsitektur pembayaran Midtrans/Manual dan antrean email latar belakang (*Queue*). |

---

## 🚦 Menjalankan Proyek Secara Lokal

### 1. Kebutuhan Lingkungan
- PHP `>= 8.2` (Disarankan PHP 8.3)
- Composer `>= 2.x`
- Node.js `>= 18.x` & NPM
- MySQL `>= 8.0`

### 2. Instalasi & Setup Database
```bash
# Clone repositori
git clone https://github.com/kadalmeinside/ytevent.git
cd ytevent

# Install dependensi backend & frontend
composer install
npm install

# Buat file konfigurasi environment
cp .env.example .env
php artisan key:generate

# Konfigurasi database di file .env lalu jalankan migrasi & seeder
php artisan migrate --seed

# Buat symbolic link storage
php artisan storage:link
```

### 3. Akun Bawaan Seeder
- **Superadmin:** `superadmin@persijadevelopment.id` / `Password123!`
- **Finance Officer:** `finance@persijadevelopment.id` / `Password123!`
- **Screening Officer:** `screening@persijadevelopment.id` / `Password123!`

### 4. Menjalankan Server & Build Asset
```bash
# Terminal 1: Web server Laravel
php artisan serve

# Terminal 2: Vite dev server (atau compile untuk produksi)
npm run dev
# atau untuk build produksi:
npm run build
```

### 5. Menjalankan Automated Test Suite
```bash
# Menjalankan seluruh 31 Feature & Unit Tests (174 Assertions)
php artisan test
```

---

## 🎯 Rencana Fase 2 (Next Steps)

1. **Panel Screening Admin (`/admin/screening`)**:
   - Dashboard antrean verifikasi berkas pemain per turnamen dan per tim.
   - *Split-View Document Reviewer* (mengecek kesesuaian data NIK & umur berdampingan dengan foto akta/KK asli).
   - Validasi keabsahan dokumen per berkas dan tombol keputusan (*Loloskan* / *Tolak dengan Catatan*).
   - Fitur *Unlock Player* darurat untuk Superadmin/Screening Officer.
2. **Siklus Revisi Terarah (*Selective Re-submission*)**:
   - Manajer tim hanya memperbaiki berkas dan data pemain yang berstatus `rejected`.
3. **Akreditasi Tim & Pengesahan Skuad (`/manager/accreditation`)**:
   - Validasi kelayakan skuad final (minimal pemain & minimal 2 GK sah).
   - Penerbitan Kode QR pengesahan digital dan unduh PDF resmi *Team Roster Sheet* & *ID Card* pemain/ofisial untuk pemeriksaan wasit di lapangan.
4. **Antrean Notifikasi Email (*Email Queue*)**:
   - Pengiriman otomatis hasil verifikasi screening ke email manajer dan pemantauan antrean di `/admin/system/email-logs`.
5. **Modul Jadwal & Hasil Pertandingan**:
   - Bagan gugur, jadwal fase grup, dan klasemen liga otomatis.
