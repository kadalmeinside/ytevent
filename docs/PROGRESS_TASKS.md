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

### 2.1 Panel Verifikasi & Screening Dokumen Admin (`/admin/screening`)
- [x] **2.1.1** Backend Routing & Controller (`ScreeningController.php`): Rute index antrean `/admin/screening`, rute review berkas tim `/admin/screening/events/{event}/teams/{team}`, dan rute mutasi verifikasi pemain.
- [x] **2.1.2** FormRequests & Validasi Keputusan (`VerifyPlayerRequest.php` & `VerifyDocumentRequest.php`): Validasi keputusan `passed` / `rejected`, dan kewajiban mengisi alasan penolakan minimal 10 karakter jika status `rejected`.
- [x] **2.1.3** Service Layanan Verifikasi (`ScreeningService.php`):
  - Penerbitan otomatis kode verifikasi unik `screening_code` saat pemain `passed`.
  - Penguncian data pemain `is_locked = true` saat berstatus `passed`.
  - Sinkronisasi otomatis status tim `teams.screening_status` (`in_review`, `partially_rejected`, `all_passed`).
  - Pencatatan jejak audit ke tabel `screening_logs` (admin pemeriksa, status lama, status baru, alasan, dan waktu).
  - Proteksi Idempotensi (*Idempotency Guard*) untuk mencegah duplikasi pencatatan log saat tombol diklik berulang kali.
  - Mekanisme *Unlock Player* darurat untuk membuka kunci pemain kembali ke `draft` jika terjadi kekeliruan administrasi panitia.
- [x] **2.1.4** Dashboard Antrean Screening Panitia (`Admin/Screening/Index.vue`):
  - Tampilan Card List View tim-tim peserta dengan progres pemeriksaan berkas (contoh: *"14 / 18 Pemain Selesai Diperiksa"*).
  - Filter interaktif berdasarkan Event dan Status Tim (`in_review`, `partially_rejected`, `all_passed`).
  - Indikator badge antrean notifikasi pada sidebar `AdminLayout.vue` (*Screening Pemain: [X] Menunggu*).
- [x] **2.1.5** Antarmuka Pemeriksaan Dokumen *Split-View* (`Admin/Screening/TeamReview.vue`):
  - Panel Kiri: Biodata pemain, pas foto resmi, posisi, nomor punggung, NIK, NISN, serta **Indikator Otomatis Kelayakan Tanggal Lahir** terhadap cut-off usia turnamen.
  - Panel Kanan: Viewer dokumen scan asli (Akta Lahir, Kartu Keluarga, KIA/Rapor) dari storage privat dengan kontrol zoom in/out, modal perbesar, dan tombol penilaian per berkas (`Valid` / `Revisi`).
  - Panel Bawah: Tombol aksi keputusan instan (**"Loloskan (Sah)"** dan **"Tolak (Revisi)"** dengan modal alasan penolakan) serta navigasi cepat antar pemain.

### 2.2 Siklus Revisi Terarah di Portal Manajer Tim (*Selective Re-submission*)
- [x] **2.2.1** Tampilan Visual Pemain Ditolak di `Manager/Players.vue`:
  - Highlight kartu merah/rose tegas pada pemain berstatus `rejected`.
  - Banner peringatan memuat alasan spesifik penolakan dari panitia verifikator.
- [x] **2.2.2** Formulir Perbaikan Berkas Khusus Pemain Ditolak:
  - Form edit dan tombol ganti berkas HANYA terbuka untuk pemain yang berstatus `rejected` atau `draft`.
  - Pemain yang sudah berstatus `passed` tetap terkunci aman dan tidak dapat diubah.
- [x] **2.2.3** Pengajuan Ulang Hasil Perbaikan (*Re-submit*):
  - Manajer mengajukan ulang hanya pemain yang telah diperbaiki.
  - Status pemain kembali ke `submitted` dan status tim kembali menjadi `in_review`.
- [x] **2.2.4** Standardisasi Icon SVG & Modal View Dokumen Terintegrasi:
  - Seluruh emoticon/emoji (jam pasir, centang, silang) dihapus total dan digantikan oleh Heroicons SVG terstandar.
  - Komponen `DocumentViewerModal.vue` untuk pratinjau dokumen scan gambar (zoom & rotasi) dan dokumen PDF terintegrasi langsung di aplikasi tanpa harus pindah tab.
  - Best-practice rules untuk disable state tombol screening beserta tooltip informatif.

### 2.3 Validasi Skuad Final & Akreditasi Resmi (`/manager/accreditation`)
- [x] **2.3.1** Validasi Kelayakan Roster Final (`AccreditationService.php` & `AccreditationController.php`):
  - Pengecekan syarat kuota minimal pemain per event (contoh: min. 14 pemain lolos).
  - Pengecekan syarat minimal 2 Penjaga Gawang (`GK`) sah.
  - Pengecekan seluruh ofisial terdaftar.
  - Validasi penolakan penguncian skuad jika masih ada pemain dalam antrean pemeriksaan (`submitted`).
- [x] **2.3.2** Aksi Kunci Skuad Permanen (*Lock Roster*):
  - Tombol **Kunci Skuad Final** di portal manajer dengan modal konfirmasi dan aturan disable state best practice.
  - Mengubah status tim menjadi `locked_roster`, mengisi timestamp `roster_locked_at`, dan menyinkronkan status screening menjadi `all_passed`.
  - Mengunci akses modifikasi data pemain dan ofisial secara permanen (`Team::canManageRoster()` -> `false`).
- [x] **2.3.3** Generator PDF Resmi - Album Pemain (*Team Roster Sheet*):
  - Template PDF landscape resmi turnamen berlogo Persija Development (`resources/views/pdf/team_roster_sheet.blade.php`).
  - Kisi foto seluruh pemain sah, nomor punggung, posisi, tanggal lahir, NIK, NISN, QR code verifikasi per pemain, dan tanda tangan pengesahan wasit/match commissioner.
  - Embed gambar lokal/base64 yang cepat, aman, dan tanpa ketergantungan jaringan eksternal.
- [x] **2.3.4** Generator PDF Resmi - Kartu Akreditasi (*ID Card*):
  - Template PDF portrait lanyard turnamen siap cetak (`resources/views/pdf/id_cards.blade.php`) dengan layout 4 kartu per halaman A4 dan garis potong putus-putus.
  - Memuat logo turnamen, pas foto, nomor punggung menonjol, nama, tim, posisi/jabatan, dan QR code keabsahan digital.
- [x] **2.3.5** Halaman Publik Pemindaian QR Code (`/verify/player/{code}`):
  - Halaman publik responsif ringan (`Public/PlayerVerification.vue`) untuk verifikasi keabsahan pemain digital saat wasit/pengawas pertandingan memindai QR Code di lapangan.
  - Menampilkan badge keabsahan resmi, foto pemain, detail tim, event, umur, NIK tersensor sebagian, serta informasi verifikator panitia.
  - Tampilan informatif khusus untuk kode QR yang tidak valid atau pemain yang belum disahkan.

### 2.4 Antrean Notifikasi Email (*Email Queue*) & Audit Logs
- [x] **2.4.1** Implementasi Mailable Latar Belakang & Job Transaksional (`SendLoggedEmailJob.php` & `EmailNotificationService.php`):
  - `ScreeningResultMail`: Notifikasi rekapitulasi verifikasi (daftar pemain lolos vs rejected beserta catatannya) otomatis ke manajer saat antrean pemeriksaan tim selesai.
  - `RosterLockedMail`: Notifikasi skuad terkunci resmi dan akreditasi turnamen siap diunduh saat manajer mengunci skuad final.
  - Arsitektur antrean fail-safe: Setiap pengiriman dicatat terlebih dahulu ke `email_notification_logs` dengan payload terserialisasi, dieksekusi via `SendLoggedEmailJob`, dan tidak menggagalkan proses bisnis utama jika koneksi SMTP mengalami gangguan.
- [x] **2.4.2** Modul Pemantauan Antrean Email Panitia (`/admin/system/email-logs` & `EmailLogController.php`):
  - Halaman pemantauan visual responsif Card List View dari tabel `email_notification_logs`.
  - Kartu metrik: Total Notifikasi, Berhasil Terkirim (`sent`), Dalam Antrean (`queued`), dan Gagal (`failed`).
  - Filter pencarian berbasis teks (email penerima, subjek, nama tim), filter status, filter jenis mailable, dan filter kompetisi.
  - Fitur **Kirim Ulang (*Retry*)**: Tombol retry per email gagal serta tombol **Kirim Ulang Semua (*Bulk Retry*)** dengan modal dialog konfirmasi.

### 2.5 Automated Testing & Verifikasi Fase 2
- [x] **2.5.1** Penulisan Feature Tests Screening Fase 2 (`ScreeningFlowTest.php`):
  - Pengujian hak akses antrean screening (`screening_officer` & `superadmin`).
  - Pengujian transisi status `submitted` -> `passed` (penguncian data `is_locked` & pembuatan kode akreditasi unik).
  - Pengujian transisi status `submitted` -> `rejected` (kewajiban catatan alasan penolakan minimal 10 karakter).
  - Pengujian verifikasi granular berkas dokumen (`valid` / `invalid`).
  - Pengujian pembukaan kunci darurat (*Unlock Player*) beserta pencatatan riwayat di `screening_logs`.
  - Pengujian perbaikan dan unggah ulang berkas oleh manajer untuk pemain yang ditolak (*selective re-submission*).
  - Pengujian proteksi integritas data pemain berstatus `passed` / terkunci dari modifikasi manajer.
- [x] **2.5.2** Penulisan Feature Tests Akreditasi & Roster Lock (`AccreditationTest.php`):
  - Pengujian checklist kelayakan roster pada halaman akreditasi manajer.
  - Pengujian penolakan kunci skuad jika kuota minimal pemain belum tercapai.
  - Pengujian penolakan kunci skuad jika syarat minimal 2 GK sah belum terpenuhi.
  - Pengujian penolakan kunci skuad jika belum ada ofisial terdaftar.
  - Pengujian penolakan kunci skuad jika masih ada pemain dalam antrean pemeriksaan.
  - Pengujian keberhasilan kunci skuad, transisi `locked_roster`, dan pencatatan `roster_locked_at`.
  - Pengujian pemblokiran mutasi pemain setelah skuad terkunci permanen.
  - Pengujian unduh dokumen PDF Roster Sheet dan ID Cards untuk tim yang terkunci.
  - Pengujian hak akses unduh PDF oleh Superadmin tanpa batasan status terkunci.
  - Pengujian endpoint publik pemindaian QR Code `/verify/player/{code}` dan streaming foto.
  - Pengujian tampilan tidak valid untuk kode acak atau pemain non-passed.
- [x] **2.5.3** Penulisan Feature Tests Antrean Email & Audit Logs (`EmailLogsAndQueueTest.php`):
  - Pengujian otorisasi akses pemantauan log email (`permission:email_logs.manage`).
  - Pengujian penolakan akses untuk peran tanpa izin (`403 Forbidden` / redirect).
  - Pengujian dispatch otomatis email rekapitulasi screening (`ScreeningResultMail`).
  - Pengujian dispatch otomatis email pengesahan skuad terkunci (`RosterLockedMail`).
  - Pengujian mekanisme kirim ulang (*retry*) email gagal dan transisi ke `sent`.
  - Pengujian mekanisme *bulk retry* seluruh email gagal.
  - Pengujian penolakan retry untuk email yang sudah sukses terkirim.
  - Total test suite Fase 2 awal bertambah menjadi **57 Feature & Unit Tests** dengan **349 assertions** lulus 100% `PASS`.
- [x] **2.5.4** Build produksi frontend (`npm run build`) & verifikasi zero regression (Vite build sukses tanpa error).

### 2.6 Penyempurnaan Operasional Fase 2 (Quick Document Wizard, Idempotency & Seeder)
- [x] **2.6.1** **Guard Idempotensi Audit Log Screening (`ScreeningService.php`)**:
  - Mencegah duplikasi pencatatan `screening_logs` (`passed -> passed`) apabila petugas menekan tombol verifikasi berulang kali pada pemain yang sudah berstatus `passed`.
- [x] **2.6.2** **Fallback Subjek Email Transaksional (`EmailLogController.php` & `EmailNotificationService.php`)**:
  - Memastikan kolom `subject` pada `/admin/system/email-logs` selalu terisi dan memiliki fallback otomatis ke `EmailNotificationLog::MAILABLE_LABELS` untuk data historis.
- [x] **2.6.3** **Seeder Skuad Pemain Demo (`PlayerDemoSeeder.php`)**:
  - Menyediakan seeder 11 pemain tambahan berstatus `submitted` (lengkap dengan 4 dokumen wajib & pas foto di private disk) untuk mempercepat pengujian manual alur screening hingga akreditasi.
- [x] **2.6.4** **Modal Wizard Verifikasi Dokumen Cepat (`TeamReview.vue` & `ScreeningController.php`)**:
  - Saat petugas klik **Loloskan Pemain (Sah)** tetapi masih ada berkas berstatus `pending`, sistem menampilkan **Modal Wizard Verifikasi Cepat** dengan pratinjau dokumen dan *auto-advance* ke berkas berikutnya secara asinkron (`axios.post` JSON response tanpa menutup modal / reload halaman) hingga langkah konfirmasi pengesahan akhir.
  - Apabila seluruh berkas sudah berstatus `valid`, tombol **Loloskan Pemain (Sah)** menampilkan **Modal Konfirmasi Pengesahan Pemain** sebelum mengunci status `passed`.

---

## FASE 3: In-Web Notifications, Live Counter Badges & Guided End-to-End Team Readiness Workflow

> **Dokumen Rencana Implementasi:** [`docs/PHASE_3_IMPLEMENTATION_PLAN.md`](./PHASE_3_IMPLEMENTATION_PLAN.md)

### 3.1 Backend Shared Notification Counters & Team Workflow State Engine
- [x] **3.1.1** **State Machine Kesiapan Tim 5 Tahap (`ResolvesActiveTeam.php`)**:
  - Menambahkan metode `buildTeamWorkflow(Team $team)` yang menyuntikkan objek `team.workflow` ke seluruh halaman Portal Manajer Tim (`current_step`, `total_steps`, boolean pemenuhan syarat, `counts`, `badges`, dan `banner` kontekstual).
  - Menyelaraskan `AccreditationController.php` agar menggunakan trait `ResolvesActiveTeam` sehingga perpindahan tim aktif (`active_manager_team_id`), properti `can_manage_roster`, dan `team.workflow` konsisten 100%.
- [x] **3.1.2** **Shared Admin Operational Counter Badges (`HandleInertiaRequests.php` & `Admin/DashboardController.php`)**:
  - Membagikan prop `adminNotifications` secara global untuk peran internal panitia (`superadmin`, `finance`, `screening_officer`) yang memuat jumlah antrean real-time:
    - `pending_screening_players_count` (pemain `submitted` menunggu verifikasi).
    - `pending_payments_count` (bukti transfer `waiting_verification`).
    - `refund_required_count` (transaksi `refund_required`).
    - `ready_to_lock_teams_count` (tim `all_passed` yang siap mengunci skuad final).
    - `locked_teams_count` (tim `locked_roster` yang telah terakreditasi resmi).
    - `failed_emails_count` (log email berstatus `failed`).

### 3.2 In-Web Notification Banner & Live Sidebar Counter Badges
- [x] **3.2.1** **Komponen Global `WorkflowStepBanner.vue` pada `ManagerLayout.vue`**:
  - Menampilkan banner peringatan kontekstual di bagian atas seluruh halaman Portal Manajer Tim sesuai tahap kesiapan tim saat ini (Tahap 1 s/d Tahap 5).
  - Menampilkan **Peringatan Akreditasi Prioritas Tinggi** (`PERINGATAN AKREDITASI — LANGKAH TERAKHIR WAJIB`) begitu seluruh pemain tim telah disahkan (`all_passed`), lengkap dengan tombol CTA langsung menuju penguncian skuad final dan penerbitan ID Card.
  - Menampilkan pintasan langsung **Unduh Roster PDF** dan **Unduh ID Card PDF** ketika skuad telah berstatus `locked_roster`.
- [x] **3.2.2** **Live Counter & Status Badges pada Sidebar Manajer (`ManagerLayout.vue`)**:
  - Menampilkan badge dinamis di samping menu navigasi manajer:
    - `Ringkasan Tim`: Progres tahap (`1/5` s/d `5/5`).
    - `Pembayaran Pendaftaran`: `WAJIB` / `VERIFIKASI` / `LUNAS`.
    - `Daftar Pemain & Berkas`: `X REVISI` / `X/Min` / `X DRAFT` / `X PROSES` / `X SAH`.
    - `Pelatih & Ofisial`: `WAJIB 1+` / `X/Max`.
    - `Akreditasi & Skuad`: `BELUM SIAP` / `SIAP KUNCI` (aksen oranye-merah Persija) / `FINAL`.
- [x] **3.2.3** **Live Counter Badges & Quick Queue Pills pada Portal Panitia (`AdminLayout.vue`)**:
  - Menampilkan badge angka antrean pada menu sidebar Admin (`Dashboard`, `Daftar Tim`, `Screening Pemain`, `Verifikasi Transfer`, `Riwayat Transaksi`, `Antrean Email`) serta *Quick Operational Queue Pills* di header atas.

### 3.3 Guided 5-Stage End-to-End Team Readiness Flow
- [x] **3.3.1** **5-Stage Team Readiness Stepper (`Manager/Dashboard.vue`)**:
  - Memperbarui alur persiapan tim menjadi 5 kartu tahapan terstruktur: (1) Pembayaran Pendaftaran, (2) Skuad & Min. 2 Kiper (GK), (3) Pelatih & Ofisial Tim, (4) Verifikasi Screening Panitia, dan (5) Akreditasi & ID Card.
- [x] **3.3.2** **Real-Time Squad Requirement Checklist & Perbaikan Read-Only `locked_roster` (`Manager/Players.vue` & `Manager/Officials.vue`)**:
  - Memperbaiki logika `isUnpaidLocked` vs `isFinalRosterLocked` pada `Manager/Players.vue` sehingga saat tim telah mengunci skuad final (`locked_roster`), daftar pemain tetap tampil utuh dalam mode *read-only* (tidak tertukar dengan pesan belum bayar).
  - Menambahkan strip 4 indikator syarat skuad real-time di halaman pemain (Kuota Min. Pemain, Min. 2 GK, Status Pengajuan, dan Lolos Screening).
- [x] **3.3.3** **Banner Penyelesaian Screening Tim pada Workspace Admin (`Admin/Screening/TeamReview.vue` & `Admin/Dashboard.vue`)**:
  - Menampilkan banner status penyelesaian screening tim ketika seluruh pemain dalam tim telah disahkan (`all_passed`) beserta tombol unduh PDF Roster & ID Card bagi panitia.
- [x] **3.3.4** **Halaman Dossier 360° Detail Tim untuk Panitia (`Admin/Teams/Show.vue` & `TeamManagementController::show`)**:
  - Menambahkan halaman khusus `/admin/teams/{team}` (`admin.teams.show`) yang menampilkan profil 360° tim bagi Admin: Stepper 5 Tahap Kesiapan Tim, Tab Daftar Pemain & Berkas (dengan filter status, pencarian, dan pratinjau langsung dokumen/pas foto via `DocumentViewerModal`), Tab Pelatih & Ofisial (beserta pratinjau berkas lisensi), serta Tab Profil Klub & Riwayat Tagihan.
  - Memperkaya kartu tim pada `Admin/Teams/Index.vue` dengan filter `screening_status`, rincian jumlah pemain (`Sah`, `Antrean`, `Revisi`, `Ofisial`), serta tombol aksi langsung **Detail Tim & Skuad** dan **Screening**.
- [x] **3.3.5** **Pengaturan Dinamis Syarat Minimal Kiper (`1 GK` / `2 GK`) & Visibilitas/Kewajiban Lisensi Pelatih per Event**:
  - Menambahkan kolom `min_gk_per_team` (`1` atau `2`), `show_coach_license` (`boolean`), dan `require_coach_license` (`boolean`) pada tabel `events` serta form pembuatan/edit event (`Admin/Events/Form.vue`).
  - Menyinkronkan aturan visibilitas dan validasi wajib/opsional Lisensi Pelatih Kepala pada form pendaftaran publik (`Public/RegisterTeam.vue` & `RegisterTeamRequest.php`), profil tim manajer (`Manager/TeamProfile.vue` & `UpdateTeamProfileRequest.php`), serta form ofisial (`Manager/Officials.vue` & `StoreOfficialRequest.php`).
  - Menyinkronkan perhitungan kelayakan akreditasi (`AccreditationService::checkReadiness`) dan *workflow state machine* (`ResolvesActiveTeam::buildTeamWorkflow`) terhadap `min_gk_per_team` (`1` atau `2` GK) dan kewajiban lisensi pelatih.

### 3.4 Automated Testing & Verifikasi Fase 3
- [x] **3.4.1** Penulisan Feature Tests Fase 3 (`WorkflowAndNotificationBadgeTest.php`):
  - Pengujian akurasi counter `adminNotifications` untuk panitia.
  - Pengujian transisi `team.workflow` dari Tahap 1 hingga Tahap 5 (`is_ready_to_lock` -> peringatan akreditasi, dan `locked_roster` -> terakreditasi resmi).
  - Pengujian visibilitas daftar pemain *read-only* pada `Manager/Players` saat tim berstatus `locked_roster`.
  - Pengujian skenario event dengan konfigurasi `min_gk_per_team = 1` dan `require_coach_license = true` dari validasi form hingga kunci skuad final.
- [x] **3.4.2** Eksekusi penuh `php artisan test` (**62 tests / 535 assertions PASS**) dan `npm run build` dengan hasil 100% lulus tanpa regresi.

---

## DOKUMEN ANALISIS GAP & ROADMAP SELANJUTNYA (FASE 4 – FASE 6)

> **Dokumen Evaluasi & Rekomendasi Fitur:** [`docs/FEATURE_GAP_AND_ROADMAP.md`](./FEATURE_GAP_AND_ROADMAP.md)
> Memuat analisis fitur yang belum dibuat, rekomendasi fitur wajib operasional (*Fase 4*), fitur pelaksanaan pertandingan/bagan (*Fase 5*), serta fitur pelengkap bernilai tambah (*Fase 6*).
