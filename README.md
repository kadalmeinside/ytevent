# YTEvent — Multi-Event Football Tournament, Audition & Coaching Clinic Platform

Platform manajemen kompetisi dan pembinaan sepak bola multi-event enterprise yang mendukung **Turnamen Tim** (*Cup/Knockout*, *League*, *Group + Knockout*, *Grassroots Festival*) dan **Event Individu** (*Talent Audition* multi-tahap & *Coaching Clinic*), terintegrasi penuh dari pendaftaran anti-*race condition*, pembayaran multi-kanal (**Midtrans**, **Xendit**, & **Transfer Bank Manual**), *screening* berkas, *Digital Pass (E-Tiket)*, *Gatekeeper QR Check-in*, panel penilaian *Talent Scout*, jadwal/klasemen pertandingan, hingga penerbitan E-Sertifikat.

Sistem ini dibangun dengan filosofi **High-Density Operational Utility** dipadukan dengan estetika **Liquid Glass Persija Red-Orange** (`#EA580C` & `#DC2626`).

---

## 🚀 Ringkasan Kapabilitas Utama (Fase 1 – Fase 10 Selesai & Terverifikasi)

### 1. Engine Multi-Event & Pendaftaran Anti-Race Condition
- **Dukungan Event Tim & Individu**: Mendukung kompetisi berbasis klub/SSB maupun pendaftaran perorangan (*Audition* berjenjang & *Coaching Clinic*) dengan kustomisasi *Form Presets* dan *Custom Fields* per event.
- **Pessimistic Row Locking (`SELECT ... FOR UPDATE`)**: Menjamin penghitungan kuota slot (`occupied_slots` maupun kuota sesi `event_sessions`) dilakukan secara atomik di dalam `DB::transaction()` untuk mencegah *over-booking*.
- **Lazy Expiration Sweep, Scheduler & Reclaim Slot**: Otomatis menyapu pendaftaran yang tagihannya melewati `slot_expires_at`, plus fitur klaim ulang slot jika kuota masih tersedia.
- **Guard SOP Lifecycle Event (`draft` → `open` → `quota_full` → `closed` → `ongoing` → `completed`)**: Penegakan ketat otorisasi berbasis status event (kunci otomatis roster saat `ongoing`/`completed`, penguncian setup jadwal/grup, serta pembukaan operasi lapangan di `closed`/`ongoing`).

### 2. Multi-Kanal Pembayaran, Hardening Webhook & Verifikasi Finance
- **Pemisahan Mode & Provider Tervalidasi**: Penegakan ketat backend untuk mode `manual` vs gateway (`midtrans` / `xendit` / `gateway`) dengan validasi `payment_gateway_default`, `payment_enabled_providers`, serta kelengkapan kredensial masing-masing provider.
- **Validasi Channel & Anti-Silent Fallback**: Validasi enum saluran pembayaran resmi per provider (`MIDTRANS_AVAILABLE_CHANNELS` / `XENDIT_AVAILABLE_CHANNELS`), kalkulasi biaya MDR konsisten, dan larangan menghapus `enabled_payments` secara diam-diam saat channel pilihan gagal.
- **Eksekusi HTTP Gateway di Luar DB Transaction**: Pembuatan token/invoice Midtrans & Xendit dijalankan di luar `DB::transaction()` dengan `gateway_initiation_lock` untuk mencegah lock berkepanjangan dan pembuatan invoice ganda.
- **Hardening Webhook Settlement**:
  - Verifikasi signature SHA-512 (Midtrans) dan `x-callback-token` (Xendit).
  - Validasi kesesuaian provider terhadap `payment.method` dan status `waiting_verification`.
  - Validasi nominal ketat terhadap `total_amount` (`amount_tolerance` default Rp1): menolak *underpayment* (termasuk bypass biaya admin) dan menandai *overpayment* ke status `refund_required`.
  - Guard kuota pada webhook terlambat maupun saat `approveManualPayment()`, serta kolom refund terstruktur (`refund_amount`, `refund_reference`, `refund_method`, `refund_notes`, `refunded_at`).

### 3. Portal Screening, Akreditasi, Turnamen, & Operasional Audisi
- **Portal Manajer Tim (`/manager/*`)**: Manajemen skuad responsif, *Age Cut-Off Filter*, *NIK Duplicate Lock*, penarikan pengajuan (*Recall to Draft*), siklus revisi selektif, penguncian roster final (`locked_roster`), serta unduh PDF *Team Roster Sheet* dan *ID Card* A4 (4 kartu/halaman).
- **Split-View Screening Admin (`/admin/screening`)**: Pemeriksaan biodata dan dokumen privat (`DocumentViewerModal.vue` dengan zoom & rotasi) per berkas, kewajiban status lunas sebelum lolos screening, serta *Emergency Unlock* tercatat di `audit_logs`.
- **Operasional Pertandingan (`/admin/tournaments`, `/manager/matches`)**: Drawing grup, kunci grup, generator jadwal fase grup & gugur, input skor/kartu/gol, klasemen otomatis, dan pengajuan DSP (*Lineup*) anti-pemain tersuspensi.
- **Operasional Individu (`/scout/*`, `/admin/auditions/*`, `/participant/*`)**: *Digital Pass (E-Tiket)* portrait yang mengikuti *Form Presets* event, pemindaian QR *Gatekeeper Attendance*, penilaian sesi *Talent Scout* terkunci per-scout, promosi/eliminasi tahapan dinamis (1, 2, hingga 3+ tahap), serta verifikasi QR publik dan E-Sertifikat resmi.

### 4. Portal Administrasi, Antrean Email Fail-Safe, & Keamanan RBAC
- **RBAC Granular & FormRequest**: Peran `superadmin`, `finance`, `screening_officer`, `team_manager`, `participant_parent`, dan `talent_scout` via `spatie/laravel-permission`, Laravel Policies, serta 100% validasi controller melalui `FormRequest`.
- **Antrean Email Transaksional (`SendLoggedEmailJob.php`)**: Payload email ditandatangani HMAC, dicatat di `email_notification_logs`, mendukung `PaymentConfirmedMail`, `AuditionPaymentConfirmedMail`, `PaymentProofRejectedMail`, `PaymentRefundedMail`, `ScreeningResultMail`, `RosterLockedMail`, hingga mekanisme *Retry* & *Bulk Retry* di `/admin/system/email-logs`.

---

## 🛠️ Tech Stack & Konvensi Arsitektur

- **Backend:** Laravel 11.x (PHP 8.3+)
- **Frontend:** Vue 3 (Composition API `<script setup>`) via Inertia.js
- **Styling:** Tailwind CSS (Custom Theme *Persija Red-Orange Liquid Glass*)
- **Routing:** Ziggy (`route('name')`)
- **Database:** MySQL 8.0+ / SQLite (Strict Mode, Foreign Key Constraints & Composite Indexes)
- **Authorization:** `spatie/laravel-permission` + Laravel Policies
- **Form Handling:** Inertia `useForm()` + Composable `useFormValidator.js` (`<form novalidate>`)
- **PDF & QR Code:** `barryvdh/laravel-dompdf` & `simplesoftwareio/simple-qrcode`
- **Testing:** PHPUnit (**185 Test Cases, 1,689 Assertions — 100% PASS**)

---

## 📂 Struktur Dokumentasi Proyek (`docs/`)

| File Dokumen | Deskripsi & Cakupan |
| :--- | :--- |
| [PROGRESS_TASKS.md](docs/PROGRESS_TASKS.md) | Rekam jejak progres pengerjaan bertahap dari Fase 1 hingga Fase 10 (audit SOP & payment hardening). |
| [PRD.md](docs/PRD.md) | Dokumen Kebutuhan Produk utama untuk kompetisi turnamen tim. |
| [PRD_MULTI_EVENT_INDIVIDUAL.md](docs/PRD_MULTI_EVENT_INDIVIDUAL.md) | Spesifikasi produk multi-event untuk event individu (*Audition* & *Coaching Clinic*). |
| [FLOW.md](docs/FLOW.md) | Arsitektur end-to-end, alur data, transisi status (*State Machine*), dan siklus revisi. |
| [DATABASE_SCHEMA.md](docs/DATABASE_SCHEMA.md) | Kamus data, skema tabel, tipe data, indeks, dan relasi *Foreign Keys*. |
| [FOOTBALL_OPERATIONS.md](docs/FOOTBALL_OPERATIONS.md) | Regulasi sepak bola operasional (format turnamen, batas usia, NIK lock, komposisi tim). |
| [RBAC_SECURITY_MATRIX.md](docs/RBAC_SECURITY_MATRIX.md) | Peta rute URL, matriks izin Spatie per peran, dan aturan otorisasi Policy. |
| [UI_UX.md](docs/UI_UX.md) | Standar desain visual *Persija Liquid Glass*, palet warna, tipografi, dan konvensi komponen. |
| [VALIDATION_AND_ERRORS.md](docs/VALIDATION_AND_ERRORS.md) | Standar penanganan validasi dua arah (frontend composable + backend FormRequest). |
| [PAYMENT_AND_QUEUE.md](docs/PAYMENT_AND_QUEUE.md) | Arsitektur pembayaran Midtrans/Xendit/Manual, webhook hardening, kuota, dan antrean email (*Queue*). |
| [SCALABILITY_AND_ECOSYSTEM_ROADMAP.md](docs/SCALABILITY_AND_ECOSYSTEM_ROADMAP.md) | Peta jalan skalabilitas dan pengembangan ekosistem jangka panjang. |

---

## 🚦 Menjalankan Proyek Secara Lokal

### 1. Kebutuhan Lingkungan
- PHP `>= 8.2` (Disarankan PHP 8.3)
- Composer `>= 2.x`
- Node.js `>= 18.x` & NPM
- MySQL `>= 8.0` (atau SQLite untuk pengujian cepat)

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

# Konfigurasi database & kredensial Midtrans/Xendit di file .env lalu jalankan migrasi & seeder
php artisan migrate --seed

# Buat symbolic link storage
php artisan storage:link
```

### 3. Akun Bawaan Seeder (`App\Database\Seeders`)
- **Superadmin:** `superadmin@persijadevelopment.id`
- **Finance Officer:** `finance@persijadevelopment.id`
- **Screening Officer:** `screening@persijadevelopment.id`

*(Lihat `database/seeders/` untuk konfigurasi password development lokal; di production seeder memblokir password default).*

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
# Menjalankan seluruh 185 Feature & Unit Tests (1,689 Assertions)
php artisan test
```
