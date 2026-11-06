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
- [x] **1.6.1** Manajemen Pengguna Admin (`/admin/users`): Tambah, edit, aktif/nonaktifkan akun panitia, penetapan peran (`superadmin`, `finance`, `screening_officer`), dan tampilan **Card List View** responsif bebas himpitan kolom data.
- [x] **1.6.2** Matriks Peran & Izin Spatie (`/admin/roles`): Super Admin dapat menyesuaikan checkbox permission untuk setiap peran panitia.
- [x] **1.6.3** Pengaturan Branding Global (`/admin/settings`): Upload logo web, favicon, nama situs, tagline, opsi gaya logo header (`logo_only` landscape memanjang kesamping tanpa teks nama vs `logo_and_text`), dan footer copyright dengan cache flushing otomatis.

### 1.7 UI/UX Refinements, Visual Editor, & Card List Views
- [x] **1.7.1** Pengurangan Jarak Header: Penyesuaian padding `PublicLayout.vue` dan `EventDetail.vue` (merapatkan jarak vertikal dengan navbar frosted glass, kondisional pembungkus flash alert).
- [x] **1.7.2** WYSIWYG / Rich Text Editor (`RichTextEditor.vue`): Format blok (H2, H3, P), tebal, miring, garis bawah, list (bullet/nomor), alignment, tautan web, dan toggle kode sumber HTML visual untuk deskripsi event pada `Admin/Events/Form.vue` serta rendering aman `v-html` dengan Tailwind Typography di `EventDetail.vue`.
- [x] **1.7.3** Integrasi Google Maps Stadion: Migrasi database kolom `venue_maps_url` pada tabel `events`, validasi URL di form admin, seeder Young Tiger Cup, dan tombol navigasi langsung `Maps ↗` pada detail event dan card list admin.
- [x] **1.7.4** Antrean Verifikasi Transfer Manual Dual Empty State (`/admin/payments/manual-verification`): Membedakan kondisi *Seluruh Bukti Transfer Telah Selesai Diverifikasi* (semua antrean tuntas diperiksa panitia) vs *Belum Ada Bukti Transfer Masuk* (belum ada manajer yang mengunggah bukti).
- [x] **1.7.5** Transformasi Seluruh Tabel Admin ke **Card List View** Responsif: Mengganti tabel kaku multi-kolom yang rawan tergencet pada `Transactions.vue`, `Teams/Index.vue`, `Events/Index.vue`, dan `Users/Index.vue` menjadi layout card list modern, proporsional, dan nyaman dibaca di desktop maupun ponsel.

### 1.8 Sinkronisasi Kuota Real-time & Tooling Operasional Panitia
- [x] **1.8.1** Engine Resync Kuota Slot (`AdminEventController::resyncSlots` & `resyncEventSlots`): Menghitung ulang slot aktif (`occupied_slots`) secara presisi dari database dan menyinkronkan status event (`open` ↔ `quota_full`).
- [x] **1.8.2** Tombol Resync Slot Antarmuka Admin: Menambahkan tombol aksi cepat *Resync Kuota* pada halaman `Admin/Teams/Index.vue` dan `Admin/Events/Index.vue` dengan indikator tanggal kedaluwarsa slot pada setiap kartu tim.
- [x] **1.8.3** Audit Keamanan & Optimasi Query Database: Pencegahan N+1 queries dengan eager loading relasi (`with(['roles', 'teams:id,user_id,name'])`, `with(['team', 'event'])`), penguatan otorisasi media privat, perlindungan rate limiting, dan hardening input sanitization.

### 1.9 Redesain Card List View & Alur Berkas Terkunci (Portal Manajer)
- [x] **1.9.1** Card List View Komprehensif Portal Manajer: Transformasi total tabel `Players.vue` dan `Officials.vue` menjadi kartu responsif bergaya Persija Liquid Glass (chip status dokumen wajib, badge posisi, NIK/NISN, nomor punggung, dan aksi cepat).
- [x] **1.9.2** Alur Tarik Pengajuan (*Recall to Draft*): Implementasi rute `manager.players.recall` (`/manager/players/{player}/recall`) agar manajer tim dapat menarik pemain yang telah diajukan kembali ke status `draft` secara resmi sebelum panitia melakukan verifikasi.
- [x] **1.9.3** Penguncian Modal & State Pratinjau: Mencegah modifikasi berkas ilegal saat pemain berstatus `submitted` atau `passed` (`isDocModalReadOnly`), tombol modal berganti menjadi "Lihat Berkas / Pratinjau", dan penambahan tombol "Lihat Detail" pada kartu pemain yang terkunci.
- [x] **1.9.4** Tombol Universal "Lihat Berkas" (*View Document*):
  - Penambahan kartu *Berkas Terunggah Saat Ini* di modal berkas pemain lengkap dengan nama berkas asli, nomor dokumen, ukuran berkas, status validasi, catatan petugas, serta tombol **"Lihat Berkas"** bergaya Persija Red-Orange dengan target rute privat.
  - Penambahan tombol **"Lihat Berkas"** pada komponen universal `FileInput.vue` saat pratinjau lokal atau `previewUrl` tersedia, serta penyembunyian otomatis tombol ganti/hapus saat mode `disabled="true"` aktif.

### 1.10 Manajemen Pengguna Panitia & Multi-Method Routing
- [x] **1.10.1** Multi-Method Update Route: Mengizinkan request `POST` dan `PUT` pada route `admin.users.update` (`/admin/users/{user}/update`) untuk kompatibilitas penuh Inertia.js dan HTTP method spoofing.
- [x] **1.10.2** Perbaikan Validasi & Form Transformation: Perbaikan destructuring validator `isValidEmail`, penambahan alias `isEmail: isValidEmail` pada `useFormValidator.js`, dan transformasi payload otomatis agar tidak mengirimkan string kosong saat mengedit pengguna tanpa mengubah kata sandi.

### 1.11 Verifikasi, Pengujian Otomatis & Build Produksi
- [x] **1.11.1** Pengujian Otomatis Komprehensif (PHPUnit): Seluruh 31 Feature & Unit Tests dengan **174 assertions** berhasil lolos 100% `PASS`.
- [x] **1.11.2** Kompilasi Aset Frontend: Berhasil di-build dengan Vite (`npm run build`) tanpa error dan siap di-deploy.

---

## Fase 2 — Tahap Lanjutan: Panel Screening Admin, Siklus Revisi, Pengesahan Skuad, Email Queue, & Operasional

- [ ] **2.1** Panel Verifikasi & Screening Dokumen Admin (`/admin/screening`):
  - [ ] **2.1.1** Dashboard antrean screening pemain per event dan per tim dengan filter status (`submitted`, `passed`, `rejected`).
  - [ ] **2.1.2** *Split-View / Side-by-Side Document Reviewer*: Memeriksa biodata pemain (Nama, NIK, NISN, kesesuaian cut-off tanggal lahir) berdampingan langsung dengan foto scan dokumen asli (Akta Kelahiran, Kartu Keluarga, KIA/Rapor) dengan tombol zoom/fullscreen.
  - [ ] **2.1.3** Validasi Dokumen Granular: Menandai keabsahan per dokumen (`Valid` / `Invalid`) dengan catatan spesifik.
  - [ ] **2.1.4** Keputusan Verifikasi: Tombol **Loloskan (Sah)** -> `passed` + `is_locked = true` otomatis; dan tombol **Tolak (Revisi)** -> wajib mengisi alasan penolakan + status `rejected` + pencatatan otomatis ke `screening_logs`.
  - [ ] **2.1.5** Aksi Khusus Panitia: Fitur *Unlock Player* (Buka Kunci Pemain) untuk koreksi darurat oleh Superadmin/Screening Officer.
- [ ] **2.2** Siklus Revisi Terarah Manajer Tim (*Selective Re-submission*):
  - [ ] **2.2.1** Tampilan penanda khusus pada pemain yang berstatus `rejected` beserta catatan spesifik penolakan dari panitia.
  - [ ] **2.2.2** Formulir perbaikan berkas terisolasi (hanya pemain `rejected` yang dapat diedit dan diunggah ulang berkasnya).
  - [ ] **2.2.3** Pengajuan ulang (*Re-submit*) hanya untuk pemain yang telah diperbaiki.
- [ ] **2.3** Validasi Skuad Final & Akreditasi Resmi (`/manager/accreditation` & `/admin/teams/{team}/accreditation`):
  - [ ] **2.3.1** Validasi kelayakan roster minimal (memeriksa batas minimal pemain per event dan minimal 2 Penjaga Gawang / `GK` yang sah).
  - [ ] **2.3.2** Penguncian permanen skuad tim (*Locked Roster*).
  - [ ] **2.3.3** Penerbitan Kode Pengesahan Digital / QR Code verifikasi pemain sah.
  - [ ] **2.3.4** Ekspor PDF *Team Roster Sheet* resmi untuk pengawas pertandingan dan *ID Card* akreditasi pemain/ofisial.
- [ ] **2.4** Antrean Email Notifikasi Latar Belakang (*Email Queue*):
  - [ ] **2.4.1** Notifikasi hasil verifikasi screening ke Manajer Tim (daftar pemain lolos vs daftar pemain butuh revisi).
  - [ ] **2.4.2** Modul pemantauan antrean email panitia (`/admin/system/email-logs`) lengkap dengan status pengiriman (`queued`, `sent`, `failed`) dan tombol kirim ulang (*Retry*).
- [ ] **2.5** Manajemen Jadwal, Bagan Pertandingan & Laporan:
  - [ ] **2.5.1** Modul jadwal & hasil pertandingan sesuai format kompetisi (`Cup`, `League`, `Group + Knockout`, `Festival`).
  - [ ] **2.5.2** Ekspor rekapitulasi data pendaftaran dan laporan keuangan per event.
