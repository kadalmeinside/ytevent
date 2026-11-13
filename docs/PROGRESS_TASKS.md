# PROGRESS_TASKS.md - Pelacak Progres Implementasi & Audit Menyeluruh (Fase 1 – Fase 9)

Dokumen ini mencatat progres pengerjaan **Multi-Event Football Tournament, Audition & Coaching Clinic Platform (`ytevent`)** secara *real-time* dan runut mulai dari fondasi awal hingga fase audit arsitektur & penegakan SOP Lifecycle Event.

Legenda Status:
- `[ ]` Belum dikerjakan
- `[~]` Sedang dikerjakan
- `[x]` Selesai & Terverifikasi

### Peta Runut Keseluruhan Fase Implementasi
1. **Fase 1** — Core Platform, Pendaftaran Tim Anti-Race, Pembayaran, & Input Roster/Dokumen (`[x] Selesai`)
2. **Fase 2** — Operasional Pertandingan, Klasemen, Kartu Akreditasi QR, & E-Sertifikat (`[x] Selesai`)
3. **Fase 3** — Audit Keamanan, Konkurensi Finansial, & Pemulihan Edge-Case (`[x] Selesai`)
4. **Fase 4** — Audit Holistik End-to-End & Penutupan Celah Operasional Lapangan (`[x] Selesai`)
5. **Fase 5** — Audit Ketahanan Ekstrem, Pemulihan Bencana, & Otomatisasi Notifikasi Email (`[x] Selesai`)
6. **Fase 6** — Audit Kesiapan Produksi (*Production Readiness*) & Verifikasi Menyeluruh (`[x] Selesai`)
7. **Fase 7** — Cetak Biru Skalabilitas & Ekosistem Jangka Panjang (*Post Go-Live Roadmap*) (`[~] Roadmap Berjalan`)
8. **Fase 8** — Ekosistem Multi-Event & Audisi/Seleksi Bakat Individual Versi 2.0 (Sub-Fase 8.1 – 8.8) (`[x] Selesai`)
9. **Fase 9** — Audit Menyeluruh Arsitektur, Kepatuhan Best Practice (`GEMINI-2.md`), & Penegakan Batasan SOP Lifecycle Event (`[x] Selesai`)

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
- [x] **3.3.6** **Optimasi Search Engine (SEO), AI Generative Engine (GEO), & Proteksi No-Index Login/Portal Admin**:
  - **Penyembunyian Login & Portal Admin dari Mesin Pencari**: Menghapus tautan publik ke `/admin/login` pada footer `PublicLayout.vue`, menambahkan middleware [`SetSeoAndRobotsHeaders`](file:///Users/macbook/Application/ytevent/app/Http/Middleware/SetSeoAndRobotsHeaders.php) (`X-Robots-Tag: noindex, nofollow, noarchive, nosnippet`), meta tag `robots` / `googlebot` `noindex` pada `AdminLogin.vue`, `AdminLayout.vue`, `ManagerLayout.vue`, dan `PlayerVerification.vue`, serta aturan `Disallow: /admin`, `/admin/login`, `/manager`, `/private-media/` di `/robots.txt`.
  - **Standar SEO Modern & Server-Side Social Preview (`6cda4a7`)**: Menambahkan `sitemap.xml` dinamis, canonical URL, Server-Side Open Graph (`og:title`, `og:description`, `og:image`, `og:image:secure_url`, `og:image:alt` dengan URL absolut sesuai domain aktif), Twitter Cards (`summary_large_image`), serta Schema.org JSON-LD (`SportsOrganization`, `WebSite` + `SearchAction`, `ItemList`, `BreadcrumbList`, dan `SportsEvent` lengkap dengan lokasi stadion, jadwal, harga, dan ketersediaan kuota) langsung pada HTML server `app.blade.php` & `PublicEventController` serta `Public/Home.vue`, `Public/EventDetail.vue`, dan `Public/RegisterTeam.vue`.
  - **Standar AI Generative Engine Optimization (GEO)**: Menyediakan endpoint `/llms.txt` dan `/llms-full.txt` (standar `llmstxt.org`) serta izin eksplisit di `/robots.txt` bagi crawler AI (`GPTBot`, `OAI-SearchBot`, `PerplexityBot`, `ClaudeBot`, `Google-Extended`) untuk merangkum katalog turnamen publik tanpa menyentuh rute internal panitia.

### 3.4 Automated Testing & Verifikasi Fase 3
- [x] **3.4.1** Penulisan Feature Tests Fase 3 (`WorkflowAndNotificationBadgeTest.php` & `SeoAndGeoTest.php`):
  - Pengujian akurasi counter `adminNotifications` untuk panitia.
  - Pengujian transisi `team.workflow` dari Tahap 1 hingga Tahap 5 (`is_ready_to_lock` -> peringatan akreditasi, dan `locked_roster` -> terakreditasi resmi).
  - Pengujian visibilitas daftar pemain *read-only* pada `Manager/Players` saat tim berstatus `locked_roster`.
  - Pengujian skenario event dengan konfigurasi `min_gk_per_team = 1` dan `require_coach_license = true` dari validasi form hingga kunci skuad final.
  - Pengujian proteksi `noindex` pada `/admin/login` & `/admin`, validasi `/robots.txt`, `/sitemap.xml`, `/llms.txt`, `/llms-full.txt`, server-side Open Graph banner/title/description per event, serta JSON-LD structured data.
- [x] **3.4.2** Eksekusi penuh `php artisan test` (**67 tests / 580 assertions PASS**) dan `npm run build` dengan hasil 100% lulus tanpa regresi.

---

## FASE 4 — KONTROL STATUS TIM ADMIN, BULK SCREENING, ID CARD OFISIAL, EKSEKUSI REFUND, EKSPOR DATA & AKUN MANDIRI (SELESAI 100%)

> **Dokumen Rencana Implementasi Teknis Fase 4:** [`docs/PHASE_4_IMPLEMENTATION_PLAN.md`](./PHASE_4_IMPLEMENTATION_PLAN.md)
> **Dokumen Analisis Gap & Roadmap Lengkap (Fase 4 – Fase 6):** [`docs/FEATURE_GAP_AND_ROADMAP.md`](./FEATURE_GAP_AND_ROADMAP.md)

### 4.1 Kontrol Administratif Status Tim & Catatan Internal Panitia (`/admin/teams/{team}`)
- [x] **4.1.1** **Aksi Buka Kunci Skuad Final (*Unlock Team Roster*)**: Mengubah status tim dari `locked_roster` kembali ke `active` atas izin Superadmin (misal pergantian pemain cedera sebelum Technical Meeting) disertai pencatatan audit (`TeamManagementController::unlockRoster`).
- [x] **4.1.2** **Aksi Kunci Skuad oleh Admin (*Force Lock Roster*)**: Memungkinkan Admin mengunci skuad tim yang sudah memenuhi syarat (`is_ready_to_lock`) jika manajer lupa menekan tombol kunci menjelang penutupan (`TeamManagementController::forceLockRoster`).
- [x] **4.1.3** **Aksi Diskualifikasi Tim (`disqualified`) & Re-Aktivasi**: Menjatuhkan status `disqualified` beserta alasan resmi (yang otomatis membebaskan slot kuota event dan menyinkronkan status kuota event) atau memulihkan status aktif (`TeamManagementController::updateStatus`).
- [x] **4.1.4** **Catatan Internal Panitia (`admin_notes`)**: Form penyimpanan catatan internal khusus panitia pada halaman Dossier Tim (`Admin/Teams/Show.vue` & `TeamManagementController::updateNotes`).

### 4.2 Pengajuan Screening Kolektif (*Bulk Submit*) & Akreditasi Staf Ofisial
- [x] **4.2.1** **Tombol *Bulk Submit Players* (`Manager/Players.vue` & `PlayerController::submitAllReady`)**: Mengajukan seluruh pemain berstatus `draft`/`rejected` yang berkas wajibnya sudah lengkap ke status `submitted` dalam 1 kali klik.
- [x] **4.2.2** **Verifikasi Ofisial & Cetak ID Card Pelatih/Ofisial (PDF)**: Menyertakan kartu ID Resmi untuk Pelatih Kepala & Ofisial di `id_cards.blade.php` lengkap dengan jabatan, nomor lisensi kepelatihan, dan kode QR verifikasi.

### 4.3 Penyelesaian Refund Finance (`refunded`) & Pusat Ekspor Data (CSV / Excel)
- [x] **4.3.1** **Alur Penyelesaian Pengembalian Dana (*Mark Refund Completed*)**: Tombol aksi bagi Finance/Superadmin untuk mengubah transaksi `refund_required` menjadi `refunded` disertai catatan referensi transfer refund dan pengiriman email `PaymentRefundedMail` (`PaymentService::markRefunded`).
- [x] **4.3.2** **Ekspor Laporan Keuangan & Transaksi (CSV/Excel)**: Ekspor data transaksi per event/status/metode di halaman `/admin/payments/transactions` (`PaymentVerificationController::exportCsv`).
- [x] **4.3.3** **Ekspor Master Data Tim & Database Pemain Terverifikasi (CSV/Excel)**: Ekspor data tim dan biodata pemain (NIK, NISN, TTL, Posisi, No. Punggung, Status Screening, Kode QR) per event di `/admin/teams` (`TeamManagementController::exportTeamsCsv` & `exportPlayersCsv`).

### 4.4 Manajemen Password & Pemulihan Akun Mandiri
- [x] **4.4.1** **Alur Lupa Kata Sandi (*Forgot & Reset Password via Email Queue*)**: Halaman `/forgot-password` dan `/reset-password/{token}` (`PasswordResetController`, `ForgotPassword.vue`, `ResetPassword.vue`) yang terintegrasi dengan antrean log email (`EmailNotificationService::sendPasswordReset`).
- [x] **4.4.2** **Form Ubah Kata Sandi Mandiri (*Change Password*)**: Pengaturan ubah password dengan verifikasi password lama di Portal Manajer (`Manager/TeamProfile.vue` & `DashboardController::updatePassword`).

### 4.5 Automated Testing & Verifikasi Fase 4
- [x] **4.5.1** Penulisan Feature Tests `Phase4OperationsTest.php` untuk seluruh fitur Fase 4 (Unlock/Force-Lock/Disqualify Tim, Bulk Submit Pemain, ID Card Ofisial, Eksekusi Refund, Ekspor CSV, dan Reset/Ubah Password).
- [x] **4.5.2** Eksekusi penuh `php artisan test` (**72 tests / 619 assertions PASS**) dan `npm run build` hingga 100% lulus.

---

## PENYEMPURNAAN OPERASIONAL: PDF LAYOUT, SIKLUS REVISI DOKUMEN & MODUL SIMULASI DATA (SELESAI 100%)

### 4.6 Presisi Layout Cetak PDF (ID Card A4 & Album Roster Tim)
- [x] **4.6.1** **Presisi ID Card A4 Dibagi 4 Simetris (`id_cards.blade.php`)**:
  - Mengatur ulang grid CSS PDF cetak menjadi 2 kolom × 2 baris (tepat 4 kartu per lembar A4 portrait) dengan batas garis potong (*cut guide*) presisi, padding proporsional, serta tata letak vertikal simetris untuk lencana akreditasi pemain dan ofisial.
- [x] **4.6.2** **Pemisahan Halaman Album Roster Skuad & Ofisial (`roster_sheet.blade.php`)**:
  - Mengatur susunan 3 kartu pemain per baris (*3 cards per row*) dengan foto, nomor punggung, NIK, NISN, dan kode akreditasi yang rapi.
  - Memisahkan halaman daftar pemain dengan halaman staf ofisial menggunakan page break CSS (`page-break-before: always;`).
  - Menyediakan blok tanda tangan dan pengesahan resmi (Manajer Tim, Panitia Penyelenggara/Inspektur Pertandingan, dan Medis) di halaman tersendiri agar tidak terpotong atau tercampur dengan daftar pemain.

### 4.7 Siklus Revisi Dokumen Pemain Ditolak Tanpa Membuka Kunci Skuad
- [x] **4.7.1** **Akses Revisi Mandiri Berkas Ditolak (`PlayerPolicy.php` & `ScreeningService.php`)**:
  - Memperbaiki aturan akses agar pemain berstatus `rejected` atau pemain yang memiliki dokumen berstatus `invalid` dapat langsung mengunggah ulang dokumen revisi secara mandiri tanpa harus membuka kunci skuad tim keseluruhan.
  - Mengubah status dokumen menjadi `valid`/`invalid` pada screening granular secara otomatis memperbarui status pemain dan memberikan kesempatan manajer untuk mengajukan ulang (*re-submit*).

### 4.8 Modul Simulasi Data Cepat (*Floating Testing & Demo Injector Tool*)
- [x] **4.8.1** **Komponen Global Floating Button (`SimulationFloatingTool.vue`)**:
  - Tombol melayang di pojok layar yang dapat diaktifkan/dinonaktifkan secara dinamis via konfigurasi `.env` (`SIMULATION_TOOL_ENABLED=true/false`) atau pengaturan sistem panitia, dan siap dicabut (*take out*) saat masuk ke fase *full production*.
- [x] **4.8.2** **Aksi Simulasi End-to-End (`SimulationController.php`)**:
  - **Simulasi Pendaftaran & Pembayaran Instan**: Mendaftarkan tim baru, mengaktifkan status, dan menerbitkan tagihan yang langsung disimulasikan lunas.
  - **Simulasi Isi Skuad & Dokumen Lengkap**: Menginjeksi data pemain sintetis lengkap dengan NIK valid, nomor punggung unik, foto, dan 4 dokumen identitas di private storage.
  - **Simulasi Sahkan Semua (One-Click Bulk Approve)**: Menyetujui dan meloloskan seluruh berkas pemain dalam tim atau event ke status `passed` sekaligus mengunci skuad tim (`locked_roster`).
  - **Simulasi Penuhi Sisa Kuota Slot Event**: Mengisi seluruh sisa kuota tim yang masih kosong pada event tertentu dengan tim-tim demo otomatis agar panitia dapat langsung menguji fase turnamen/drawing grup tanpa menunggu pendaftar manual.
- [x] **4.8.3** **Pengujian Otomatis Modul Simulasi**:
  - Penulisan test suite `tests/Feature/SimulationModuleTest.php` (**5 tests / 32 assertions PASS**) untuk memverifikasi isolasi lingkungan, eksekusi step-by-step, fast-forward, dan pengisian slot sisa kuota.

---

## FASE 5 — MANAJEMEN KOMPETISI, JADWAL, HASIL PERTANDINGAN, DSP & KLASEMEN (SELESAI 100%)

> **Dokumen Rencana Implementasi:** [`docs/PHASE_5_AND_6_IMPLEMENTATION_PLAN.md`](./PHASE_5_AND_6_IMPLEMENTATION_PLAN.md)

### 5.1 Format Pertandingan Fleksibel & Standar Usia Dini (5v5, 7v7, 8v8, 9v9, 11v11)
- [x] **5.1.1** **Konfigurasi Format Turnamen pada Event (`events` table & `Admin/Events/Form.vue`)**:
  - Menambahkan kolom `match_format` (`5_vs_5`, `7_vs_7`, `8_vs_8`, `9_vs_9`, `11_vs_11`), `starting_players_count` (5, 7, 8, 9, atau 11), dan `dsp_cutoff_minutes` (default 30 menit) pada tabel `events`.
  - Dropdown interaktif di form pembuatan & edit event dengan deskripsi standar kategori usia (contoh: 5v5 untuk U-8/U-9 Futsal/Mini, 7v7 untuk U-10/U-11 Mini Soccer, 9v9 untuk U-12/U-13, 11v11 untuk U-14+ PSSI/FIFA).
  - Tampilan chip format kompetisi pada halaman detail event publik (`EventDetail.vue`).

### 5.2 Sistem Pengundian Grup, Kunci Grup & Penataan Tim Manual
- [x] **5.2.1** **Generator Grup & Undian Otomatis (`StandingsCalculatorService::generateGroupsAndDraw`)**:
  - Membentuk grup (Grup A s/d Grup P) dan mengundi posisi tim terakreditasi (`locked_roster`) secara acak.
- [x] **5.2.2** **Fitur Kunci Pembagian Grup (`is_group_locked` & `toggleGroupLock`)**:
  - Tombol **"Kunci Grup"** pada Pusat Kendali Turnamen Admin (`Admin/Events/Tournament.vue`).
  - Saat grup dikunci, aksi pengundian otomatis dinonaktifkan dan request undi ulang ditolak untuk mencegah perubahan tanpa sengaja pasca *Technical Meeting (TM)*.
  - Panitia dapat membuka kunci (*Unlock*) kembali kapan saja dengan satu klik konfirmasi.
- [x] **5.2.3** **Pindah Grup Manual (*Manual Seeding / Akademi Separator*) (`moveTeamGroup`)**:
  - Aksi **"Pindah Grup"** pada setiap baris tim di klasemen grup admin.
  - Memungkinkan panitia memindahkan tim antar-grup sebelum pertandingan dimulai (misal memisahkan tim se-akademi agar tidak satu grup).
  - Validasi ketat: Tim yang sudah memainkan pertandingan resmi (`status != scheduled`) dilarang dipindahkan demi integritas kompetisi.

### 5.3 Penjadwalan Pertandingan, Reschedule & Generator Babak Gugur
- [x] **5.3.1** **Generator Jadwal Round-Robin Fase Grup (`generateGroupStageMatches`)**:
  - Membuat seluruh pasangan pertandingan fase grup (*Round-Robin*) secara otomatis dengan parameter jam mulai, interval menit (misal 60 menit per laga), dan distribusi nomor lapangan (Lapangan 1, Lapangan 2, Lapangan Utama).
- [x] **5.3.2** **Modal Penjadwalan Ulang Pertandingan (*Match Reschedule*) (`rescheduleMatch`)**:
  - Tombol **"Reschedule"** pada kartu pertandingan admin untuk menyesuaikan waktu & jam kick-off baru, lapangan tanding, nama wasit, status pertandingan (`scheduled`, `ongoing`, `completed`, `postponed`), dan catatan penundaan (*match notes*).
- [x] **5.3.3** **Generator Babak Gugur Otomatis (*Cross-Group Knockout Seeding*) (`generateKnockoutStageMatches`)**:
  - Tombol **"Generate Babak Gugur"** untuk babak 16 besar, perempat final, semifinal, dan final.
  - Membaca posisi klasemen grup terkini dan secara otomatis menyilangkan tim unggulan:
    - **2 Grup**: `1A vs 2B` & `1B vs 2A`.
    - **4 Grup**: `1A vs 2B`, `1C vs 2D`, `1B vs 2A`, `1D vs 2C`.
    - Mengalokasikan jam kick-off dan rotasi lapangan secara otomatis.
- [x] **5.3.4** **Tambah Pertandingan Manual (`storeMatch`)**:
  - Form penambahan laga manual jika panitia membutuhkan pertandingan tambahan atau laga persahabatan/perebutan tempat ketiga.

### 5.4 Daftar Susunan Pemain (DSP / Match Lineup) & Regulasi Disiplin
- [x] **5.4.1** **Portal Pengisian DSP Manajer Tim (`/manager/matches` & `MatchLineupController.php`)**:
  - Halaman pemilihan pemain inti (*Starting Lineup*) dan pemain cadangan (*Substitutes*) sebelum pertandingan dimulai.
  - Validasi ketat kuota starter: Jumlah starter wajib tepat sama dengan format turnamen (`starting_players_count`, contoh: 7 untuk 7v7, 11 untuk 11v11).
  - Validasi penjaga gawang: Starting lineup wajib menyertakan minimal 1 Penjaga Gawang (GK) jika tim memiliki kiper yang sah.
  - Validasi penugasan Kapten Tim (`captain_player_id`).
- [x] **5.4.2** **Penegakan Otomatis Suspensi Kartu Disiplin (`checkPlayerDisciplinaryStatus`)**:
  - Memeriksa akumulasi kartu kuning (2 kartu kuning berturut-turut) atau kartu merah langsung pada laga sebelumnya.
  - Pemain yang terkena sanksi suspensi otomatis diblokir dari pilihan DSP dengan pesan alasan yang transparan.
- [x] **5.4.3** **Mekanisme Auto-Lock DSP (Jadwal Penguncian Otomatis)**:
  - DSP otomatis terkunci dan tidak dapat diubah oleh manajer apabila:
    1. Pertandingan telah berstatus `ongoing` (sedang bertanding), `completed` (selesai), atau `cancelled`.
    2. Waktu telah melewati batas cut-off regulasi (`kickoff_at - dsp_cutoff_minutes`, misal H-30 menit sebelum kick-off).
  - Perubahan darurat akibat cedera saat pemanasan (*pre-match warm up*) dibatasi hanya melalui wewenang Admin / Pengawas Pertandingan (*Match Commissioner*).
- [x] **5.4.4** **Cetak Dokumen Resmi DSP Match Sheet (PDF) (`lineup_sheet.blade.php`)**:
  - Lembar berita acara pertandingan resmi berformat A4 yang memuat daftar starter, cadangan, ofisial di bangku cadangan, warna kostum tim, serta kolom tanda tangan wasit dan pengawas pertandingan.

### 5.5 Input Hasil Pertandingan, Skor, Kejadian Laga & Klasemen Real-time
- [x] **5.5.1** **Pusat Input Skor & Kejadian Pertandingan (`updateMatchResult`)**:
  - Form pencatatan skor akhir, skor adu penalti (fase gugur), nama wasit, dan kejadian laga (*match events*):
    - Gol Reguler, Gol Penalti, Gol Bunuh Diri (*Own Goal*).
    - Kartu Kuning, Kartu Kuning Kedua (*Second Yellow Card*), Kartu Merah Langsung.
    - Menit kejadian dan catatan wasit.
- [x] **5.5.2** **Kalkulasi Klasemen Otomatis (*Real-Time Standings Engine*) (`recalculateEventStandings`)**:
  - Menghitung statistik tim secara transaksional: Main (*P*), Menang (*W*), Seri (*D*), Kalah (*L*), Memasukkan (*GF*), Kebobolan (*GA*), Selisih Gol (*GD*), Poin (*Pts*), serta akumulasi Kartu Kuning & Merah (*Fair Play*).
  - Mengurutkan peringkat klasemen berdasarkan: (1) Manual Rank Override, (2) Poin, (3) Selisih Gol, (4) Gol Memasukkan, (5) Kartu Merah paling sedikit, (6) Kartu Kuning paling sedikit.
- [x] **5.5.3** **Tab Live Match Center di Halaman Publik (`Public/EventDetail.vue`)**:
  - Tab **Jadwal & Hasil**: Kartu skor pertandingan, menit gol, kartu disiplin, lapangan, dan status laga.
  - Tab **Klasemen Grup**: Tabel klasemen interaktif per grup lengkap dengan indikator tim lolos.
  - Tab **Statistik & Top Scorer**: Papan peringkat pencetak gol terbanyak (*Top Scorer Leaderboard*) dan tim paling sportif (*Fair Play Award*).

### 5.6 Pengumuman Turnamen, Prestasi Tim, Matchday QR Scanner & E-Sertifikat Digital
- [x] **5.6.1** **Papan Pengumuman & Penetapan Prestasi Tim (`announcements` & `achievements`)**:
  - Admin dapat mempublikasikan pengumuman resmi turnamen (jadwal TM, regulasi teknis, info lapangan).
  - Admin dapat menyematkan gelar juara pada profil tim (`achievement_title`, contoh: "Juara 1 (Champions)", "Runner-up", "Peringkat 3").
- [x] **5.6.2** **Panel Pemindai QR Hari Pertandingan (*Matchday QR Scanner*) (`/admin/qr-scanner`)**:
  - Endpoint verifikasi cepat `POST /admin/qr-scanner/lookup` untuk memeriksa keabsahan pemain atau ofisial di meja pengawas pertandingan dalam hitungan detik.
- [x] **5.6.3** **E-Sertifikat Digital & Gerbang Rilis Panitia (`certificates_released`)**:
  - Template PDF E-Sertifikat Penghargaan Pemain (`player_certificate.blade.php`) berdesain landscape resmi Persija Development lengkap dengan kode verifikasi QR dan predikat prestasi tim.
  - **Gerbang Rilis Sertifikat (*Certificate Release Gating*)**: Manajer tim dibatasi dan tidak dapat mengunduh sertifikat pemain sebelum panitia mengaktifkan toggle **"Rilis E-Sertifikat"** (`toggleCertificateRelease`) atau status turnamen telah resmi diselesaikan (`status = completed`).

### 5.7 Automated Testing & Verifikasi Fase 5
- [x] **5.7.1** Penulisan Feature Tests `Phase5TournamentTest.php` (15 test methods):
  - Pengujian kontrol turnamen admin, undian grup otomatis, kunci grup & penolakan undi ulang saat terkunci, pemindahan tim antar-grup, penjadwalan ulang laga (*reschedule*), generator babak gugur silang (*knockout cross-pairing*).
  - Pengujian pengajuan DSP manajer: validasi jumlah starter sesuai format turnamen (5v5-11v11), kewajiban kiper (GK), blokir pemain suspensi, serta auto-lock DSP saat status ongoing atau melewati cut-off.
  - Pengujian pembatasan unduh e-sertifikat (403 saat belum dirilis, 200 PDF saat dirilis / event completed).
  - Pengujian input skor, pencatatan gol/kartu, kalkulasi klasemen otomatis, leaderboard top scorer, pengumuman, dan scanner QR matchday.
- [x] **5.7.2** Eksekusi Penuh Test Suite: Seluruh **95 automated tests dengan 794 assertions PASS (100% lulus)** dan build aset Vite (`npm run build`) berjalan bersih tanpa error.

---

## FASE 6 & PRODUCTION HARDENING — AUDIT KEAMANAN, EXPLOIT RESOLUTION, BRANDED ERROR VIEWS & PRODUCTION READINESS (SELESAI 100%)

### 6.1 Penutupan Celah Keamanan & Isolasi Modul Simulasi
- [x] **6.1.1** **Penutupan Celah Kritis Modul Simulasi (`switch-persona`)**:
  - Mengubah default konfigurasi `config('simulation.master_enabled')` menjadi **MATI (`false`)**.
  - Menerapkan penguncian rute ganda: rute `/simulation/*` tidak didaftarkan pada lingkungan produksi (`! app()->isProduction()`).
  - Menambahkan *runtime guard-rail* di `AppServiceProvider::boot()` yang langsung menghentikan booting aplikasi (`RuntimeException`) jika modul simulasi atau `APP_DEBUG=true` aktif di produksi.
  - Menolak akses persona switch tanpa login sah (`403 Forbidden`).

### 6.2 Eliminasi Kerentanan Dependensi & Upgrade Framework
- [x] **6.2.1** **Upgrade ke Laravel 12.71.0**:
  - Mengeliminasi 5 security advisories (termasuk CVE-2026-114509 TokenGuard, CRLF injection pada rule email, dan path confusion signed URL).
  - Hasil audit keamanan: `composer audit` **0 vulnerabilities** dan `npm audit` **0 vulnerabilities**.

### 6.3 Header Keamanan Universal & Rate Limiting Terpusat
- [x] **6.3.1** **Middleware `SecurityHeaders`**:
  - Content-Security-Policy (CSP) berbasis cryptographic nonce dinamis per-request (`Vite::useCspNonce()`) yang terintegrasi dengan tag inline Ziggy (`@routes(null, $nonce)`).
  - Strict-Transport-Security (HSTS: `max-age=31536000; includeSubDomains`) saat koneksi HTTPS.
  - Anti-clickjacking (`X-Frame-Options: SAMEORIGIN`), `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, dan `Permissions-Policy: camera=(self)`.
  - Dukungan konfigurasi reverse proxy `TRUSTED_PROXIES` untuk integrasi Cloudflare / load balancer.
- [x] **6.3.2** **Rate Limiting Anti-Abuse (`AppServiceProvider`)**:
  - Throttle `public-verify` (30 req/min per IP) untuk mencegah scraping/enumerasi data anak pada QR ID Card.
  - Throttle `password-reset` (5 req/min per IP & 10 req/jam per email|IP) untuk mencegah email-bombing.
  - Throttle `webhooks` (120 req/min) dan `qr-lookup` (120 req/min).

### 6.4 Sanitasi DOM & Integritas Kriptografi Antrean Email
- [x] **6.4.1** **Parser DOM XML Whitelist (`HtmlSanitizer`)**:
  - Sanitizer semantik berbasis `DOMDocument` untuk deskripsi event menggantikan regex.
  - Menghapus tuntas seluruh tag berbahaya (`<script>`, `<iframe>`, `<object>`, `<svg onload>`, event handler `on*`, skema `javascript:`).
- [x] **6.4.2** **Tanda Tangan SHA-256 HMAC Payload Email**:
  - Payload mailable pada tabel `email_notification_logs` ditandatangani menggunakan HMAC SHA-256 dengan secret key aplikasi (`app.key`).
  - Mencegah bahaya *PHP Object Injection* saat `unserialize()` dipanggil oleh queue worker.

### 6.5 Keamanan Kata Sandi Seeder & Halaman Error Bermerek (Branded Views)
- [x] **6.5.1** **Kata Sandi Seeder Dinamis Produksi**:
  - `RolePermissionSeeder` di lingkungan produksi tidak menggunakan kata sandi default statis publik (`Admin12345`), melainkan mewajibkan nilai dari `.env` atau otomatis meng-generate string kriptografi acak 24 karakter.
- [x] **6.5.2** **Halaman Error Kustom (`resources/views/errors/`)**:
  - Menyediakan template halaman status `404 Not Found`, `403 Forbidden`, `429 Too Many Requests`, dan `500 Server Error` dengan tema resmi Persija Liquid Glass dan tombol kembali ke beranda.
  - Mencegah kebocoran stack trace dan query SQL internal ke publik.
- [x] **6.5.3** **Template `.env.production.example`**:
  - Menyediakan konfigurasi template produksi lengkap siap pakai untuk server hosting cPanel (`yt.persijadevelopment.id`) mencakup MySQL, HTTPS cookie security, SMTP, dan penonaktifan modul dev.

### 6.6 Automated Testing & Verifikasi Penuh
- [x] **6.6.1** Penulisan Test Suite `SecurityHardeningTest.php` (12 test methods / 55 assertions).
- [x] **6.6.2** **Hasil Eksekusi Penuh PHPUnit**: Seluruh **107 Feature & Unit Tests dengan 849 assertions PASS 100% (0 failed)**.
- [x] **6.6.3** **Vite Build**: Kompilasi aset frontend `npm run build` sukses tanpa error (2.28s).

---

## FASE 7 — CETAK BIRU SKALABILITAS & KERELEVANAN EKOSISTEM JANGKA PANJANG (POST GO-LIVE)

- [x] **7.1** Dokumen Strategis Skalabilitas & Nilai Jangka Panjang: [`SCALABILITY_AND_ECOSYSTEM_ROADMAP.md`](file:///Users/macbook/Application/ytevent/docs/SCALABILITY_AND_ECOSYSTEM_ROADMAP.md) telah selesai disusun dan disahkan.
- [ ] **7.2** *(P-1 Segera)* Implementasi Otomatisasi Watermarking Perlindungan Data Anak (UU PDP No. 27/2022) pada berkas upload Akta/KK.
- [ ] **7.3** *(P-2 Segera)* Penambahan tautan Live Streaming YouTube/TikTok pada kartu pertandingan & live match center.
- [ ] **7.4** *(P-3 Bulan Depan)* Digital Player Passport & Talent Scouting Database terpusat berbasis NIK/NISN.
- [ ] **7.5** *(P-4 Skala Trafik)* Migrasi private document vault ke Object Storage (Cloudflare R2 / AWS S3) dengan Presigned URLs.
- [ ] **7.6** *(P-5 Operasional)* Integrasi Redis In-Memory Caching & Laravel Horizon Queue Cluster.

---

## FASE 8 — EKOSISTEM MULTI-EVENT & AUDISI/SELEKSI BAKAT INDIVIDUAL (VERSI 2.0)

Dokumen Rencana Teknis Terperinci: [`PHASE_MULTI_EVENT_AND_INDIVIDUAL_IMPLEMENTATION_PLAN.md`](file:///Users/macbook/Application/ytevent/docs/PHASE_MULTI_EVENT_AND_INDIVIDUAL_IMPLEMENTATION_PLAN.md)  
Dokumen PRD Terverifikasi: [`PRD_MULTI_EVENT_INDIVIDUAL.md`](file:///Users/macbook/Application/ytevent/docs/PRD_MULTI_EVENT_INDIVIDUAL.md)

### 8.1 Sub-Fase 1: Konfigurasi Kuota Dinamis & Custom Form Presets Engine
- [x] **8.1.1** Migrasi database penambahan kolom `event_type`, `participant_type`, `quota_type`, `max_participants`, `position_quota_caps`, `form_presets`, dan `custom_fields_schema` pada tabel `events`.
- [x] **8.1.2** Pembaruan model `Event.php` (casts JSON, helper `availableSlotsCount()`, dan logika `canAcceptRegistration()` untuk mode unlimited quota).
- [x] **8.1.3** Pembaruan form admin `Admin/Events/Form.vue` (pemilihan tipe event, mode kuota fixed vs unlimited, dan sakelar toggle preset formulir).
- [x] **8.1.4** Pembaruan halaman publik `Public/EventDetail.vue` & `Public/Home.vue` (badge dinamis kuota terbuka vs kuota tetap).
- [x] **8.1.5** Automated Feature Test `Phase8DynamicQuotaAndFormPresetsTest.php` dan verifikasi integritas sistem kuota (122 automated tests PASS 100%).

### 8.2 Sub-Fase 2: Universal Account & Unified Portal Header / Dashboard
- [x] **8.2.1** Transisi navigasi publik `PublicLayout.vue`: tombol `"Login Manajer"` diganti menjadi `"Masuk"` / `"Akun Saya"` yang mendukung manajer tim turnamen dan orang tua audisi.
- [x] **8.2.2** Harmonisasi portal login `/login` dan dropdown navigasi multi-peran (Tim Turnamen & Pendaftaran Audisi Anak) dengan copy teks ramah keluarga.
- [x] **8.2.3** Pembuatan antarmuka **Unified Dashboard Hub** (`/manager/dashboard`) dengan switcher tab interaktif (`🏆 Tim Turnamen` dan `⭐ Pendaftaran Audisi Anak`) yang menampilkan kartu tim turnamen sekaligus kartu pendaftaran audisi anak.
- [x] **8.2.4** Automated Feature Test `Phase8UniversalAccountAndDashboardTest.php` lolos 100%.

### 8.3 Sub-Fase 3: Pendaftaran Audisi, Sesi Waktu, & Screening Dokumen
- [x] **8.3.1** Migrasi tabel `event_sessions`, `audition_stages`, `audition_participants`, `audition_attendances`, dan `scouting_evaluations` dengan dukungan payment polymorphic/nullable team_id.
- [x] **8.3.2** Formulir pendaftaran audisi dinamis (`/events/{slug}/register-audition`) yang me-render input sesuai preset event + checkbox surat pernyataan medis (*liability waiver*), kontak darurat, upload pas foto (kompresi WebP), akta lahir, dan KK via `FileInput.vue`.
- [x] **8.3.3** Integrasi tagihan pembayaran Midtrans Snap & manual transfer terpadu untuk pendaftaran individual tanpa duplikasi engine transaksi.
- [x] **8.3.4** Panel screening berkas atlet admin (`/admin/screening/auditions`): verifikasi Akta Kelahiran, KK, dan Pas Foto dengan SOP persetujuan otomatis menerbitkan Nomor Dada berurutan dan kode verifikasi keabsahan.
- [x] **8.3.5** Automated Feature Test `Phase8AuditionRegistrationAndScreeningTest.php` lolos 100%.

### 8.4 Sub-Fase 4: Generator Nomor Dada (Bib Tag PDF) & Absensi Multi-Hari QR Scanner
- [x] **8.4.1** Generator PDF Nomor Dada ukuran A4 Landscape (`AuditionBibPdfService`) dengan nomor dada raksasa di tengah, foto atlet, spesifikasi teknis, barcode NIK anti-joki, dan lubang peniti dada siap cetak (hanya dapat diunduh jika lunas & lolos screening).
- [x] **8.4.2** Modul Web QR Scanner Kehadiran Lapangan (`/admin/auditions/scanner`) dengan pemindaian kamera langsung via BarcodeDetector / jsQR fallback, sound feedback Web Audio API, input nomor bib manual, dan counter kehadiran langsung.
- [x] **8.4.3** Penerapan *Gatekeeper Guard*: sistem memvalidasi kelunasan, keabsahan berkas screening, menolak duplikasi check-in, dan secara ketat menolak check-in peserta yang tereliminasi pada hari/tahap sebelumnya.
- [x] **8.4.4** Automated Feature Test `Phase8BibAndAttendanceTest.php` lolos 100%.

### 8.5 Sub-Fase 5: Mobile Quick Scouting Web Panel
- [x] **8.5.1** Registrasi peran Spatie RBAC `talent_scout` dan pemberian izin `auditions.scout.grade`.
- [x] **8.5.2** Antarmuka web responsif ramah sentuhan di lapangan bawah sinar matahari (`/scout/events/{event}`) dengan header rekapitulasi nilai dan filter cepat nomor bib serta posisi bermain.
- [x] **8.5.3** Form *Field Quick Grade*: tombol sentuh besar rekomendasi A (Lolos), B (Review), C (Gugur), rating bintang 1-5, chip tag parameter teknis (`#KecepatanTinggi`, dll), dan catatan pelatih dengan debounced auto-save.
- [x] **8.5.4** Fitur kunci penilaian sesi harian (*locked session*) oleh scout untuk mencegah salah sentuh dan menjaga integritas data penilaian lapangan.

### 8.6 Sub-Fase 6: Kelolosan Bertahap, Notifikasi Email Otomatis, & E-Sertifikat
- [x] **8.6.1** Panel seleksi kelulusan bertahap admin (`/admin/events/{event}/auditions/qualifications`): konsolidasi hasil evaluasi seluruh scout (persentase A/B/C, rata-rata bintang, tag), absensi multi-hari, dan promosi/eliminasi masal.
- [x] **8.6.2** Otomatisasi pengiriman email transaksional berjenjang (`AuditionStageResultMail`): instruksi hari lanjutan untuk yang lolos vs surat apresiasi motivasional untuk yang belum lolos.
- [x] **8.6.3** Generator PDF E-Sertifikat Berjenjang (`AuditionCertificateService`): Piagam Partisipasi Resmi, Piagam Finalis Seleksi, dan Piagam Atlet Terpilih Skuad Utama ber-QR Code verifikasi digital publik (`/verify/certificate/{code}`).
- [x] **8.6.4** Automated Feature Test `Phase8ScoutingAndQualificationTest.php` lolos 100%.

### 8.7 Sub-Fase 7: Digital Pass (E-Tiket), Admin Event Workspace Hub, & Split-View Screening Individual
- [x] **8.7.1** **Refaktorisasi Terminologi & Alur Digital Pass / E-Tiket**: Menjadikan `registration_number` sebagai ID Digital Pass utama untuk check-in QR Scanner seluruh event perorangan (Audisi & Coaching Clinic), sementara `bib_number` berfungsi sebagai nomor rompi opsional khusus audisi.
- [x] **8.7.2** **Penyederhanaan Daftar Event & Sentralisasi Event Workspace Hub (`Admin/Events/Index.vue` & `Admin/Events/Show.vue`)**:
  - Kartu daftar event hanya menampilkan 4 aksi tingkat-event (`Kelola Event`, `Edit`, `Sync Kuota`, `Lihat Publik`).
  - Halaman `/admin/events/{event}` menyajikan hub operasional bertab (`Ringkasan & Kuota`, `Peserta & Screening` / `Tim & Screening`, `Operasional & Lapangan`) yang beradaptasi untuk Turnamen Tim, Audisi, maupun Coaching Clinic.
- [x] **8.7.3** **Peningkatan UX Screening Perorangan Setara Screening Tim (`Admin/Screening/Auditions.vue` & `AuditionReview.vue`)**:
  - Antarmuka Split-View 2-panel (`/admin/screening/auditions/events/{event}`) dengan daftar antrean peserta di kiri dan panel verifikasi terpadu di kanan.
  - Penambahan kolom JSON `document_statuses` pada `audition_participants`, endpoint granular `POST /admin/screening/auditions/{participant}/document-verify`, serta reset status otomatis saat re-upload di `AuditionDocumentReuploadService`.
  - Kotak validasi Cut-Off Tanggal Lahir (`is_age_valid`), Quick Verification Wizard, inspeksi berkas & pas foto melalui `DocumentViewerModal` tersemat (*zoom* & rotasi tanpa tab eksternal), auto-fill catatan penolakan, dan *Emergency Unlock* (`POST /admin/screening/auditions/{participant}/unlock`) yang tercatat di `AuditLog`.
- [x] **8.7.4** Automated Feature Test `EventWorkspaceAndIndividualScreeningTest.php` lolos 100%.

### 8.8 Sub-Fase 8: Penyempurnaan Event Form, Digital Pass A4 Portrait, Autentisitas HMAC, & Sinkronisasi Penuh Pengaturan Event
- [x] **8.8.1** **Layout Full-Width Event Form, Stage Builder, & Batas Usia Fleksibel (`Admin/Events/Form.vue`, `Admin/Events/Show.vue`, `Dashboard.vue`)**:
  - Perbaikan lebar halaman Event Form, panel ringkasan kuota/tahapan anti-bertumpuk, Stage Builder dinamis (`audition_stages`), dukungan batas usia terbuka maupun satu sisi (`min_birth_date`/`max_birth_date`), dan penyelarasan label `"Edit Event"` pada Dashboard.
- [x] **8.8.2** **Transformasi Digital Pass (E-Tiket) A4 Portrait & Autentisitas Kriptografis (`AuditionBibPdfService`, `audition_bib_tag.blade.php`, `AuditionVerification`)**:
  - Layout PDF dikonversi menjadi **1 halaman A4 Portrait** (`595.280 x 841.890`) dengan badge Helvetica-safe, micro-watermark, audit trail invoice/petugas sah, dan kode **HMAC-SHA256 `AUTH-SIG`** yang identik dengan portal verifikasi QR publik.
  - Penambahan **Header Banner Overlay** proporsional berbasis GD aspect-ratio preserving dengan scrim lembut.
- [x] **8.8.3** **E-Tiket Sepenuhnya Berbasis Aturan Event & Normalisasi Boolean `form_presets`**:
  - Perbaikan bug `FormData` boolean string (`"1"`/`"0"`) melalui `Event::normalizeFormPresetsArray()`, `StoreEventRequest::prepareForValidation()`, `Form.vue` (`toPresetBool`), `RegisterAudition.vue`, serta migrasi data `2026_11_13_160000_normalize_event_form_presets_booleans.php`.
  - Seluruh konten E-Tiket (`form_presets`, `custom_fields_schema`, `required_documents`, `audition_stages`, `dsp_cutoff_minutes`, `contact_person`/`contact_whatsapp`, tema `event_type`, dan dukungan event gratis via `AuditionParticipant::isPaid()`) sepenuhnya mengikuti konfigurasi event.
- [x] **8.8.4** **Verifikasi Pengujian Penuh**: Ditambahkan pengujian komprehensif pada `EventWorkspaceAndIndividualScreeningTest.php` dan seluruh test suite lolos 100%.

---

## REKAPITULASI STATUS PENGUJIAN AKHIR FASE 8:
- **Total Automated Tests**: **167 Tests PASS 100% (1,587 assertions)**
- **Regresi Sistem Eksisting**: **0 Failed (Zero Regression)**
- **Kompilasi Frontend (Vite)**: **Sukses tanpa error (`npm run build`)**

---

## FASE 9 — AUDIT MENYELURUH ARSITEKTUR, KEPATUHAN BEST PRACTICE (`GEMINI-2.md`), & PENEGAKAN BATASAN SOP LIFECYCLE EVENT

Fase ini mencakup hasil audit menyeluruh pasca-implementasi Fase 8 untuk menutup seluruh celah **State Machine Lifecycle Event** (`draft` → `open` → `quota_full` → `closed` → `ongoing` → `completed`), memperbaiki *dead-end* alur operasional lintas tipe event (Turnamen Tim, Audisi Individu, & Coaching Clinic/Festival), serta menegakkan kepatuhan penuh terhadap aturan arsitektur `docs/GEMINI-2.md`.

### Matriks Acuan SOP Lifecycle Status Event (Standar Baku Sistem)

| Status Event | Pendaftaran & Klaim Slot | Pembayaran & Verifikasi | Input/Revisi Roster & Dokumen | Screening Admin | Drawing Grup & Buat Jadwal | Presensi / DSP / Scouting / Skor / Seleksi | Unduh E-Sertifikat |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| **`draft`** *(Draf Internal)* | ❌ Terkunci (`404`) | ❌ Terkunci | ❌ Terkunci | ❌ Terkunci | ❌ Terkunci | ❌ Terkunci | ❌ Terkunci |
| **`open`** *(Pendaftaran Dibuka)* | ✅ Buka (Window & Kuota) | ✅ Buka | ✅ Buka (Sesuai `lock_roster_until_paid`) | ✅ Buka (Wajib Lunas) | ❌ Terkunci (Kecuali Kuota Penuh) | ❌ Terkunci | ❌ Terkunci |
| **`quota_full`** *(Kuota Penuh)* | ❌ Terkunci (Kecuali Slot Gugur) | ✅ Buka (Slot Aktif) | ✅ Buka | ✅ Buka (Wajib Lunas) | ✅ Buka (Sesi Technical Meeting) | ❌ Terkunci | ❌ Terkunci |
| **`closed`** *(Pendaftaran Ditutup)* | ❌ Terkunci | ✅ Verifikasi Sisa Antrean | ✅ Buka (Masa Pemberkasan & Grace Period) | ✅ Buka (Wajib Lunas) | ✅ Buka | ✅ Buka (Jika Tahapan Hari-H Aktif) | ❌ Terkunci (Kecuali Dirilis) |
| **`ongoing`** *(Sedang Berlangsung)* | ❌ Terkunci | ❌ Terkunci | ❌ **Wajib Terkunci Otomatis** | ⚠️ Hanya *Emergency Unlock* Admin | ⚠️ Jadwal Gugur Saja (Grup Terkunci) | ✅ **Buka Penuh** (Sesuai Cutoff & Stage Aktif) | ❌ Terkunci (Kecuali Dirilis) |
| **`completed`** *(Selesai)* | ❌ Terkunci | ❌ Terkunci | ❌ **Terkunci Permanen** | ❌ **Terkunci Permanen** | ❌ **Terkunci Permanen** | ❌ **Terkunci Permanen (Read-Only)** | ✅ **Buka Penuh** |

---

### 9.1 Sub-Fase 1: Penegakan Guard SOP & State Machine Lifecycle Event (`Event.php`, Policies, & Controllers)
- [x] **9.1.1** **Sentralisasi Helper State Machine pada Model `Event` (`app/Models/Event.php`)**:
  - Penambahan metode guard eksplisit: `isDraft()`, `isCompleted()`, `canModifyTeamRoster()` (hanya mengizinkan perubahan biodata/dokumen pemain & ofisial saat status event `open`, `quota_full`, atau `closed`), `canVerifyScreening()` (memblokir saat `draft` atau `completed`), `canManageTournamentSetup()` (memblokir drawing grup/jadwal saat `draft` atau `completed`), dan `canRunFieldOperations()` (hanya mengizinkan presensi, scouting, promosi tahapan, DSP lineup, dan input skor saat `closed` atau `ongoing`).
- [x] **9.1.2** **Kunci Otomatis Roster & Profil Tim saat Event `ongoing` / `completed` (`Team.php`, `TeamPolicy.php`, `PlayerPolicy.php`)**:
  - Memperbarui `Team::canManageRoster()` dan `TeamPolicy::update()` agar otomatis menolak penambahan/perubahan/penghapusan pemain, pelatih/ofisial, maupun profil tim begitu `$team->event->status` memasuki `'ongoing'` atau `'completed'`, meskipun admin belum mengunci roster tim satu per satu secara manual.
- [x] **9.1.3** **Guard Status Event pada Revisi Berkas Individu (`AuditionDocumentController.php`)**:
  - Memblokir unggah ulang dokumen revisi (`POST /auditions/{participant}/documents/reupload`) apabila `$participant->event->status` sudah `'ongoing'`, `'completed'`, atau `'draft'`, di samping validasi `reupload_grace_hours` yang sudah ada.
- [x] **9.1.4** **Guard Status Event pada Drawing Grup, Kunci Grup, Jadwal Pertandingan, Skor, & DSP (`TournamentController.php`, `MatchLineupController.php`)**:
  - Memblokir `generateGroups()`, `toggleGroupLock()`, `moveTeamGroup()`, `generateGroupMatches()`, `generateKnockoutMatches()`, `storeMatch()`, `rescheduleMatch()`, dan `destroyMatch()` ketika `$event->status` masih `'draft'` atau sudah `'completed'`.
  - Memastikan `MatchLineupController::saveLineup()` dan `TournamentController::updateMatchResult()` hanya diizinkan saat event berstatus `closed` atau `ongoing` (`canRunFieldOperations()`).
- [x] **9.1.5** **Guard Status Event pada Gatekeeper Check-in, Talent Scouting, & Seleksi Tahapan (`AuditionAttendanceController.php`, `AuditionScoutingController.php`, `AuditionQualificationController.php`)**:
  - Mewajibkan `$event->canRunFieldOperations()` (`in_array($event->status, ['closed', 'ongoing'], true)`) pada endpoint scan QR/check-in kehadiran, input nilai *Talent Scout*, kunci sesi, dan promosi/eliminasi tahapan.

### 9.2 Sub-Fase 2: Penutupan Celah SOP Screening Individu & Kelayakan E-Sertifikat
- [x] **9.2.1** **Kewajiban Lunas (`isPaid()`) & Guard Status Event pada Verifikasi Screening (`ScreeningController`)**:
  - Memvalidasi `$participant->isPaid()` sebelum admin dapat menetapkan status `screening_status = 'passed'` pada `verifyAuditionParticipant()` (mencegah peserta belum lunas memperoleh Digital Pass / E-Tiket aktif), sekaligus menjaga `bib_number` tetap opsional sesuai arsitektur Sub-Fase 8.7.1.
  - Menerapkan guard `$event->canVerifyScreening()` pada `verifyPlayer()`, `verifyDocument()`, `unlockPlayer()`, `verifyAuditionDocument()`, `verifyAuditionParticipant()`, `unlockAuditionParticipant()`, serta `TeamManagementController::verifyOfficial()`.
- [x] **9.2.2** **Perbaikan Syarat Unduh E-Sertifikat untuk Coaching Clinic & Festival (`AuditionParticipant::canDownloadCertificate`)**:
  - Memperbaiki logika `canDownloadCertificate()` di `app/Models/AuditionParticipant.php`:
    - Untuk event bertipe **`coaching_clinic`** / non-audisi: peserta berhak mengunduh **Piagam Partisipasi Resmi** apabila aktif, lunas (`isPaid()`), lolos screening (`passed`), sertifikat telah dirilis (`certificates_released || event->status === 'completed'`), dan **tercatat hadir (`present`) pada minimal 1 sesi kehadiran (`audition_attendances`)** — tanpa mewajibkan status eliminasi `day_1_passed`/`day_2_passed`/`final_selected`.
    - Untuk event bertipe **`audition`**: mendukung pengecekan kehadiran tahapan final dinamis (`max(stage_number)`).

### 9.3 Sub-Fase 3: Perbaikan Bug Fungsional Kritis (Gatekeeper Route, Scouting Session Lock, & Dynamic Multi-Stage Qualification)
- [x] **9.3.1** **Perbaikan Bug `RouteNotFoundException` pada Gatekeeper Attendance (`AuditionAttendanceController.php`)**:
  - Mengganti pemanggilan `route('audition.birth-certificate', ...)` yang tidak terdaftar di `routes/web.php` dengan route pas foto publik terotorisasi (`route('public.audition.verify.photo', ['code' => $p->screening_code])` atau `null` jika belum tersedia).
- [x] **9.3.2** **Perbaikan Logika `grade()` & `lockSession()` pada Panel Talent Scout (`AuditionScoutingController.php`)**:
  - Memvalidasi bahwa tahapan (`AuditionStage`) sedang aktif (`is_active === true` dan `is_completed === false`) sebelum scout dapat menyimpan nilai atau mengunci sesi.
  - Memperbaiki `lockSession()` agar mengunci evaluasi spesifik milik scout yang bersangkutan (`where('audition_stage_id', ...)->where('scout_id', $scout->id)`) dan mengecek status kunci per-scout (`$isSessionLocked`), serta menolak penguncian apabila scout belum menilai satu pun peserta pada sesi tersebut.
- [x] **9.3.3** **Dukungan Transisi Tahapan Dinamis (1-Stage, 2-Stage, & 3-Stage) & Transaksi Atomik (`AuditionQualificationController::advanceStage`, `Qualifications.vue`)**:
  - Memperbaiki aturan transisi menuju `final_selected` agar menyesuaikan jumlah tahapan (`audition_stages`) pada event:
    - Event 1 Tahap: mengizinkan `registered` → `final_selected`.
    - Event 2 Tahap: mengizinkan `day_1_passed` → `final_selected`.
    - Event 3+ Tahap: mengizinkan `day_2_passed` → `final_selected`.
  - Menyesuaikan tombol aksi `Qualifications.vue` secara dinamis mengikuti `props.stages.length` dan membungkus perubahan status peserta + `AuditLog` dalam satu `DB::transaction()`, baru kemudian mengirim notifikasi email setelah commit berhasil.

### 9.4 Sub-Fase 4: Kepatuhan Penuh Arsitektur `GEMINI-2.md` Rule 2.3 (Eliminasi Inline `$request->validate()`)
- [x] **9.4.1** Ekstraksi seluruh 9 pemanggilan validasi inline pada `Admin/TournamentController.php` ke class `FormRequest` khusus di `app/Http/Requests/Admin/Tournament/`.
- [x] **9.4.2** Ekstraksi seluruh 4 pemanggilan validasi inline pada `Admin/TeamManagementController.php` ke class `FormRequest` khusus di `app/Http/Requests/Admin/Team/`.
- [x] **9.4.3** Ekstraksi validasi inline pada `Admin/SystemSettingController.php`, `Admin/PaymentVerificationController.php`, `Admin/RolePermissionController.php`, dan `Admin/DashboardController.php` ke class `FormRequest` masing-masing di `app/Http/Requests/Admin/`.
- [x] **9.4.4** Ekstraksi validasi inline pada `Manager/MatchLineupController.php`, `Manager/DashboardController.php`, dan `Auth/PasswordResetController.php` ke class `FormRequest` masing-masing (**terverifikasi 0 pemanggilan `->validate(` di seluruh `app/Http/Controllers/`**).

### 9.5 Sub-Fase 5: Pengujian Regresi & Automated Test Fase 9
- [x] **9.5.1** Pembuatan suite pengujian fitur baru [`Phase9SopLifecycleAndBestPracticesTest.php`](file:///Users/macbook/Application/ytevent/tests/Feature/Phase9SopLifecycleAndBestPracticesTest.php) (8 skenario uji, 51 assertions).
- [x] **9.5.2** Eksekusi penuh seluruh *test suite* PHPUnit (**175 Tests PASS 100%, 1,638 assertions, 0 failed**) dan kompilasi frontend `npm run build` sukses dalam `2.99s`.

---

## FASE 10 — HARDENING MODUL PEMBAYARAN, GATEWAY PROVIDER SEPARATION, & REKONSILIASI REFUND TERSTRUKTUR

Fase ini menindaklanjuti audit keamanan dan keandalan modul pembayaran (`Midtrans`, `Xendit`, `Transfer Manual`, `Webhook`, `Approval Finance`, & `Refund`) pada skala prioritas **P0, P1, P2**, sekaligus menutup temuan bug operasional peserta audisi individu.

### 10.1 Prioritas P0 — Pemisahan Provider & Validasi Ketat Webhook
- [x] **10.1.1** **Pemisahan Mode Pembayaran & Provider Gateway (`PaymentService`, `UpdatePaymentGatewaySettingsRequest`, `SystemSettingController`, `Manager\PaymentController`, `PublicEventController`)**:
  - Menambahkan helper `isGatewayMode()`, `activeGatewayProvider()`, `xenditSecretKey()`, `xenditCallbackToken()`, dan `isProviderEnabled()` untuk menolak provider yang tidak aktif atau belum dikonfigurasi sebelum memanggil API gateway.
- [x] **10.1.2** **Validasi Kesesuaian Provider Callback Webhook (`PaymentService::processVerifiedGatewayCallback`)**:
  - Menolak sinyal callback (`403 Forbidden`) apabila `payment.method` sudah terikat ke provider berbeda atau sedang berstatus `waiting_verification`, sembari tetap mendukung invoice legacy (`method === null`).
- [x] **10.1.3** **Validasi Nominal Ketat Terhadap `total_amount` (`config/payment.php`, `PaymentService.php`)**:
  - Memperketat pencocokan nominal callback terhadap `total_amount` dengan batas `payment.amount_tolerance` (default Rp1): memblokir *underpayment* (termasuk celah pembayaran pokok tanpa `gateway_fee`) dan menandai *overpayment* pada settlement sebagai `refund_required` (`AuditLog` `payment.gateway.overpayment_flagged`) tanpa mengaktifkan tim/peserta.

### 10.2 Prioritas P1 — Validasi Saluran Resmi, Non-Silent Fallback, & Eksekusi Gateway di Luar DB Transaction
- [x] **10.2.1** **Validasi Enum & Normalisasi `payment_channel` (`InitiateGatewayPaymentRequest`, `PaymentService`)**:
  - Menambahkan `allowedChannelsForProvider()`, `resolveProviderChannel()`, `MIDTRANS_CHANNEL_ALIASES`, `XENDIT_AVAILABLE_CHANNELS`, dan `XENDIT_CHANNEL_ALIASES`, serta menolak channel arbitrary pada validasi FormRequest.
- [x] **10.2.2** **Penghapusan Silent Fallback `enabled_payments` pada Midtrans**:
  - Menghapus fallback otomatis yang menghapus filter `enabled_payments` saat channel pilihan ditolak Midtrans guna mencegah ketidaksesuaian kanal pilihan user dan selisih tarif biaya MDR.
- [x] **10.2.3** **Refaktor Panggilan HTTP Gateway ke Luar `DB::transaction()` (`initiateGatewayPayment`, `executeGatewayInitiationRequest`)**:
  - Memecah proses menjadi transaksi singkat (claim `gateway_initiation_lock` TTL 30 detik), eksekusi HTTP di luar transaksi database, dan transaksi singkat finalisasi agar tidak menahan row lock selama latensi provider.

### 10.3 Prioritas P2, Bug Individu, & Verifikasi Pengujian Penuh
- [x] **10.3.1** **Guard Kuota saat Approval Transfer Manual (`PaymentService::approveManualPayment`, `PaymentVerificationController`)**:
  - Menjalankan `sweepExpiredForEvent()`, mengecek sisa kuota sebelum aktivasi, serta mengalihkan pembayaran ke `refund_required` (`expired`/`cancelled` pada tim/peserta terkait) apabila kuota sudah penuh.
- [x] **10.3.2** **Kolom Refund Terstruktur (`2026_11_13_210500_add_refund_columns_to_payments_table`, `Payment`, `MarkPaymentRefundedRequest`, `PaymentVerificationController`)**:
  - Menambahkan kolom `refund_amount`, `refund_reference`, `refund_method`, `refund_notes`, dan `refunded_at` beserta paparan data ke portal & ekspor CSV Finance (tetap mempertahankan kompatibilitas `rejection_reason`).
- [x] **10.3.3** **Perbaikan Bug Webhook `EXPIRED` & Email Konfirmasi Peserta Audisi Individu**:
  - Menambahkan null-check `$lockedTeam` dan sinkronisasi status `cancelled` + pengembalian `occupied_slots` sesi pada sinyal `EXPIRED`, serta penambahan `AuditionPaymentConfirmedMail` dan `EmailNotificationService::sendAuditionPaymentConfirmed()`.
- [x] **10.3.4** **Suite Test Webhook Xendit & Hardening Pembayaran Lengkap (`PaymentFlowTest.php`)**:
  - Menambahkan 8 skenario feature test baru (provider separation, channel enum, anti-silent-fallback, webhook Xendit lengkap, fee underpayment/overpayment, null-safety `EXPIRED` individu, approval manual over-quota, dan kolom refund terstruktur).

---

## REKAPITULASI STATUS PENGUJIAN AKHIR FASE 10:
- **Total Automated Tests**: **185 Tests PASS 100% (1,689 assertions)**
- **Regresi Sistem Eksisting**: **0 Failed (Zero Regression)**




