# Product Requirements Document (PRD): Multi-Event Football Tournament & League Platform

## 1. Ringkasan Proyek & Stack Teknologi
Platform manajemen kompetisi sepak bola multi-event (Cup/Knockout, League/Kompetisi Penuh, Group + Knockout, Festival Usia Dini) yang terintegrasi dari pendaftaran tim publik, pembayaran otomatis/manual, hingga *screening* berkas pemain dan manajemen operasional.

- **Backend:** Laravel 11.x (PHP 8.3+)
- **Frontend:** Vue 3 (Composition API, `<script setup>`) via Inertia.js
- **Database:** MySQL 8.0+ (InnoDB, indexing pada foreign keys & slug)
- **Authentication & Security:** Laravel Fortify / Session-based Inertia Auth, Bcrypt/Argon2id hashing, CSRF Protection, Rate Limiting (`ThrottleRequests`).
- **Authorization (RBAC):** `spatie/laravel-permission` + Laravel Policies & Middleware.
- **Queue & Background Jobs:** Redis / Database Queue (`ShouldQueue`) untuk pengiriman email transaksional dan webhook pembayaran.
- **Payment Gateway:** Midtrans (Snap / Core API), Xendit (Invoice API), dan Manual Bank Transfer (Upload Bukti Bayar).

---

## 2. Arsitektur Halaman Publik (Public Portal)

### 2.1 Halaman Utama (Global Landing Page - `/`)
- **Daftar Event Aktif & Akan Datang:** Filter berdasarkan format (`Cup`, `League`, `Grassroots Festival`), kelompok usia (U-10, U-12, U-15, U-17, Senior), dan status pendaftaran (`Open`, `Quota Full`, `Ongoing`, `Completed`).
- **Ringkasan Statistik Platform:** Total turnamen, total tim terdaftar, total pemain terverifikasi.
- **Pencarian Cepat:** Cari nama event atau kota penyelenggaraan.

### 2.2 Halaman Landing Per Event (`/events/{slug}`)
- **Informasi Utama Event:** Nama kompetisi, format (Cup/League/Group+Knockout/Festival), tanggal pelaksanaan, lokasi/stadion, indikator kuota maksimal & sisa slot real-time (misal: `24 / 32 Tim Terisi — Sisa 8 Slot`), batas waktu pelunasan reservasi slot, biaya pendaftaran, dan rincian hadiah/trofi.
- **Regulasi & Dokumen Unduhan:** Link unduh PDF Regulasi Teknis, *Laws of the Game* khusus event, dan syarat kelayakan pemain (batas tahun kelahiran).
- **Tab Informasi:** Ringkasan, Daftar Tim Terdaftar (Logo + Askot/Askab), Jadwal/Bagan (jika sudah berjalan), dan Kontak Panitia.
- **CTA Pendaftaran (`Daftar Sekarang`):** Mengarahkan ke `/events/{slug}/register`. Tombol hanya aktif jika status event `open`, masa pendaftaran sedang berlangsung, dan slot masih tersedia (`occupied_slots < max_teams`). Jika penuh, tombol otomatis berganti menjadi status terkunci `Kuota Penuh`.

### 2.3 Form Pendaftaran Tim (`/events/{slug}/register`)
Form menggunakan atribut `<form novalidate>` (tanpa validasi bawaan HTML5). Setelah submit berhasil di dalam transaksi terkunci, sistem otomatis membuat akun `Team Manager` (atau mengaitkan akun manajer yang sedang login), mereservasi 1 slot kuota event, menerbitkan invoice pendaftaran, dan mengarahkan ke Dashboard Manajer.

**Field Wajib:**
1. **Nama Tim:** String, 3–100 karakter, unik pada event yang sama (`UNIQUE(event_id, name)`).
2. **Logo Tim:** File upload (`png`, `jpg`, `jpeg`), maks 2 MB, rasio disarankan 1:1 (dikonversi/dikompresi di backend).
3. **Kota / Asosiasi Asal (Askot / Askab):** Dropdown/Searchable Select atau teks terstruktur (contoh: *Askot PSSI Jakarta Selatan*, *Askab PSSI Bogor*).
4. **Nama Manajer Tim / Penanggung Jawab:** String, 3–100 karakter.
5. **Email Aktif:** Format email valid, unik di tabel `users` (untuk pendaftar baru/tamu), digunakan untuk login dan notifikasi Queue.
6. **Password & Konfirmasi Password:** Minimal 8 karakter, kombinasi huruf dan angka (dienkripsi dengan `Hash::make()` / Argon2id).
7. **Nomor Telepon / WhatsApp Aktif:** Format nomor Indonesia (`08xx` / `+628xx`), 10–15 digit.
8. **Nama Pelatih Kepala (Head Coach):** String, 3–100 karakter (opsional lisensi kepelatihan di tahap awal, wajib dilengkapi di dashboard).
9. **Persetujuan Syarat & Ketentuan:** Checkbox wajib centang (`accepted` di FormRequest) bahwa tim menyetujui regulasi kompetisi dan keabsahan data.

### 2.4 Pengelolaan Kuota Maksimal Event & Arsitektur Anti-Race Condition
Untuk mencegah *over-booking* (jumlah tim melebihi kapasitas bagan/kompetisi) saat banyak tim menekan tombol daftar secara bersamaan di detik-detik terakhir kuota:

1. **Aturan Kuota Maksimal (`max_teams`):**
   - Setiap event wajib mendefinisikan `max_teams` (bilangan bulat positif `>= 2`, contoh: 8, 16, 24, 32, 64 tim) dan durasi batas pembayaran `payment_deadline_hours` (contoh: 24 jam).
   - Superadmin dilarang menurunkan nilai `max_teams` di bawah jumlah slot yang sedang terpakai (`occupied_slots`).
2. **Definisi Slot Terpakai (`occupied_slots`) & *Temporary Slot Hold*:**
   - Pendaftaran menggunakan skema **Reservasi Slot Sementara (*Temporary Slot Hold*)**: tim yang baru mendaftar langsung mengunci 1 slot selama masa tenggang pembayaran (`slot_expires_at`).
   - Slot kuota dihitung dari jumlah tim pada event terkait dengan status:
     - `pending_payment` (selama tagihan berstatus `waiting_verification`, atau berstatus `unpaid` dengan `expires_at > NOW()`),
     - `active` (pembayaran lunas), dan
     - `locked_roster` (skuad telah disahkan/dikunci).
   - Tim dengan status `expired` atau `disqualified` otomatis tidak lagi memakan slot kuota.
3. **Pessimistic Row-Level Locking (`SELECT ... FOR UPDATE`):**
   - Validasi kuota pada `FormRequest` hanya berperan sebagai penyaring awal (*optimistic pre-check*). Keputusan akhir ketersediaan kuota **wajib** dieksekusi di dalam `DB::transaction()` menggunakan penguncian baris eksklusif MySQL InnoDB pada record event (`Event::whereKey($event->id)->lockForUpdate()->firstOrFail()`).
   - Urutan eksekusi atomik saat baris `events` dikunci:
     1. **Lazy Expiration Sweep:** Tandai terlebih dahulu seluruh pendaftaran `pending_payment` pada event tersebut yang tagihan `unpaid`-nya telah melewati `expires_at` menjadi `expired`. Ini menjamin slot yang baru saja hangus di detik sebelumnya langsung tersedia tanpa jeda penjadwalan cron.
     2. **Perhitungan Akurat:** Hitung jumlah `occupied_slots` terkini secara langsung di dalam transaksi.
     3. **Penolakan Aman saat Penuh:** Jika `occupied_slots >= event->max_teams`, pastikan status event menjadi `quota_full`, batalkan pembuatan tim, dan kembalikan respon validasi terstruktur: *"Maaf, kuota pendaftaran untuk kompetisi ini baru saja terpenuhi oleh tim lain."*
     4. **Reservasi Atomik:** Jika slot masih tersedia, buat akun `team_manager`, simpan `Team` (`pending_payment`), buat `Payment` (`unpaid` beserta `expires_at`), dan apabila `occupied_slots + 1 == event->max_teams`, perbarui status event menjadi `quota_full` sebelum transaksi di-`commit`.
4. **Penanganan Konkurensi pada Pembayaran, Kedaluwarsa, & Klaim Ulang:**
   - **Pelepasan Slot Terjadwal (Scheduler):** Job terjadwal setiap menit menyapu seluruh invoice `unpaid` lintas event yang melewati `expires_at` menjadi `expired`, mengubah status tim menjadi `expired`, dan mengembalikan status event dari `quota_full` menjadi `open` jika masa pendaftaran belum ditutup.
   - **Sinkronisasi Upload Bukti Manual:** Pengunggahan bukti transfer manual mengunci baris `Payment` (`lockForUpdate()`). Begitu status berubah menjadi `waiting_verification`, timer kedaluwarsa otomatis ditangguhkan agar slot tidak hilang saat sedang mengantre pemeriksaan Finance. Jika Finance menolak bukti transfer (`rejected`), sistem memberikan perpanjangan waktu terbatas (*re-upload grace period*, misal 6 jam) untuk mengunggah ulang bukti yang benar.
   - **Proteksi *Late Webhook Settlement* (Over-Quota Guard):** Waktu kedaluwarsa dikirimkan ke payload Midtrans/Xendit agar kanal pembayaran tertutup otomatis saat `expires_at`. Apabila tetap terjadi *late settlement* dari gateway setelah pendaftaran berstatus `expired` dan slot terakhir telah diisi tim lain (`occupied_slots >= max_teams`), sistem **tetap menjaga batas `max_teams`** dan menandai transaksi tersebut sebagai `refund_required` untuk pengembalian dana oleh Finance.
   - **Anti Double-Submit & Klaim Ulang Slot (*Reclaim Slot*):** Tombol submit dikunci selama request berjalan (`form.processing`), diperkuat indeks unik database `UNIQUE(event_id, name)` serta pembatasan laju request (`ThrottleRequests`). Tim yang statusnya `expired` dapat mengajukan klaim ulang slot dari portal manajer melalui mekanisme `lockForUpdate()` yang sama selama kuota masih tersedia.

---

## 3. Autentikasi & Keamanan (`/admin/login` & `/manager/login`)
- **Single Unified Login atau Dedicated Entry:** Akses `/admin` otomatis mengarahkan pengguna yang belum terautentikasi ke `/admin/login`.
- **Standar Keamanan:**
  - Password hashing menggunakan **Argon2id** atau **Bcrypt** (cost factor 12).
  - **Rate Limiting:** Maksimal 5 percobaan login gagal per menit per kombinasi IP + Email (`Lockout` selama 60 detik dengan pesan error inline yang jelas).
  - **Session Regeneration:** Wajib menjalankan `$request->session()->regenerate()` setelah autentikasi berhasil untuk mencegah *Session Fixation*.
  - **Role-Based Redirect:** Setelah login, `superadmin` dan `finance` diarahkan ke `/admin/dashboard`, sedangkan `team_manager` diarahkan ke `/manager/dashboard`.

---

## 4. Role-Based Access Control (Spatie RBAC & Policies)

### 4.1 Daftar Role & Ruang Lingkup Akses
| Role | Kode Role (`spatie`) | Hak Akses Utama |
| :--- | :--- | :--- |
| **Superadmin** | `superadmin` | Akses penuh (`Gate::before`). Manajemen Event, Tim, Screening Pemain, Pembayaran, User, Role, & Permission. |
| **Finance** | `finance` | Melihat daftar transaksi, memverifikasi pembayaran manual (Approve/Reject bukti transfer), memantau callback Midtrans/Xendit, ekspor laporan keuangan per event. |
| **Manajer Tim** | `team_manager` | Mengelola profil tim sendiri, melakukan pembayaran pendaftaran, menambah/mengedit data pemain & ofisial, mengunggah berkas *screening*, memperbaiki data pemain yang ditolak (*re-upload*). |

### 4.2 Matriks Menu Sidebar Berdasarkan Role
- **Menu Superadmin:**
  - Dashboard Operasional
  - Manajemen Kompetisi (Daftar Event, Buat Event, Kategori Usia, Kuota & Biaya)
  - Verifikasi & Screening (Daftar Tim Masuk, Verifikasi Berkas Pemain & Ofisial)
  - Keuangan & Pembayaran (Transaksi Gateway, Verifikasi Manual, Pengaturan Channel Pembayaran)
  - Pengaturan Sistem (Manajemen User, Role & Permission Matrix, Log Email Queue)
- **Menu Finance:**
  - Dashboard Keuangan
  - Verifikasi Pembayaran Manual (Antrean Bukti Transfer)
  - Riwayat Transaksi (Midtrans, Xendit, Manual)
  - Laporan Pendapatan per Event
- **Menu Manajer Tim:**
  - Ringkasan Tim & Status Pendaftaran
  - Pembayaran Pendaftaran (Invoice & Upload Bukti)
  - Daftar Pemain & Berkas Screening (Terkunci sebelum pembayaran lunas, atau terbuka sesuai kebijakan event)
  - Data Ofisial & Pelatih
  - Unduh ID Card / Pengesahan Pemain (Hanya untuk pemain berstatus `Passed Screening`)

---

## 5. Modul Pembayaran (Payment Gateway & Manual)
Setiap pendaftaran tim menghasilkan 1 record `RegistrationInvoice` dengan kode unik (misal: `INV-2026-EVT01-0042`).
1. **Otomatis - Midtrans (Snap / Core API) & Xendit:**
   - Mendukung Virtual Account (BCA, Mandiri, BNI, BRI), QRIS, dan E-Wallet.
   - Menggunakan endpoint Webhook khusus (`/api/webhooks/midtrans` dan `/api/webhooks/xendit`) dengan verifikasi *Signature Key* wajib sebelum mengubah status menjadi `paid`.
2. **Manual Transfer Bank:**
   - Manajer mengunggah bukti transfer (`jpg`, `png`, `pdf`, maks 2 MB), memilih bank pengirim, nama pengirim, dan tanggal transfer.
   - Status berubah menjadi `waiting_verification`. Role `finance` atau `superadmin` melakukan *Approve* atau *Reject* beserta alasan.

---

## 6. Modul Manajemen Pemain & Verifikasi Berkas (Screening)
- **Input Pemain oleh Manajer Tim:**
  - Nama Lengkap, Nomor Punggung, Posisi (`GK`, `DF`, `MF`, `FW`), Tanggal Lahir, NIK / NISN, Tinggi/Berat Badan.
  - **Unggah Berkas Wajib:** Pas Foto terbaru, Akta Kelahiran, Kartu Keluarga (KK), Ijazah / Rapor / KIA, atau KTP (untuk senior).
- **Proses Screening oleh Admin:**
  - Admin memeriksa kesesuaian dokumen dengan batas usia event di panel *Side-by-Side Document Viewer*.
  - Status per pemain:
    - `draft`: Belum diajukan.
    - `submitted`: Menunggu pemeriksaan admin.
    - `passed`: Lolos verifikasi (data dikunci, tidak bisa diubah manajer).
    - `rejected`: Gagal/Revisi (wajib menyertakan catatan alasan penolakan, misal: *"Scan Akta Kelahiran buram"* atau *"Melebihi batas usia kelahiran 2012"*).

---

## 7. Fitur Email Queue & Notifikasi Transaksional
Semua pengiriman email wajib di-dispatch ke background queue (`implements ShouldQueue`) dengan mekanisme `$tries = 3` dan `$backoff = [10, 30, 60]`:
1. **`TeamRegisteredMail`:** Dikirim saat form pendaftaran publik berhasil disubmit (berisi detail akun manajer, ringkasan event, dan instruksi pembayaran).
2. **`PaymentReceivedMail`:** Dikirim saat pembayaran berhasil via Webhook Midtrans/Xendit atau disetujui oleh Finance (membuka akses penuh pengisian berkas pemain).
3. **`PaymentRejectedMail`:** Dikirim jika bukti bayar manual ditolak oleh Finance beserta alasannya.
4. **`ScreeningResultMail`:** Dikirim ketika admin menyelesaikan verifikasi satu batch tim atau per pemain:
   - Daftar pemain yang **Lolos Screening (`Passed`)**.
   - Daftar pemain yang **Gagal / Perlu Revisi (`Rejected`)** beserta catatan spesifik dari verifikator dan tautan langsung untuk perbaikan berkas.