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
- **Fase 3 (In-Web Notifications, Live Counter Badges & 360° Team Dossier):**
  - Live Counter Badges di Sidebar Admin & Manajer, Banner Peringatan Akreditasi Otomatis (`WorkflowStepBanner`), Stepper 5 Tahap Kesiapan Tim, serta Halaman **Dossier 360° Detail Tim (`/admin/teams/{team}`)** untuk Panitia.

---

## 2. Analisis Gap: Fitur di PRD / Schema yang Belum Dibuat atau Belum Lengkap

Berdasarkan pemeriksaan mendalam terhadap `docs/PRD.md`, `docs/DATABASE_SCHEMA.md`, dan kode saat ini, berikut adalah bagian yang **sudah ada di spesifikasi/kolom database namun belum memiliki UI atau alur aksi lengkap**:

### 2.1 Manajemen Status Tim & Catatan Internal oleh Admin (`teams.manage_status`)
- **Kondisi Saat Ini:** Di database `teams` sudah ada kolom `status` (`disqualified`, `expired`, `active`, `locked_roster`), kolom `admin_notes`, serta permission `teams.manage_status`. Namun di halaman Admin (`Admin/Teams/Show.vue`), belum ada tombol bagi Superadmin untuk:
  1. **Membuka Kunci Skuad Final (*Unlock Team Roster*)**: Jika tim sudah terlanjur klik `Kunci Skuad Final` (`locked_roster`), namun sebelum Technical Meeting ada pemain yang cedera parah dan harus diganti atas izin panitia.
  2. **Diskualifikasi Tim (`disqualified`)**: Menjatuhkan sanksi diskualifikasi beserta alasan resmi (yang otomatis melepaskan slot kuota event).
  3. **Kunci Skuad Paksa oleh Admin (*Force Lock Roster*)**: Jika semua pemain sudah sah dan batas waktu pendaftaran ditutup, tetapi manajer lupa menekan tombol `Kunci Skuad Final`.
  4. **Catatan Internal Panitia (`admin_notes`)**: Menyimpan catatan khusus internal antar-panitia pada suatu tim.

### 2.2 Penyelesaian Pengembalian Dana (*Refund Execution Workflow*)
- **Kondisi Saat Ini:** Sistem sudah memiliki deteksi otomatis `refund_required` apabila terjadi *late settlement* saat kuota event sudah penuh, serta peringatan di Dashboard Admin.
- **Yang Belum Ada:** Belum ada tombol aksi bagi Finance/Superadmin untuk **Menandai Refund Selesai (`refunded`)** beserta unggah bukti transfer pengembalian dana dan pengiriman email notifikasi `PaymentRefundedMail` ke manajer tim.

### 2.3 Ekspor Laporan Keuangan & Data Tim/Pemain (CSV / Excel / PDF)
- **Kondisi Saat Ini:** Di `PRD.md` bagian 4.1 disebutkan Finance memiliki fitur *"ekspor laporan keuangan per event"*.
- **Yang Belum Ada:**
  - Tombol **Export Laporan Keuangan / Transaksi (Excel/CSV)** per event di halaman `Riwayat Transaksi` (`/admin/payments/transactions`).
  - Tombol **Export Master Data Tim & Pemain (Excel/CSV)** per event untuk kebutuhan buku acara, asuransi pemain, atau pelaporan ke Askot/Asprov PSSI.

### 2.4 Pengajuan Screening Kolektif (*Bulk Submit Players*) di Portal Manajer
- **Kondisi Saat Ini:** Manajer harus menekan tombol `Ajukan Screening` satu per satu pada setiap kartu pemain (12–18 kali klik untuk 1 tim).
- **Yang Belum Ada:** Tombol **`Ajukan Semua Pemain Siap Screening (X Pemain)`** di bagian atas halaman `Manager/Players.vue` agar manajer cukup 1 kali klik ketika semua berkas pemain sudah lengkap.

### 2.5 Fitur Lupa Kata Sandi (*Forgot / Reset Password*) & Ubah Password Mandiri
- **Kondisi Saat Ini:** Manajer membuat password saat registrasi tim, namun belum ada halaman **Lupa Password (`/forgot-password`)** via email token maupun form **Ubah Password** di halaman profil manajer/admin.

### 2.6 Verifikasi / Screening Staf Ofisial & Lisensi Pelatih
- **Kondisi Saat Ini:** Di `DATABASE_SCHEMA.md` bagian 2.8 disebutkan tabel `team_officials` dirancang untuk memiliki verifikasi lisensi kepelatihan dan kartu ID Ofisial.
- **Yang Belum Ada:**
  - Saat ini kartu ID PDF (`id_cards.blade.php`) hanya mencetak kartu untuk pemain (`passedPlayers`), belum mencetak **ID Card untuk Pelatih & Ofisial Resmi** (atau opsi unduh ID Card Ofisial).
  - Belum ada tombol verifikasi keabsahan sertifikat lisensi pelatih oleh Admin.

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
