# PROGRESS_TASKS.md - Pelacak Progres Implementasi (Fase 1 & Fase 2)

Dokumen ini mencatat progres pengerjaan **Multi-Event Football Tournament & League Platform** secara *real-time* berdasarkan rencana implementasi 2 fase.

Legenda Status:
- `[ ]` Belum dikerjakan
- `[~]` Sedang dikerjakan
- `[x]` Selesai & Terverifikasi

---

## Fase 1 — Target Hari Ini: Core Platform, Pendaftaran Anti-Race, Pembayaran Tim, & Input Pemain + Dokumen

### 1.1 Fondasi Proyek, Skema Database, & Standar UI/Validasi
- [x] **1.1.1** Inisialisasi proyek Laravel 11 (PHP 8.3) + Inertia.js + Vue 3 (`<script setup>`) + Tailwind CSS + `spatie/laravel-permission` + `tightenco/ziggy`.
- [x] **1.1.2** Konfigurasi `HandleInertiaRequests` (pembagian state `auth.user`, `roles`, `permissions`, dan `flash` ke Vue).
- [x] **1.1.3** Pembuatan seluruh migrasi database & indexing sesuai `DATABASE_SCHEMA.md` (`users`, Spatie RBAC, `events`, `teams`, `payments`, `players`, `player_documents`, `team_officials`, `screening_logs`, `email_notification_logs`).
- [x] **1.1.4** Pembuatan Eloquent Models beserta relasi, cast, *soft deletes*, dan helper perhitungan kuota (`occupied_slots`).
- [x] **1.1.5** Implementasi Design System (`UI_UX.md` — *Light Enterprise Frosted Glass*, `bg-slate-50`, aksen `emerald-700`, font tabular `JetBrains Mono`, Badge Status tanpa dot/pulse) & sistem validasi kustom tanpa HTML5 (`novalidate`, `useFormValidator.js`, `<InputError />`, *Frosted Rose Error Banner*).
- [x] **1.1.6** Pembuatan Database Seeder (`RolePermissionSeeder`, akun awal `superadmin` & `finance`, serta sampel multi-event `EventSeeder`).

### 1.2 Autentikasi, Keamanan Akses, & Manajemen Event (`/login`, `/admin`, `/admin/events`)
- [x] **1.2.1** Autentikasi `/login` (Manajer Tim) & gerbang `/admin` -> `/admin/login` (Superadmin & Finance) dengan Rate Limiting (5x/menit), Session Regeneration, dan pengalihan berbasis role.
- [x] **1.2.2** Implementasi Laravel Policies (`EventPolicy`, `TeamPolicy`, `PaymentPolicy`, `PlayerPolicy`) untuk isolasi data ketat antar-tim (`403 Forbidden`).
- [x] **1.2.3** Dashboard & CRUD Manajemen Event oleh Superadmin (`/admin/dashboard`, `/admin/events`) termasuk pengaturan `max_teams`, validasi larangan menurunkan kuota di bawah `occupied_slots`, batas tanggal lahir kategori usia, dokumen wajib, biaya pendaftaran, dan batas waktu pembayaran.

### 1.3 Portal Publik & Engine Pendaftaran Tim Anti-Race Condition (`/`, `/events/{slug}`, `/events/{slug}/register`)
- [x] **1.3.1** Global Landing Page (`/`) dengan filter format kompetisi, kategori usia, status kuota (`Open` / `Quota Full`), statistik platform, dan pencarian cepat.
- [x] **1.3.2** Halaman Detail Event (`/events/{slug}`) menampilkan sisa slot kuota *real-time* (`occupied_slots / max_teams`), unduhan regulasi, daftar tim terdaftar, dan tombol `Daftar Sekarang` yang terkunci saat `quota_full`.
- [x] **1.3.3** Form Pendaftaran Tim (`/events/{slug}/register`) dengan validasi dua arah (`useFormValidator.js` + `RegisterTeamRequest` Bahasa Indonesia) & proteksi anti *double-submit*.
- [x] **1.3.4** Implementasi `TeamRegistrationService` dengan `DB::transaction()` + `Event::whereKey($id)->lockForUpdate()`, *Lazy Expiration Sweep*, *Temporary Slot Hold* (`pending_payment`), sinkronisasi otomatis `open` ↔ `quota_full`, penerbitan invoice `INV-YYYY-...`, dan *auto-login* ke `/manager/payments`.
- [x] **1.3.5** Command terjadwal `app:expire-unpaid-registrations` (pelepas slot kadaluwarsa otomatis) dan fitur **Ajukan Ulang Slot (*Reclaim Slot*)** pada dashboard manajer.

### 1.4 Penerimaan & Verifikasi Pembayaran Pendaftaran Tim (`/manager/payments` & `/admin/payments/*`)
- [x] **1.4.1** Halaman Pembayaran Manajer Tim (`/manager/payments`) dengan rincian invoice, hitung mundur `expires_at`, pilihan kanal pembayaran otomatis (Midtrans Snap / Xendit Invoice + simulator sandbox dev) & transfer bank manual.
- [x] **1.4.2** Integrasi layanan Payment Gateway (Midtrans & Xendit) serta endpoint Webhook (`/api/webhooks/midtrans`, `/api/webhooks/xendit`) dengan validasi Signature Key, *Idempotency*, `lockForUpdate()`, aktivasi otomatis tim (`paid` -> `active`), dan proteksi *Late Settlement Over-Quota* (`refund_required`).
- [x] **1.4.3** Alur Upload Bukti Transfer Manual (`UploadPaymentProofRequest`, penyimpanan ke *Private Disk* + rute baca terotorisasi, status `waiting_verification` penahan slot).
- [x] **1.4.4** Portal Verifikasi Pembayaran Finance & Superadmin (`/admin/payments/manual-verification` & `/admin/payments/transactions`) dengan pratinjau bukti *side-by-side*, aksi **Approve** (`paid` -> buka akses pemain), dan aksi **Reject** (wajib alasan min. 10 karakter + *re-upload grace period*).

### 1.5 Kelengkapan Nama Pemain, Ofisial, & Dokumen di Portal Manajer (`/manager/players`, `/manager/officials`)
- [x] **1.5.1** Dashboard Manajer Tim (`/manager/dashboard` & `/manager/team-profile`) beserta gerbang kunci akses daftar pemain sebelum lunas (`lock_roster_until_paid`).
- [x] **1.5.2** CRUD Biodata Pemain (`/manager/players`) dengan `StorePlayerRequest` / `UpdatePlayerRequest`: Nama Lengkap, Nama Punggung, Nomor Punggung unik per tim (`1–99`), Posisi (`GK/DF/MF/FW`), Tanggal Lahir (*Age Cut-Off Filter* otomatis), NIK 16 digit unik per event (*NIK Duplicate Lock*), NISN, Tinggi/Berat, Pas Foto resmi, dan batas `max_players_per_team`.
- [x] **1.5.3** Unggah & Pratinjau Aman Dokumen Kelengkapan Pemain (`player_documents` di *Private Storage*: Akta Kelahiran, KK, KIA/Rapor/Ijazah, KTP sesuai syarat event) serta aksi **Ajukan Screening** (`draft` -> `submitted`).
- [x] **1.5.4** Pengelolaan Data Pelatih Kepala & Ofisial Tim (`/manager/officials`) beserta unggahan sertifikat lisensi.

### 1.6 Verifikasi & Automated Testing Fase 1
- [x] **1.6.1** Penulisan dan eksekusi Feature Tests (kuota maksimal & anti-race condition `lockForUpdate`, *lazy/scheduled expiration*, pembayaran manual & webhook gateway + *over-quota guard*, validasi usia & NIK ganda pemain, unggah dokumen privat, serta isolasi Policy `403 Forbidden`).
- [x] **1.6.2** Build aset produksi (`npm run build`) & pemeriksaan menyeluruh seluruh rute Fase 1 tanpa error.

---

## Fase 2 — Tahap Lanjutan: Panel Screening Admin, Siklus Revisi, Pengesahan Skuad, Email Queue, & Operasional

- [ ] **2.1** Panel Verifikasi & Screening Dokumen Admin (`/admin/screening`) dengan *Split-View Document Viewer* (Zoom/Rotate), keputusan `passed` (`is_locked = true`) / `rejected` (wajib alasan), `screening_logs`, dan *Unlock Player*.
- [ ] **2.2** Siklus Revisi Terarah Manajer Tim (*Selective Re-submission* hanya untuk pemain/berkas `rejected`).
- [ ] **2.3** Validasi Skuad Final (min. pemain & min. 2 `GK`), penguncian `locked_roster`, QR pengesahan pemain, serta unduh PDF *Team Roster Sheet* & *ID Card* (`/manager/accreditation`).
- [ ] **2.4** Implementasi penuh 5 Mailable Antrean Latar Belakang (`ShouldQueue`, `$tries = 3`, `$backoff = [10, 30, 60]`) dan pemantauan `/admin/system/email-logs` (*Retry*).
- [ ] **2.5** Manajemen User & Matriks Role-Permission Dinamis (`/admin/system/*`), ekspor laporan keuangan per event, dan modul jadwal/bagan pertandingan (`Cup`, `League`, `Group + Knockout`, `Festival`).
