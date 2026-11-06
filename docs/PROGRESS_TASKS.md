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
- [x] **1.1.5** Implementasi Design System (`UI_UX.md` — *Persija Red-Orange Liquid Glass*, aksen `#EA580C` & `#DC2626`, font tabular `JetBrains Mono`, larangan total dot/pulse/ping, komponen berkas kustom `FileInput.vue`) & sistem validasi kustom tanpa HTML5 (`novalidate`, `useFormValidator.js`, `<InputError />`, *Frosted Rose Error Banner*).
- [x] **1.1.6** Pembuatan Database Seeder non-destruktif (`RolePermissionSeeder` tanpa menimpa password yang sudah diubah, akun awal `superadmin`, `finance`, dan `screening_officer`, `SystemSettingSeeder` dengan default branding Persija Development & rekening BCA PT Persija Jakarta Hebat KCP Pamulang, serta `EventSeeder` Young Tiger Cup U-12 dari poster flyer).

### 1.2 Autentikasi, Keamanan Akses, & Manajemen Event (`/login`, `/admin`, `/admin/events`)
- [x] **1.2.1** Autentikasi `/login` (Manajer Tim) & gerbang `/admin` -> `/admin/login` (Superadmin, Finance, & Screening Officer) dengan Rate Limiting (5x/menit), Session Regeneration, dan pengalihan berbasis role.
- [x] **1.2.2** Implementasi Laravel Policies (`EventPolicy`, `TeamPolicy`, `PaymentPolicy`, `PlayerPolicy`) untuk isolasi data ketat antar-tim (`403 Forbidden`).
- [x] **1.2.3** Dashboard & CRUD Manajemen Event oleh Superadmin (`/admin/dashboard`, `/admin/events`) termasuk upload banner event, kompresi gambar client & server side, pengaturan `max_teams`, validasi larangan menurunkan kuota di bawah `occupied_slots`, batas tanggal lahir kategori usia, dokumen wajib, biaya pendaftaran, dan batas waktu pembayaran.

### 1.3 Portal Publik & Engine Pendaftaran Tim Anti-Race Condition (`/`, `/events/{slug}`, `/events/{slug}/register`)
- [x] **1.3.1** Global Landing Page (`/`) dengan tema Persija, filter format kompetisi, kategori usia, status kuota (`Open` / `Quota Full`), statistik platform, dan pencarian cepat.
- [x] **1.3.2** Halaman Detail Event (`/events/{slug}`) menampilkan banner resmi poster Young Tiger Cup, sisa slot kuota *real-time* (`occupied_slots / max_teams`), unduhan regulasi, daftar tim terdaftar (hanya menampilkan tim yang sudah berstatus lunas/terverifikasi aktif), dan tombol `Daftar Sekarang` yang terkunci saat `quota_full`.
- [x] **1.3.3** Form Pendaftaran Tim (`/events/{slug}/register`) dengan `FileInput.vue`, kompresi logo otomatis, validasi dua arah (`useFormValidator.js` + `RegisterTeamRequest` Bahasa Indonesia) & proteksi anti *double-submit*.
- [x] **1.3.4** Implementasi `TeamRegistrationService` dengan `DB::transaction()` + `Event::whereKey($id)->lockForUpdate()`, *Lazy Expiration Sweep*, *Temporary Slot Hold* (`pending_payment`), sinkronisasi otomatis `open` ↔ `quota_full`, penerbitan invoice `INV-YYYY-...`, dan *auto-login* ke `/manager/payments`.
- [x] **1.3.5** Command terjadwal `app:expire-unpaid-registrations` (pelepas slot kadaluwarsa otomatis) dan fitur **Ajukan Ulang Slot (*Reclaim Slot*)** pada dashboard manajer.

### 1.4 Penerimaan & Verifikasi Pembayaran Pendaftaran Tim (`/manager/payments` & `/admin/payments/*`)
- [x] **1.4.1** Pengaturan Global Pembayaran di Super Admin (`/admin/settings`): pemilihan metode eksklusif (`manual` vs `midtrans`), multi-rekening transfer bank, dan aturan MDR channel (VA flat, QRIS %, E-Wallet %, Kartu Kredit %).
- [x] **1.4.2** Halaman Pembayaran Manajer Tim (`/manager/payments`) yang adaptif sesuai mode aktif: kartu multi-rekening resmi (BCA 473-5499999) + tombol salin instan + upload bukti transfer via `FileInput.vue` untuk mode manual; kartu kanal pembayaran + simulasi MDR dinamis untuk mode Midtrans.
- [x] **1.4.3** Penegakan Ketat Mode Pembayaran di Backend (`PaymentService` & `PaymentController`): memblokir inisiasi gateway saat mode manual aktif, dan memblokir upload bukti transfer saat mode gateway aktif.
- [x] **1.4.4** Pengamanan Simulator Sandbox: simulator dimatikan secara default (`PAYMENT_SANDBOX_SIMULATOR=false`), dinonaktifkan paksa di `APP_ENV=production`, dan mengembalikan `403 Forbidden` jika dipanggil tanpa izin.
- [x] **1.4.5** Integrasi kredensial Midtrans dinamis dari database `system_settings` dengan fallback ke `.env` untuk pembuatan Snap token maupun verifikasi signature webhook SHA-512.
- [x] **1.4.6** Alur Upload Bukti Transfer Manual (`UploadPaymentProofRequest`, penyimpanan ke *Private Disk* + rute baca terotorisasi, status `waiting_verification` penahan slot).
- [x] **1.4.7** Portal Verifikasi Pembayaran Finance & Superadmin (`/admin/payments/manual-verification` & `/admin/payments/transactions`) dengan pratinjau modal bukti transfer `PaymentProofModal.vue`, aksi **Approve** (`paid` -> buka akses pemain), dan aksi **Reject** (wajib alasan min. 10 karakter + *re-upload grace period*).

### 1.5 Kelengkapan Nama Pemain, Ofisial, & Dokumen di Portal Manajer (`/manager/players`, `/manager/officials`)
- [x] **1.5.1** Dashboard Manajer Tim (`/manager/dashboard` & `/manager/team-profile`) beserta gerbang kunci akses daftar pemain sebelum lunas (`lock_roster_until_paid`).
- [x] **1.5.2** CRUD Biodata Pemain (`/manager/players`) dengan `StorePlayerRequest` / `UpdatePlayerRequest` dan `FileInput.vue`: Nama Lengkap, Nama Punggung, Nomor Punggung unik per tim (`1–99`), Posisi (`GK/DF/MF/FW`), Tanggal Lahir (*Age Cut-Off Filter* otomatis), NIK 16 digit unik per event (*NIK Duplicate Lock*), NISN, Tinggi/Berat, Pas Foto resmi dengan kompresi browser, dan batas `max_players_per_team`.
- [x] **1.5.3** Unggah & Pratinjau Aman Dokumen Kelengkapan Pemain (`player_documents` di *Private Storage*: Akta Kelahiran, KK, KIA/Rapor/Ijazah, KTP sesuai syarat event) serta aksi **Ajukan Screening** (`draft` -> `submitted`).
- [x] **1.5.4** Pengelolaan Data Pelatih Kepala & Ofisial Tim (`/manager/officials`) beserta unggahan lisensi via `FileInput.vue`.

### 1.6 Pengaturan Global, Manajemen Pengguna & Hak Akses (`/admin/settings`, `/admin/users`, `/admin/roles`)
- [x] **1.6.1** Manajemen Pengguna Admin (`/admin/users`): Tambah, edit, aktif/nonaktifkan akun panitia, serta penetapan peran (`superadmin`, `finance`, `screening_officer`).
- [x] **1.6.2** Matriks Peran & Izin Spatie (`/admin/roles`): Super Admin dapat menyesuaikan checkbox permission untuk setiap peran panitia.
- [x] **1.6.3** Pengaturan Branding Global (`/admin/settings`): Upload logo web, favicon, nama situs, tagline, opsi gaya logo header (`logo_only` landscape memanjang kesamping tanpa teks nama vs `logo_and_text`), dan footer copyright dengan cache flushing otomatis.

### 1.7 Verifikasi & Automated Testing Fase 1
- [x] **1.7.1** Penulisan dan eksekusi Feature Tests: 28 test cases (130 assertions) lulus 100% (kuota & anti-race `lockForUpdate`, lazy/scheduled expiration, penegakan mode pembayaran manual vs midtrans, proteksi sandbox simulator, akses login screening officer, validasi NIK ganda & umur pemain, dokumen privat, serta otorisasi Policy `403 Forbidden`).
- [x] **1.7.2** Build aset produksi (`npm run build`) & kompilasi siap Shared Hosting.

---

## Fase 2 — Tahap Lanjutan: Panel Screening Admin, Siklus Revisi, Pengesahan Skuad, Email Queue, & Operasional

- [ ] **2.1** Panel Verifikasi & Screening Dokumen Admin (`/admin/screening`) dengan *Split-View Document Viewer* (Zoom/Rotate), keputusan `passed` (`is_locked = true`) / `rejected` (wajib alasan), `screening_logs`, dan *Unlock Player*.
- [ ] **2.2** Siklus Revisi Terarah Manajer Tim (*Selective Re-submission* hanya untuk pemain/berkas `rejected`).
- [ ] **2.3** Validasi Skuad Final (min. pemain & min. 2 `GK`), penguncian `locked_roster`, QR pengesahan pemain, serta unduh PDF *Team Roster Sheet* & *ID Card* (`/manager/accreditation`).
- [ ] **2.4** Implementasi penuh 5 Mailable Antrean Latar Belakang (`ShouldQueue`, `$tries = 3`, `$backoff = [10, 30, 60]`) dan pemantauan `/admin/system/email-logs` (*Retry*).
- [ ] **2.5** Manajemen User & Matriks Role-Permission Dinamis (`/admin/system/*`), ekspor laporan keuangan per event, dan modul jadwal/bagan pertandingan (`Cup`, `League`, `Group + Knockout`, `Festival`).
