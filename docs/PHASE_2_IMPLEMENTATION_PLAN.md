# PHASE_2_IMPLEMENTATION_PLAN.md - Rencana Implementasi Teknis & Arsitektur Fase 2

Dokumen ini memuat arsitektur teknis terperinci, alur kerja, spesifikasi endpoint, rancangan antarmuka pengguna (UI/UX), model data, serta strategi pengujian otomatis untuk pengerjaan **Fase 2: Panel Screening Dokumen Admin, Siklus Revisi Terarah, Akreditasi Skuad (PDF & QR), dan Antrean Email**.

---

## 1. Tujuan & Ruang Lingkup Fase 2

Fase 2 berfokus pada **operasional pemeriksaan keabsahan data (*screening*)**, verifikasi berkas anti-pencurian umur, pengesahan skuad pertandingan (*matchday accreditation*), serta otomatisasi notifikasi latar belakang:

1. **Panel Antrean & Review Screening Admin (`/admin/screening`)**:
   - Dashboard antrean tim dan pemain menunggu verifikasi per event.
   - *Split-View Document Reviewer*: Biodata pemain berdampingan langsung dengan foto/scan dokumen asli (Akta Lahir, KK, NISN/KIA) dengan zoom & rotasi.
   - Deteksi otomatis kesesuaian cut-off usia (*Age Cut-Off Check*) di layar panitia.
   - Validasi status granular per dokumen (`valid` / `invalid`).
   - Keputusan akhir pemain: **Loloskan (Sah)** -> `passed` + `is_locked = true` + penerbitan `screening_code`, atau **Tolak (Revisi)** -> `rejected` + wajib catatan alasan penolakan min. 10 karakter.
   - Pencatatan histori audit ke `screening_logs`.
   - Fitur darurat *Unlock Player* oleh Superadmin / Screening Officer.
2. **Siklus Revisi Terarah di Portal Manajer (`Selective Re-submission`)**:
   - Penandaan jelas kartu pemain yang `rejected` beserta catatan spesifik penolakan panitia.
   - Akses edit berkas dibuka kembali khusus untuk pemain yang `rejected`.
   - Pengajuan ulang (*Re-submit*) hanya untuk pemain yang telah diperbaiki.
3. **Validasi Skuad Final & Akreditasi Resmi (`/manager/accreditation` & `/admin/teams/{team}/accreditation`)**:
   - Aturan sepak bola: minimal kuota pemain terpenuhi dan minimal 2 Penjaga Gawang (`GK`) sah.
   - Penguncian Skuad Final (*Lock Roster*) sebelum *Technical Meeting*.
   - Generator PDF Resmi: **Team Roster Sheet** (album foto pemain & ofisial untuk wasit) dan **ID Card** akreditasi (lanyard turnamen).
   - Kode QR verifikasi keabsahan pemain digital.
4. **Antrean Notifikasi Email (*Email Queue*) & Audit Logs (`/admin/system/email-logs`)**:
   - Pengiriman hasil screening via antrean latar belakang (`ShouldQueue`).
   - Monitoring antrean email dan tombol *Retry* untuk email yang gagal.

---

## 2. Peta Rute & Spesifikasi API Endpoint Fase 2

### 2.1 Area Internal Panitia (Role: `superadmin`, `screening_officer`)

| HTTP Method | URI | Nama Route | Middleware / Permission | Deskripsi Aksi |
| :--- | :--- | :--- | :--- | :--- |
| `GET` | `/admin/screening` | `admin.screening.index` | `permission:screening.view_queue` | Halaman antrean tim per event dengan ringkasan status screening |
| `GET` | `/admin/screening/events/{event}/teams/{team}` | `admin.screening.team` | `permission:screening.view_queue` | Panel review screening seluruh pemain dari tim tertentu |
| `POST` | `/admin/screening/players/{player}/verify` | `admin.screening.player.verify` | `permission:screening.verify_player` | Meloloskan (`passed`) atau menolak (`rejected`) pemain |
| `POST` | `/admin/screening/documents/{document}/verify` | `admin.screening.document.verify` | `permission:screening.verify_player` | Menandai keabsahan per berkas dokumen (`valid`/`invalid`) + catatan |
| `POST` | `/admin/screening/players/{player}/unlock` | `admin.screening.player.unlock` | `permission:screening.unlock_player` | Membuka kembali kunci pemain yang sudah lolos untuk perbaikan darurat |
| `GET` | `/admin/teams/{team}/accreditation/pdf` | `admin.teams.accreditation.roster` | `permission:teams.view_all` | Unduh PDF Album Roster Tim Pengesahan Wasit |
| `GET` | `/admin/system/email-logs` | `admin.email-logs.index` | `permission:email_logs.manage` | Pemantauan antrean pengiriman email sistem |
| `POST` | `/admin/system/email-logs/{log}/retry` | `admin.email-logs.retry` | `permission:email_logs.manage` | Pemicu kirim ulang email yang gagal |

### 2.2 Area Portal Manajer Tim (Role: `team_manager`)

| HTTP Method | URI | Nama Route | Middleware / Policy | Deskripsi Aksi |
| :--- | :--- | :--- | :--- | :--- |
| `POST` | `/manager/teams/{team}/lock-roster` | `manager.teams.lock-roster` | `can:update,team` | Penguncian skuad final setelah seluruh syarat minimal terpenuhi |
| `GET` | `/manager/accreditation` | `manager.accreditation.index` | `can:view,team` | Halaman akreditasi tim & unduh kartu ID / album pemain |
| `GET` | `/manager/accreditation/roster-sheet` | `manager.accreditation.roster-sheet` | `can:view,team` | Unduh PDF resmi Team Roster Sheet untuk pemeriksaan wasit |
| `GET` | `/manager/accreditation/id-cards` | `manager.accreditation.id-cards` | `can:view,team` | Unduh PDF Kartu Akreditasi (ID Card) seluruh pemain & ofisial sah |
| `GET` | `/verify/player/{code}` | `public.player.verify` | Publik | Verifikasi keabsahan pemain digital melalui pemindaian Kode QR |

---

## 3. Spesifikasi Arsitektur & Model Data

### 3.1 Kolom & Relasi Tambahan

1. **Tabel `players`**:
   - `screening_status`: `enum('draft', 'submitted', 'passed', 'rejected')` (Sudah ada).
   - `is_locked`: `boolean` default `false` (Sudah ada).
   - `rejection_reason`: `text nullable` (Sudah ada).
   - `screening_code`: `string(32) unique nullable` (Kode verifikasi acak alfanumerik untuk QR code, contoh: `YT-EVT01-PL260841`).
   - `screening_verified_by`: `foreignId('users')->nullable()` (Sudah ada).
   - `screening_verified_at`: `timestamp nullable` (Sudah ada).

2. **Tabel `player_documents`**:
   - `validation_status`: `enum('pending', 'valid', 'invalid')` default `'pending'`.
   - `notes`: `text nullable` (Catatan verifikator per dokumen).

3. **Tabel `screening_logs`**:
   - `id`: `bigIncrements`.
   - `player_id`: `foreignId('players')->constrained()->cascadeOnDelete()`.
   - `user_id`: `foreignId('users')->constrained()`.
   - `previous_status`: `string(20)`.
   - `new_status`: `string(20)`.
   - `reason`: `text nullable`.
   - `metadata`: `json nullable` (Mencatat status dokumen per berkas saat verifikasi).
   - `created_at`: `timestamp`.

4. **Tabel `teams`**:
   - `screening_status`: `enum('not_started', 'in_review', 'partially_rejected', 'all_passed')`.
   - `roster_locked_at`: `timestamp nullable` (Waktu penguncian skuad final oleh manajer).

---

## 4. Rincian Langkah Implementasi (Step-by-Step Milestones)

### Milestone 2.1: Panel Antrean & Review Screening Admin (`/admin/screening`) [SELESAI - COMMIT 5cabd5a]
- [x] **2.1.1 Backend - Controller & FormRequests**:
  - Telah dibuat `app/Http/Controllers/Admin/ScreeningController.php` dengan aksi `index`, `teamReview`, `verifyPlayer`, `verifyDocument`, dan `unlockPlayer`.
  - Telah dibuat `app/Http/Requests/Admin/VerifyPlayerRequest.php` (Validasi aksi `action: passed/rejected`, validasi `rejection_reason` wajib diisi min. 10 karakter saat status `rejected`).
  - Telah dibuat `app/Http/Requests/Admin/VerifyDocumentRequest.php` (Validasi `validation_status: valid/invalid`, `notes`).
- [x] **2.1.2 Backend - Service & State Transition**:
  - Telah dibuat `app/Services/ScreeningService.php`:
    - Verifikasi pemain & penerbitan `accreditation_code` unik acak alfanumerik saat pemain `passed`.
    - Sinkronisasi otomatis `teams.screening_status` (`all_passed` jika semua pemain lolos, `partially_rejected` jika ada pemain gagal, `in_review` jika masih ada yang diperiksa).
    - Pencatatan transaksi audit ke `screening_logs` (actor_id, player_id, from_status, to_status, reason).
    - Fitur `unlockPlayer` (mengembalikan status ke `draft` dan melepas `is_locked = false`).
- [x] **2.1.3 Frontend - Halaman Antrean Screening (`Admin/Screening/Index.vue`)**:
  - Filter interaktif per Event dan per Status Tim.
  - Kartu List View Tim berstatus `in_review`, `partially_rejected`, dan `all_passed`.
  - Progres verifikasi per tim (contoh: *"14 / 18 Pemain Telah Diperiksa"*).
  - Tombol aksi: *"Periksa Berkas Skuad"*.
- [x] **2.1.4 Frontend - Antarmuka Split-View Reviewer (`Admin/Screening/TeamReview.vue`)**:
  - **Panel Kiri (Biodata & Validasi Usia)**:
    - Pas foto pemain, Nama Lengkap, Nomor Punggung, Posisi, NIK, NISN, Tempat/Tanggal Lahir.
    - **Age Cut-off Comparison Badge**: Menghitung usia otomatis terhadap tanggal mulai turnamen dan membandingkan dengan rentang tanggal lahir resmi event menggunakan SVG icon bersih (bukan unicode).
    - Histori audit log mini pemain.
  - **Panel Kanan (Document Viewer & Canvas)**:
    - Pratinjau gambar/PDF interaktif dokumen terpilih (Akta Kelahiran, Kartu Keluarga, KIA/Rapor) yang dimuat via rute privat.
    - Kontrol viewer: Zoom in/out, reset, iframe PDF tertanam, dan tombol *"Modal View"* untuk inspeksi dokumen definisi tinggi.
    - Tombol penilaian per berkas (`Dokumen Sah` / `Butuh Revisi`) dengan SVG icon dan disable state jika pemain sudah sah.
  - **Panel Bawah (Sticky Decision Bar)**:
    - Tombol hijau: **"Loloskan Pemain (Sah)"** (dilengkapi disable rules: tanggal lahir invalid, dokumen berstatus invalid, atau pemain terkunci).
    - Tombol merah: **"Tolak / Minta Revisi"** (modal alasan penolakan wajib min. 10 karakter).
    - Tombol abu-abu: **"Buka Kunci (Unlock)"** untuk pemain sah yang membutuhkan perbaikan darurat.
    - Navigasi cepat ke pemain berikutnya/sebelumnya tanpa reload halaman.

### Milestone 2.2: Siklus Revisi Terarah di Portal Manajer [SELESAI - COMMIT 882f98d & 55dc815]
- [x] **2.2.1 Frontend - Visualisasi Status Rejected di `Manager/Players.vue`**:
  - Pemain berstatus `rejected` diberi highlight border Rose-400 yang tegas, ring aksen, dan background lembut.
  - Banner peringatan merah muda memuat alasan spesifik penolakan dari petugas screening.
  - Chip dokumen menampilkan status granular: `✓ Sah: [Dokumen]`, `✕ Revisi: [Dokumen]`, `⏳ Menunggu`, atau `✕ Belum` menggunakan icon SVG (bebas unicode emoji).
  - Tombol aksi utama: *"Ajukan Ulang Perbaikan"*.
- [x] **2.2.2 Backend & Frontend - Selective Re-submission**:
  - Endpoint `manager.players.submit` dimutakhirkan agar dapat mengajukan ulang pemain yang telah direvisi kembali ke status `submitted`.
  - Notifikasi toast sukses dan perubahan status tim kembali ke `in_review` via `ScreeningService::syncTeamScreeningStatus`.
  - Manajer dapat menghapus pemain rejected jika terbukti tidak lolos cut-off usia untuk digantikan pemain baru.
- [x] **2.2.3 Komponen Terintegrasi DocumentViewerModal (`DocumentViewerModal.vue`)**:
  - Viewer modal terpadu untuk berkas scan gambar (JPG/PNG) dengan kontrol zoom & putar 90°, serta berkas dokumen PDF dengan embedded iframe.
  - Diintegrasikan di Portal Admin Screening dan Portal Manajer Tim.

### Milestone 2.3: Validasi Skuad Final & Akreditasi Resmi (PDF & QR Code)
- [ ] **2.3.1 Backend - Validasi Roster Final & Penguncian Tim**:
  - Buat `app/Http/Controllers/Manager/AccreditationController.php`.
  - Pemeriksaan syarat sebelum penguncian skuad:
    - Minimal jumlah pemain lolos (`passed >= event.min_players_per_team`).
    - Minimal 2 pemain lolos berposisi Penjaga Gawang (`GK`).
    - Seluruh ofisial terdaftar lengkap.
  - Endpoint `POST /manager/teams/{team}/lock-roster` mengunci tim secara permanen (`roster_locked_at = now()`).
- [ ] **2.3.2 Backend - Engine Generator PDF (DomPDF / Snappy)**:
  - Template PDF **Team Roster Sheet**: Lembar album pengesahan wasit (Logo tim, nama manajer & pelatih, daftar foto pemain lolos, nomor punggung, tanggal lahir, NIK, dan QR Code).
  - Template PDF **ID Card**: Kartu akreditasi turnamen standar lanyard (Foto, Nama, Tim, Peran, Nomor Punggung, dan QR Code verifikasi).
- [ ] **2.3.3 Halaman Publik Verifikasi QR Code (`/verify/player/{code}`)**:
  - Halaman publik ringan yang dapat dibuka oleh wasit/pengawas pertandingan di pinggir lapangan saat memindai QR Code di ID Card / Roster Sheet untuk memastikan data pemain benar-benar terdaftar di database resmi Persija Development.

### Milestone 2.4: Antrean Notifikasi Email & Audit Logs
- [ ] **2.4.1 Mailable Transaksional Latar Belakang (`ShouldQueue`)**:
  - `ScreeningResultMail`: Dikirim otomatis saat panitia menyelesaikan verifikasi tim (berisi daftar pemain lolos vs daftar pemain rejected beserta catatannya).
  - `RosterLockedMail`: Notifikasi skuad terkunci dan link unduh akreditasi.
- [ ] **2.4.2 Portal Pemantauan Email Panitia (`/admin/system/email-logs`)**:
  - Tampilan Card List riwayat pengiriman email dari tabel `email_notification_logs`.
  - Aksi *Retry* pengiriman jika status `failed`.

---

## 5. Rencana Pengujian Otomatis (Testing Strategy)

Target: Memperluas cakupan test suite dari 31 tests menjadi **minimal 40+ tests** mencakup:
1. `ScreeningQueueTest`: Petugas screening dan superadmin dapat mengakses `/admin/screening`, manajer tim ditolak (`403 Forbidden`).
2. `PlayerVerificationTest`: Verifikasi lolos mengubah status ke `passed`, mengunci `is_locked`, dan menghasilkan `screening_code`.
3. `PlayerRejectionTest`: Verifikasi tolak mewajibkan alasan penolakan, mengubah status ke `rejected`, dan membuka kembali hak edit manajer.
4. `EmergencyUnlockTest`: Superadmin dapat membuka kunci pemain yang salah lolos menggunakan tombol *Unlock*.
5. `RosterLockValidationTest`: Penguncian skuad tim ditolak jika kuota minimal pemain atau minimal 2 GK belum terpenuhi.
6. `AccreditationAccessTest`: Akreditasi dan cetak PDF hanya dapat diakses untuk tim yang pembayarannya lunas dan skuadnya sah.
7. `QrVerificationTest`: Endpoint verifikasi publik mengembalikan data pemain yang sah sesuai kode acak.

---

## 6. Standar Desain & Aturan Kepatuhan

- Tetap mematuhi aturan strict `GEMINI-2.md` dan `UI_UX.md`:
  - Larangan total penggunaan dot indicator status bulat.
  - Larangan animasi `pulse`, `ping`, atau efek berkedip (*blinking*).
  - **Larangan total emoticon/emoji unicode:** Tidak boleh menggunakan emoji mentah (seperti ⏳, ✓, ✕, ⚠️, 📄). Wajib menggunakan Icon SVG terstandar (Heroicons).
  - **Standardisasi Modal Viewer Berkas (`DocumentViewerModal`):** Pratinjau terpadu file gambar (zoom in/out, reset 1:1, putar 90°) dan dokumen PDF (embedded iframe).
  - **Best Practice Tombol Operasional:** Memberikan indikator disable state yang jelas disertai tooltip alasan penonaktifan tombol, proteksi *double-click*, dan konfirmasi modal untuk aksi destruktif.
  - Komponen form selalu menggunakan `<form novalidate>`.
  - Menggunakan tema warna **Persija Red-Orange Liquid Glass** (`#EA580C`, `#DC2626`).
  - Mengutamakan tata letak **Card List View** responsif bebas tumpukan data.

