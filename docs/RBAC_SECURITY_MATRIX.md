# RBAC_SECURITY_MATRIX.md - Peta Rute, Matriks Izin Spatie, & Standar Keamanan

Dokumen ini mengatur tata kelola keamanan akses, struktur URL aplikasi, matriks izin Spatie Permission, serta aturan isolasi data pengguna.

---

## 1. Struktur Peta Rute (URL Routing Blueprint)

### 1.1 Area Publik (Tanpa Login)
- `/` : Halaman Utama Katalog Multi-Event (Global Landing Page).
- `/events/{slug}` : Halaman Detail & Informasi Spesifik per Event.
- `/events/{slug}/register` : Halaman Formulir Pendaftaran Tim & Pembuatan Akun Manajer.
- `/login` : Halaman Login Khusus Manajer Tim.
- `/forgot-password` & `/reset-password` : Alur Pemulihan Kata Sandi.

### 1.2 Pintu Gerbang Internal Panitia (`/admin`)
- `/admin` : Gerbang pemeriksaan sesi. Jika belum login, otomatis diarahkan ke `/admin/login`. Jika sudah login sebagai `superadmin` atau `finance`, diarahkan ke `/admin/dashboard`. Jika login sebagai `team_manager`, ditolak dan diarahkan ke `/manager/dashboard`.
- `/admin/login` : Halaman Login Terenkripsi Khusus Staf Internal (Superadmin & Finance).

### 1.3 Area Portal Manajer Tim (Wajib Role: `team_manager`)
- `/manager/dashboard` : Ringkasan Status Pendaftaran, Status Pembayaran, dan Progres Screening Tim.
- `/manager/team-profile` : Pengelolaan profil tim, logo, dan kontak penanggung jawab.
- `/manager/payments` : Detail Invoice, Pilihan Bayar Otomatis (Midtrans/Xendit), dan Formulir Unggah Bukti Transfer Manual.
- `/manager/players` : Tampilan Card List View Pemain, Tambah/Edit Biodata, Unggah/Pratinjau Dokumen Screening, Aksi **Ajukan Screening** (`draft` -> `submitted`), dan Aksi **Tarik Pengajuan** (`submitted` -> `draft` via `/manager/players/{player}/recall`).
- `/manager/officials` : Pengelolaan data Pelatih Kepala dan Ofisial Tim (Card List View).
- `/manager/accreditation` : Unduh Berkas Pengesahan Tim & Kartu ID Pemain (Hanya untuk pemain berstatus Lolos Screening).

### 1.4 Area Operasional Admin, Finance, & Screening (Wajib Role: `superadmin`, `finance`, atau `screening_officer`)
- `/admin/dashboard` : Ringkasan Metrik Operasional & Keuangan Lintas Event.
- `/admin/events` : Daftar Event (Card List View), Tambah Event, Pengaturan Kuota, Batas Usia, Biaya, Deskripsi HTML Rich-Text, Google Maps Stadion, dan Tombol **Resync Kuota Event**.
- `/admin/teams` : Daftar Tim Terdaftar per Event (Card List View), Filter Askot/Askab, Tanggal Kedaluwarsa Slot, dan Tombol **Resync Kuota Global**.
- `/admin/screening` : Antrean Verifikasi Tim & Panel Layar Terbagi (*Split-View*) Pemeriksaan Dokumen Pemain.
- `/admin/payments/manual-verification` : Antrean Persetujuan/Penolakan Bukti Transfer Manual dengan Dual Empty State.
- `/admin/payments/transactions` : Riwayat Transaksi Payment Gateway & Transfer Manual (Card List View) & Ekspor Laporan Keuangan.
- `/admin/users` : Manajemen Akun Pengguna Panitia & Manajer Tim (Card List View) dengan dukungan Multi-Method `POST`/`PUT` dan Reset Kata Sandi.
- `/admin/roles` : Pengaturan Role & Matriks Centang Permission Dinamis.
- `/admin/settings` : Pengaturan Branding Web (Logo, Favicon, Tagline, Gaya Header) dan Konfigurasi Saluran Pembayaran (Manual / Midtrans Gateway).

---

## 2. Matriks Permission Spatie Berdasarkan Role

| Kode Permission | Deskripsi Hak Akses | Superadmin | Finance | Screening Officer | Manajer Tim |
| :--- | :--- | :---: | :---: | :---: | :---: |
| `dashboard.admin.view` | Melihat dashboard internal panitia | Ya | Ya | Ya | Tidak |
| `events.view` | Melihat daftar dan detail manajemen event | Ya | Ya | Tidak | Tidak |
| `events.create` | Membuat event kompetisi baru | Ya | Tidak | Tidak | Tidak |
| `events.update` | Mengubah pengaturan, kuota, dan biaya event | Ya | Tidak | Tidak | Tidak |
| `events.delete` | Menghapus atau mengarsipkan event | Ya | Tidak | Tidak | Tidak |
| `teams.view_all` | Melihat seluruh tim dari semua event | Ya | Ya | Ya | Tidak |
| `teams.manage_status` | Mendiskualifikasi atau mengunci paksa roster tim | Ya | Tidak | Tidak | Tidak |
| `screening.view_queue` | Melihat antrean berkas pemain yang diajukan | Ya | Tidak | Ya | Tidak |
| `screening.verify_player` | Meloloskan atau menolak berkas screening pemain | Ya | Tidak | Ya | Tidak |
| `screening.unlock_player` | Membuka kembali kunci data pemain yang sudah lolos | Ya | Tidak | Ya | Tidak |
| `payments.view_all` | Melihat seluruh invoice dan transaksi semua tim | Ya | Ya | Tidak | Tidak |
| `payments.verify_manual` | Menyetujui atau menolak bukti transfer manual | Ya | Ya | Tidak | Tidak |
| `payments.export_report` | Mengunduh laporan keuangan dan rekonsiliasi | Ya | Ya | Tidak | Tidak |
| `payments.configure_gateway` | Mengatur saluran aktif Midtrans / Xendit / Rekening | Ya | Tidak | Tidak | Tidak |
| `users.manage` | Menambah, mengedit, dan menonaktifkan akun user | Ya | Tidak | Tidak | Tidak |
| `roles.manage` | Membuat role baru dan mengubah matriks permission | Ya | Tidak | Tidak | Tidak |
| `email_logs.manage` | Melihat log antrean email dan memicu kirim ulang | Ya | Tidak | Tidak | Tidak |
| `manager.portal.access` | Mengakses portal khusus manajer tim | Tidak | Tidak | Tidak | Ya |
| `manager.team.update_own` | Mengubah profil tim milik sendiri | Tidak | Tidak | Tidak | Ya |
| `manager.payment.pay_own` | Membayar tagihan atau unggah bukti bayar tim sendiri | Tidak | Tidak | Tidak | Ya |
| `manager.players.manage_own` | Menambah/mengedit pemain milik tim sendiri | Tidak | Tidak | Tidak | Ya |

---

## 3. Aturan Keamanan & Isolasi Data (Policy Rules)
1. **Aturan Kepemilikan Mutlak (`TeamPolicy` & `PlayerPolicy`):**
   - Setiap permintaan baca, ubah, hapus, maupun unduh dokumen dari peran `team_manager` wajib memeriksa kecocokan ID Pengguna yang sedang login dengan pemilik sah tim tersebut.
   - Percobaan mengganti parameter ID pada URL untuk mengakses tim lain wajib dihentikan oleh sistem dengan respon penolakan akses (403 Forbidden).
2. **Aturan Penguncian Data & Berkas (`Immutability & Lock Rules`):**
   - Pemain yang berstatus `submitted` (Sedang Diperiksa Panitia) atau `passed` (Lolos Verifikasi) dikunci secara ketat (`is_locked = true` atau `isDocModalReadOnly`).
   - Manajer tim dilarang mengubah biodata maupun mengganti berkas pemain yang sedang diajukan kecuali melalui mekanisme resmi **Tarik Pengajuan (*Recall to Draft*)** sebelum panitia mengambil keputusan verifikasi.
   - Dokumen bukti pembayaran manual yang sedang berstatus `waiting_verification` atau sudah `paid` tidak dapat ditimpa ulang oleh Manajer Tim kecuali statusnya diubah menjadi `rejected` oleh Finance.
3. **Penyimpanan Dokumen Sensitif (Private Disk & Signed Route):**
   - Berkas Akta Kelahiran, Kartu Keluarga, KIA, dan Bukti Transfer **dilarang** diletakkan pada folder publik yang dapat ditebak URL-nya.
   - Seluruh berkas sensitif wajib disajikan melalui rute khusus (`/private-media/*`) yang memeriksa sesi login dan otorisasi Policy sebelum menampilkan gambar ke layar.

---

## 4. Standar Hardening Keamanan Produksi (*Production Security Hardening*)

### 4.1 Header Keamanan HTTP Universal (`SecurityHeaders` Middleware)
Setiap respons web publik dan panel terotentikasi dilengkapi dengan header pertahanan komprehensif:
- **Content-Security-Policy (CSP)**: Menggunakan token acak dinamis per-request (*CSP Nonce*) yang terintegrasi dengan Vite (`Vite::useCspNonce()`) dan Ziggy (`@routes(null, $nonce)`). Menolak script/eksekusi asing (`default-src 'self'`, `object-src 'none'`, `frame-ancestors 'self'`).
- **Strict-Transport-Security (HSTS)**: `max-age=31536000; includeSubDomains` aktif otomatis saat koneksi HTTPS.
- **X-Frame-Options**: `SAMEORIGIN` (mencegah serangan Clickjacking).
- **X-Content-Type-Options**: `nosniff` (mencegah MIME-type sniffing).
- **Referrer-Policy**: `strict-origin-when-cross-origin`.
- **Permissions-Policy**: `camera=(self), microphone=(), geolocation=(), payment=(), usb=()` (hanya mengizinkan kamera internal untuk Matchday QR Scanner).
- **Cross-Origin-Opener-Policy (COOP)**: `same-origin`.

### 4.2 Rate Limiting Terpusat & Anti-Abuse (`AppServiceProvider`)
- `team-registration`: Maksimal 10 pendaftaran/menit per IP.
- `public-verify`: Maksimal 30 permintaan/menit per IP (mencegah *brute-force* / enumerasi kode akreditasi pemain/ofisial).
- `password-reset`: Perlindungan ganda (5 permintaan/menit per IP dan 10 permintaan/jam per kombinasi `email|IP`) untuk mencegah email bombing.
- `webhooks`: Maksimal 120 notifikasi/menit per IP dengan verifikasi tanda tangan kriptografi SHA-512 (Midtrans) & Token (Xendit).
- `qr-lookup`: Maksimal 120 pemindaian/menit untuk meja wasit/inspektur pertandingan.

### 4.3 Isolasi & Penguncian Modul Simulasi (*Take-Out Safety Guard*)
- Nilai default master konfigurasi adalah **MATI (`false`)**.
- Rute `/simulation/*` memiliki proteksi ganda: tidak akan pernah didaftarkan pada lingkungan produksi (`! app()->isProduction()`).
- `AppServiceProvider` menerapkan guard-rail runtime: menolak aplikasi berjalan di produksi jika modul simulasi aktif atau `APP_DEBUG=true`.

### 4.4 Integritas Antrean Email & Anti-Object Injection
- Data mailable pada tabel `email_notification_logs` ditandatangani menggunakan SHA-256 HMAC dengan secret key aplikasi (`app.key`).
- `SendLoggedEmailJob` memverifikasi keabsahan HMAC sebelum melakukan `unserialize()`. Setiap payload yang dimanipulasi di database akan otomatis ditolak dan tidak akan pernah dieksekusi.

### 4.5 Sanitasi Konten Dinamis Berbasis DOM (`HtmlSanitizer`)
- Deskripsi event dan input teks kaya dibersihkan menggunakan parser DOM XML dengan *tag & attribute whitelist* ketat.
- Seluruh tag berbahaya (`<script>`, `<iframe>`, `<object>`, `<svg onload>`, event handler `on*`, skema `javascript:`) dibuang tuntas sebelum disimpan dan dirender ke browser.

### 4.6 Penanganan Error & Halaman Status Bermerek (Branded Error Views)
- Halaman status `404 Not Found`, `403 Forbidden`, `429 Too Many Requests`, dan `500 Server Error` menggunakan template bertema resmi Persija Development (*Liquid Glass*).
- Menyembunyikan seluruh informasi internal, stack trace, dan query database dari publik.
