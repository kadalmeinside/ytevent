# Analisis Gap Fitur, Rekomendasi Fitur Wajib & Roadmap Pengembangan Selanjutnya (YTEvent)

> **Tanggal Audit:** 7 November 2026  
> **Cakupan Evaluasi:** Perbandingan menyeluruh antara implementasi aktual (Fase 1, Fase 2, dan Fase 3) terhadap spesifikasi `PRD.md`, `DATABASE_SCHEMA.md`, serta standar industri *Tournament & League Management System* (seperti FIFA Connect / SIAP PSSI / Persija Development).

---

## 1. Ringkasan Status Saat Ini (Yang Sudah Selesai 100%)

Saat ini fondasi **Pre-Competition (Pendaftaran -> Pembayaran -> Input Skuad -> Screening -> Akreditasi & ID Card)** sudah berjalan utuh dari hulu ke hilir:

- **Fase 1 (Core Registration, Quota Locking & Payment):**
  - Katalog Event Publik, Pendaftaran Tim dengan *Pessimistic Row-Level Locking* (`SELECT ... FOR UPDATE`), Reservasi Slot Sementara (*Temporary Slot Hold*), Pembayaran Midtrans/Sandbox & Transfer Manual, Verifikasi Bukti Bayar oleh Finance, serta CRUD Pemain & Ofisial oleh Manajer Tim.
- **Fase 2 (Screening Workbench, Accreditation PDF & Email Queue):**
  - Workspace Screening Admin *Side-by-Side*, **Modal Wizard Verifikasi Dokumen Cepat (*Auto-Advance*)**, Penguncian Skuad Final (`locked_roster`), Cetak **Album Roster Sheet (PDF)** & **ID Card Lanyard (PDF)**, Halaman Verifikasi QR Code Publik (`/verify/player/{code}`), serta Antrean Log Email Transaksional (`ScreeningResultMail`, `RosterLockedMail`, dll.) dengan fitur *Retry*.
- **Fase 3 (In-Web Notifications, Live Counter Badges, Configurable Event Rules, SEO/GEO & 360° Team Dossier):**
  - Live Counter Badges di Sidebar Admin & Manajer, Banner Peringatan Akreditasi Otomatis (`WorkflowStepBanner`), Stepper 5 Tahap Kesiapan Tim, serta Halaman **Dossier 360° Detail Tim (`/admin/teams/{team}`)** untuk Panitia.
  - Pengaturan dinamis per event untuk **Syarat Minimal Kiper (`min_gk_per_team`: `1` atau `2` GK)** serta **Visibilitas & Kewajiban Lisensi Pelatih Kepala (`show_coach_license` & `require_coach_license`)** dari form pendaftaran hingga validasi kunci skuad final.
  - Optimasi **SEO, Server-Side Open Graph (Thumbnail Banner, Judul & Deskripsi Event untuk Facebook/WhatsApp/X), Schema.org JSON-LD (`SportsEvent`), AI Generative Engine (`/llms.txt`, `/llms-full.txt`, `/sitemap.xml`, `/robots.txt`)**, serta proteksi **`noindex, nofollow`** penuh pada `/admin/login` dan seluruh rute internal.
- **Fase 4 (Kontrol Status Tim Admin, Bulk Screening, ID Card Ofisial, Eksekusi Refund, Pusat Ekspor CSV/Excel & Akun Mandiri — SELESAI 100%):**
  - Kendali Status Tim oleh Admin di `/admin/teams/{team}` (*Unlock Roster*, *Force Lock Roster*, *Diskualifikasi / Re-Aktivasi Tim*, dan *Catatan Internal Panitia*).
  - Pengajuan Screening Kolektif 1-Klik (*Bulk Submit All Ready Players*) di Portal Manajer (`Manager/Players.vue`) serta pencetakan **ID Card Pelatih & Ofisial** ber-QR Code & Nomor Lisensi di PDF Akreditasi.
  - Eksekusi penyelesaian pengembalian dana (`refund_required` -> `refunded` + `PaymentRefundedMail`) serta **Pusat Ekspor Data CSV/Excel** (Laporan Transaksi Keuangan, Rekapitulasi Tim, & Database Pemain per Event).
  - Pemulihan Kata Sandi Mandiri (`/forgot-password` & `/reset-password/{token}` via Email Queue) dan Form Ubah Kata Sandi di Profil Tim Manajer.
- **Penyempurnaan PDF Layout & Modul Simulasi Data (SELESAI 100%):**
  - Presisi ID Card 1 lembar A4 dibagi 4 kartu simetris (2x2 grid) bergaris potong rapi.
  - Album Roster Skuad 3 kartu per baris, pemisahan halaman skuad pemain dengan staf ofisial dan blok pengesahan/tanda tangan.
  - Siklus revisi dokumen mandiri tanpa perlu membuka kunci skuad keseluruhan.
  - Modul Simulasi Data Melayang (*Floating Testing/Demo Injector Tool*) untuk pengujian cepat pendaftaran, pembayaran, injeksi pemain sintetis, pengesahan massal, dan pengisian slot sisa kuota event.
- **Fase 5 (Tournament Control Center, Format 5v5–11v11, Grup & Knockout, Jadwal & Reschedule, DSP & Auto-Lock, Klasemen & E-Sertifikat — SELESAI 100%):**
  - Format pertandingan fleksibel (`match_format`: 5v5, 7v7, 8v8, 9v9, 11v11) dengan kuota starter wajib & batas menit penutupan DSP (`dsp_cutoff_minutes`).
  - Undian grup otomatis, **Kunci Pembagian Grup (`is_group_locked`)**, serta **Pindah Grup Manual (`moveTeamGroup`)** sebelum bertanding.
  - Penjadwalan Round-Robin otomatis, **Reschedule Pertandingan**, dan **Generator Fase Gugur Otomatis (*Cross-Group Seeding*)**.
  - **Daftar Susunan Pemain (DSP)** dengan penegakan kuota starter, minimal 1 GK, blokir suspensi kartu disiplin, **Auto-Lock DSP** (laga berlangsung / lewat cut-off), dan cetak PDF Match Sheet.
  - Input skor akhir, adu penalti, gol/kartu, serta kalkulasi klasemen otomatis dengan tie-breaker standar sepak bola.
  - Matchday QR Scanner (`/admin/qr-scanner`), pengumuman turnamen, predikat juara tim, serta **E-Sertifikat Digital** dengan gerbang rilis panitia (`certificates_released`).

---

## 2. Status Capaian Fitur (Seluruh Fitur Inti Telah Selesai 100%)

Seluruh celah fungsional (*feature gap*) yang teridentifikasi pada audit awal telah berhasil diimplementasikan dan diverifikasi:

| Area Fitur | Status Implementasi | Lokasi Kode / Antarmuka |
| :--- | :---: | :--- |
| **Kontrol Status Tim Admin** | Selesai (100%) | `Admin/Teams/Show.vue` & `TeamManagementController` |
| **Penyelesaian Refund Finance** | Selesai (100%) | `PaymentVerificationController::markRefunded` |
| **Ekspor Data CSV / Excel** | Selesai (100%) | `exportCsv`, `exportTeamsCsv`, `exportPlayersCsv` |
| **Bulk Submit Screening Pemain** | Selesai (100%) | `Manager/Players.vue` & `PlayerController::submitAllReady` |
| **Manajemen Kata Sandi Mandiri** | Selesai (100%) | `/forgot-password`, `/reset-password`, & Profil Manajer |
| **ID Card Ofisial & QR Verifikasi** | Selesai (100%) | `id_cards.blade.php` & `/verify/official/{code}` |
| **Format Turnamen (5v5 s/d 11v11)**| Selesai (100%) | `Admin/Events/Form.vue` & `EventController` |
| **Kunci Grup & Pindah Grup Manual** | Selesai (100%) | `Admin/Events/Tournament.vue` & `TournamentController` |
| **Jadwal & Reschedule Pertandingan**| Selesai (100%) | `rescheduleMatch` & modal reschedule |
| **Generator Fase Gugur (Cross-Group)**| Selesai (100%)| `generateKnockoutMatches` & `StandingsCalculatorService` |
| **DSP Lineup & Auto-Lock Cutoff** | Selesai (100%) | `Manager/Matches.vue` & `MatchLineupController` |
| **Live Match Center & Klasemen** | Selesai (100%) | `Public/EventDetail.vue` & `StandingsCalculatorService` |
| **Matchday QR Scanner Meja Wasit** | Selesai (100%) | `Admin/Screening/QrScanner.vue` |
| **E-Sertifikat Digital & Release Gating**| Selesai (100%)| `player_certificate.blade.php` & `toggleCertificateRelease` |
| **Modul Simulasi Data Melayang** | Selesai (100%) | `SimulationFloatingTool.vue` & `SimulationController` |

---

## 3. Rekomendasi Fitur Wajib Berikutnya (*Must-Have Core Features — Fase 4*)

Fitur-fitur di bawah ini **sangat direkomendasikan sebagai prioritas wajib (Fase 4)** karena menyempurnakan siklus administrasi, keuangan, dan hari-H *Technical Meeting*:

| No | Nama Fitur Wajib | Area | Masalah yang Diselesaikan |
| :--- | :--- | :--- | :--- |
| **1** | **Panel Kendali Status Tim oleh Admin (*Unlock Roster, Force Lock, Disqualify & Admin Notes*)** | Admin (`/admin/teams/{team}`) | Mengatasi situasi darurat di lapangan ketika tim yang sudah `locked_roster` perlu revisi atas izin panitia, atau tim melanggar regulasi dan harus didiskualifikasi. |
| **2** | **Tombol *Bulk Submit Screening* untuk Manajer Tim** | Manajer (`/manager/players`) | Memangkas waktu manajer: 1 klik untuk mengajukan seluruh pemain berstatus `draft` yang berkasnya sudah lengkap ke antrean screening panitia. |
| **3** | **Cetak ID Card Pelatih & Ofisial + QR Verifikasi Ofisial** | Manajer & Admin (PDF Engine) | Di lapangan, Pelatih Kepala dan Ofisial di *bench* juga wajib mengenakan ID Card resmi ber-QR Code agar wasit/pengawas pertandingan tahu siapa yang berhak duduk di bangku cadangan. |
| **4** | **Eksekusi Penyelesaian Refund (`refund_required` -> `refunded`)** | Admin Finance (`/admin/payments/transactions`) | Menutup siklus akuntansi untuk tim yang pembayarannya masuk terlambat setelah kuota penuh, lengkap dengan bukti pengembalian dana. |
| **5** | **Pusat Ekspor Data (Excel / CSV): Laporan Keuangan, Daftar Tim, & Database Pemain** | Admin Finance & Operasional | Panitia membutuhkan file spreadsheet (`.xlsx` / `.csv`) untuk rekonsiliasi bank, pembuatan polis asuransi pemain, dan arsip PSSI. |
| **6** | **Manajemen Akun Mandiri (Ubah Password & Lupa Password via Email)** | Auth & Portal Manajer | Menghindari manajer terkunci dari akunnya menjelang penutupan pendaftaran/akreditasi. |

---

## 4. Rekomendasi Fitur Pelaksanaan Kompetisi & Hari Pertandingan (*Tournament & Matchday — Fase 5*)

Pada `PRD.md` bagian 2.2 disebutkan adanya tab **"Jadwal / Bagan (jika sudah berjalan)"** di halaman publik `/events/{slug}`. Agar platform YTEvent tidak berhenti setelah kartu ID dicetak, melainkan terus dipakai selama turnamen berlangsung, berikut adalah fitur pelaksanaan kompetisi:

1. **Generator Grup & Bagan Gugur (*Group Stage & Knockout Bracket*)**:
   - Pembagian grup otomatis (*drawing* acak atau manual *seeding* oleh Admin) dari daftar tim berstatus `locked_roster`.
   - Tabel Klasemen Grup otomatis (*Main, Menang, Seri, Kalah, GM, GK, Selisih Gol, Poin, Head-to-Head*).
   - Bagan Fase Gugur (*Perempat Final, Semifinal, Perebutan Juara 3, Final*).
2. **Manajemen Jadwal Pertandingan (*Match Fixtures & Lapangan*)**:
   - Pengaturan tanggal, jam *kick-off*, nomor lapangan/venue (misal Lapangan A, Lapangan B pada Festival U-12), dan penugasan wasit.
3. **Daftar Susunan Pemain Pertandingan (*Digital Match Lineup / DSP*)**:
   - Sebelum kick-off, manajer memilih *Starting XI* dan *Substitutes* dari daftar pemain yang sudah `passed` & terakreditasi.
   - Otomatis memblokir pemilihan pemain yang sedang terkena akumulasi kartu merah/kuning.
4. **Input Skor, Pencetak Gol, & Kartu Disiplin (*Match Report*)**:
   - Admin / Pengawas Pertandingan menginput skor akhir, menit pencetak gol, serta kartu kuning/merah.
   - Tampil secara *real-time* di halaman publik `/events/{slug}` (Tab Jadwal, Klasemen, dan *Top Scorer*).

---

## 5. Rekomendasi Fitur Pelengkap & Peningkatan Nilai Tambah (*Nice-to-Have — Fase 6*)

Fitur pelengkap untuk meningkatkan citra profesionalisme penyelenggara (Persija Development):

1. **Papan Pengumuman & Undangan Technical Meeting (*In-Web Broadcast Bulletin*)**:
   - Admin dapat mempublikasikan pengumuman per event (misal: *"Link Zoom / Lokasi Technical Meeting & Pembagian Grup"* atau *"Perubahan Jadwal Lapangan karena Cuaca"*) yang tampil di Dashboard Manajer dan dikirim via Email.
2. **Pemilihan Warna Jersey Tim (Home & Away Kit Colors)**:
   - Manajer menginput warna jersey Utama (*Home*), Cadangan (*Away*), dan Kiper pada profil tim untuk mencegah bentrok warna kostum saat jadwal pertandingan dirilis.
3. **Sertifikat Partisipasi & Piagam Juara Digital (PDF Generator)**:
   - Setelah status event berubah menjadi `completed`, setiap pemain yang terakreditasi dapat mengunduh **E-Certificate Resmi** berlogo kompetisi & tanda tangan digital penyelenggara (sangat bernilai tinggi bagi pemain SSB/Akademi usia muda untuk portofolio/jalur prestasi sekolah).
4. **Integrasi Notifikasi WhatsApp Gateway (Fonnte / Wablas / Official WA API)**:
   - Melengkapi antrean email dengan pesan WhatsApp otomatis ke nomor `manager_whatsapp` saat tagihan hampir kedaluwarsa, saat ada berkas pemain yang ditolak screening, atau saat seluruh pemain sudah lolos dan siap dikunci.
5. **Mode Pemindai Cepat Hari-H (*Matchday QR Scanner Mode*)**:
   - Halaman khusus panitia lapangan dengan kamera langsung (*HTML5 Camera QR Scanner*) untuk memindai kartu ID pemain secara beruntun saat pemeriksaan *screening* fisik di lorong lapangan sebelum *kick-off*.

---

## 6. Rekomendasi Urutan Eksekusi Selanjutnya

Jika kita ingin melanjutkan ke **Fase 4**, urutan pengerjaan yang paling berdampak tinggi dan langsung menyempurnakan sistem yang ada saat ini adalah:

1. **Tahap 4.1 — Kontrol Administratif Tim oleh Admin (`/admin/teams/{team}`)**:
   - Fitur *Unlock Final Roster* (membuka kembali kunci skuad tim), *Force Lock Roster*, *Disqualify Team*, dan *Catatan Internal Panitia*.
2. **Tahap 4.2 — *Bulk Submit Screening* & Kelengkapan Akreditasi Ofisial**:
   - Tombol *Ajukan Semua Pemain Siap Screening* di `Manager/Players.vue` + pencetakan **ID Card Pelatih & Ofisial** di PDF Akreditasi.
3. **Tahap 4.3 — Penyelesaian Refund Finance & Ekspor Laporan (CSV/Excel)**:
   - Alur perubahan `refund_required` -> `refunded` + tombol Export CSV/Excel untuk Transaksi Keuangan, Daftar Tim, dan Master Pemain per Event.
4. **Tahap 4.4 — Manajemen Password Mandiri (Forgot Password & Change Password)**:
   - Fitur *Lupa Kata Sandi* di halaman login dan *Ubah Kata Sandi* di portal Manajer & Admin.

