# FLOW.md - System Architecture, Data Model & End-to-End Workflows

Dokumen ini mendefinisikan alur kerja sistem dari hulu ke hilir, aturan perubahan status (*State Machine*), serta struktur keterkaitan data pada proyek **Multi-Event Football Competition Management System**.

---

## 1. Struktur Entitas & Relasi Data (Database Blueprint)

### 1.1 Entitas Pengguna & Hak Akses
- **Users:** Menyimpan data kredensial login (Nama, Email, Nomor Telepon, Password Terenkripsi, Status Aktif).
- **Roles & Permissions:** Menyimpan definisi peran (`superadmin`, `finance`, `team_manager`) dan daftar izin granular (seperti mengelola event, memverifikasi pembayaran, melakukan screening pemain, dan mengelola pengguna).

### 1.2 Entitas Kompetisi (Events)
- Menyimpan identitas setiap turnamen atau liga: Nama Event, Slug URL, Format Kompetisi (Cup, League, Group+Knockout, Festival), Kategori Usia, Batas Tanggal Lahir Maksimal Pemain, Kuota Maksimal Tim (`max_teams`), Durasi Batas Bayar Reservasi Slot (`payment_deadline_hours`), Biaya Pendaftaran, Tanggal Pelaksanaan, Lokasi, Dokumen Regulasi, dan Status Event (`Draft`, `Open`, `Quota Full`, `Closed`, `Ongoing`, `Completed`).

### 1.3 Entitas Tim Partisipan (Teams)
- Terhubung ke satu **Event** dan satu **User (Manajer Tim)**.
- Menyimpan: Nama Tim, Path Logo Tim, Kota/Asosiasi Asal (Askot/Askab), Nama Manajer, Nomor Telepon Manajer, Nama Pelatih Kepala, Waktu Persetujuan Syarat & Ketentuan, Waktu Reservasi & Kedaluwarsa Slot, serta Status Pendaftaran Tim (`Pending Payment`, `Active`, `Locked Roster`, `Expired`, `Disqualified`).

### 1.4 Entitas Tagihan & Pembayaran (Payments)
- Terhubung ke satu **Team** dan satu **Event**.
- Menyimpan: Nomor Invoice Unik, Nominal Tagihan, Metode Pembayaran (`Midtrans`, `Xendit`, `Manual`), Saluran Pembayaran, Referensi Transaksi Eksternal, Waktu Kedaluwarsa (`expires_at`), Berkas Bukti Transfer Manual, Nama Bank & Pengirim Manual, Status Pembayaran (`Unpaid`, `Waiting Verification`, `Paid`, `Rejected`, `Expired`, `Refund Required`, `Refunded`), ID Verifikator Finance, Catatan Penolakan, dan Waktu Pelunasan.

### 1.5 Entitas Pemain & Berkas Screening (Players & Player Documents)
- Terhubung ke satu **Team** dan satu **Event**.
- Menyimpan biodata pemain: Nama Lengkap, Nomor Punggung, Posisi, Tanggal Lahir, Nomor Identitas (NIK/NISN), Pas Foto, Status Screening (`Draft`, `Submitted`, `Passed`, `Rejected`), Catatan Verifikator Admin, ID Pemeriksa, dan Waktu Pemeriksaan.
- Memiliki relasi ke entitas **Dokumen Pemain** yang menyimpan berkas scan Akta Kelahiran, Kartu Keluarga, Rapor/Ijazah, atau KTP.

---

## 2. Alur End-to-End 1: Penjelajahan Publik & Pendaftaran Tim (Anti-Race Condition)

1. **Eksplorasi Kompetisi:**
   - Pengunjung membuka halaman utama publik dan melihat daftar kompetisi sepak bola beserta indikator ketersediaan slot kuota (`Open` atau `Quota Full`).
   - Pengunjung memilih salah satu event untuk membuka halaman landing spesifik event tersebut, membaca persyaratan kategori usia, memantau sisa slot kuota real-time (`occupied_slots / max_teams`), serta mengunduh regulasi.
2. **Pengisian Formulir Pendaftaran:**
   - Calon manajer menekan tombol pendaftaran dan mengisi Nama Tim, mengunggah Logo Tim (PNG/JPG), mengisi Kota/Asosiasi Asal (Askot/Askab), Nama Manajer, Email Aktif, Password, Nomor Telepon Aktif, Nama Pelatih, dan mencentang Persetujuan Syarat & Ketentuan.
3. **Proses Validasi Dua Arah:**
   - Sistem memeriksa kelengkapan isian di sisi layar pengguna menggunakan tampilan pesan kesalahan kustom (tanpa alert HTML5) serta mengunci tombol kirim (`form.processing`) agar tidak terjadi pengiriman ganda.
   - Server memvalidasi format data, keunikan email, serta keunikan nama tim pada event terkait.
4. **Penguncian Kuota Atomik & Reservasi Slot (`DB::transaction` + `lockForUpdate`):**
   - Server mengunci baris `Event` (`SELECT ... FOR UPDATE`) sehingga permintaan pendaftaran serentak diproses secara berurutan satu per satu.
   - Sistem menjalankan *Lazy Expiration Sweep* pada event tersebut untuk membebaskan slot pendaftaran `Pending Payment` yang tagihan `Unpaid`-nya telah melewati `expires_at`.
   - Sistem menghitung ulang `occupied_slots`. Jika `occupied_slots >= max_teams`, transaksi dibatalkan, status event disinkronkan ke `Quota Full`, dan form mengembalikan pesan kesalahan bahwa kuota baru saja penuh.
   - Jika slot masih tersedia, sistem membuat akun Pengguna baru dengan peran `team_manager`, menyimpan Tim berstatus `Pending Payment` (menahan 1 slot sementara), menerbitkan Tagihan `Unpaid` dengan batas waktu `expires_at`, dan mengubah status event menjadi `Quota Full` apabila slot terakhir baru saja terisi.
5. **Antrean Email & Pengarahan Otomatis:**
   - Setelah transaksi di-`commit`, sistem memasukkan pekerjaan pengiriman **Email Pendaftaran Berhasil** ke dalam antrean latar belakang (*Queue*).
   - Pengguna langsung masuk (auto-login) ke portal Manajer Tim dan diarahkan menuju halaman penyelesaian pembayaran.
   - Apabila hingga `expires_at` tagihan tetap `Unpaid`, tugas terjadwal (*Scheduler*) otomatis mengubah status tagihan & tim menjadi `Expired`, melepas slot kuota, dan membuka kembali status event (`Quota Full` -> `Open`) selama masa pendaftaran masih berjalan.
6. **Sinkronisasi Kuota Real-time (*Resync Slot Engine*):**
   - Panitia dapat memicu sinkronisasi ulang kuota (`/admin/events/resync-slots` atau `/admin/events/{event}/resync-slots`) sewaktu-waktu dari antarmuka Admin untuk memastikan status ketersediaan slot (`open` ↔ `quota_full`) selalu akurat 100% terhadap transaksi aktif di database.

---

## 3. Alur End-to-End 2: Penyelesaian Pembayaran (Gateway & Manual)

### 3.1 Jalur Otomatis (Midtrans / Xendit)
1. Pada halaman pembayaran Manajer Tim, manajer memilih metode pembayaran instan (Virtual Account / QRIS / E-Wallet).
2. Sistem menghubungi server Midtrans atau Xendit untuk menampilkan instruksi pembayaran.
3. Setelah manajer melakukan pembayaran, server Payment Gateway mengirimkan sinyal notifikasi (Webhook) ke sistem.
4. Sistem memverifikasi keaslian tanda tangan keamanan Webhook, mengubah status tagihan menjadi `Paid`, mengaktifkan status tim menjadi `Active`, membuka akses menu pengisian pemain, dan mengirim **Email Konfirmasi Pembayaran Lunas** melalui Queue.

### 3.2 Jalur Manual (Transfer Bank & Verifikasi Finance)
1. Manajer Tim melakukan transfer ke rekening resmi panitia, lalu mengunggah foto/PDF bukti transfer beserta detail rekening pengirim pada halaman pembayaran.
2. Status tagihan berubah menjadi `Waiting Verification`.
3. Staf dengan peran `finance` atau `superadmin` login melalui `/admin/login` dan membuka menu **Antrean Verifikasi Pembayaran Manual** (dilengkapi Dual Empty State informatif).
4. Staf memeriksa kecocokan mutasi rekening dengan bukti unggahan melalui modal `PaymentProofModal.vue`:
   - **Apabila Disetujui:** Status tagihan berubah menjadi `Paid`, status tim menjadi `Active`, akses input pemain dibuka, dan sistem mengirimkan **Email Konfirmasi Pembayaran Lunas** melalui Queue.
   - **Apabila Ditolak:** Staf wajib menuliskan alasan penolakan (min. 10 karakter). Status tagihan berubah menjadi `Rejected`, sistem mengirimkan **Email Penolakan Pembayaran** melalui Queue, dan Manajer Tim mendapatkan masa tenggang (*grace period*) untuk mengunggah ulang bukti pembayaran yang benar.

---

## 4. Alur End-to-End 3: Pengisian Daftar Pemain & Screening Berkas

1. **Akses Khusus Manajer Tim (Card List View Responsif):**
   - Manajer Tim wajib login ke portal khusus manajer untuk membuka menu **Daftar Pemain & Berkas Screening** (`/manager/players`) yang disajikan dalam format Card List View responsif.
2. **Penambahan Data & Berkas Pemain:**
   - Manajer menginput biodata setiap pemain beserta unggahan foto dan dokumen pendukung (Akta Lahir, KK, KIA/Rapor) melalui komponen universal `FileInput.vue`.
   - Sistem secara otomatis memvalidasi tanggal lahir pemain terhadap batas kelahiran maksimal event (*Age Cut-Off Filter* otomatis) dan keunikan 16 digit NIK (*NIK Duplicate Lock*).
   - Setiap berkas yang telah berhasil diunggah memiliki tombol **"Lihat Berkas"** langsung untuk mempratinjau dokumen scan secara aman dari rute privat media.
3. **Pengajuan Screening & Penguncian Dokumen:**
   - Setelah data dan dokumen pemain lengkap, Manajer Tim menekan tombol **Ajukan Screening** sehingga status pemain berubah dari `Draft` menjadi `Submitted`.
   - Selama berstatus `Submitted`, form pengubahan berkas otomatis dikunci demi integritas verifikasi panitia.
   - **Alur Tarik Pengajuan (*Recall to Draft*):** Jika manajer menyadari ada kesalahan data/berkas sebelum panitia memeriksa, manajer dapat menekan tombol **"Tarik Pengajuan"** (`/manager/players/{player}/recall`) untuk mengembalikan pemain ke status `Draft` agar dapat diedit kembali.
4. **Pemeriksaan oleh Admin (Screening Process — Fase 2):**
   - Petugas dengan peran `screening_officer` atau `superadmin` membuka menu **Verifikasi & Screening Pemain**, memilih event dan tim terkait, lalu memeriksa data pemain berdampingan dengan pratinjau dokumen asli di layar (*Split-View Reviewer*).
   - **Keputusan Lolos (`Passed`):** Jika seluruh dokumen sah dan sesuai regulasi, admin menandai pemain sebagai **Lolos**. Status pemain menjadi `Passed` dan data otomatis dikunci permanen (`is_locked = true`).
   - **Keputusan Gagal (`Rejected`):** Jika ditemukan ketidaksesuaian atau dokumen buram, admin menandai pemain sebagai **Gagal** dan wajib mengisi kolom catatan alasan penolakan.
5. **Notifikasi Email Hasil Screening & Siklus Revisi Terarah:**
   - Saat admin menyelesaikan pemeriksaan, sistem mencatat riwayat ke `screening_logs` dan memasukkan pengiriman **Email Hasil Screening Tim** ke dalam Queue.
   - Manajer Tim menerima rincian lengkap daftar pemain yang dinyatakan Lolos dan daftar pemain yang Gagal beserta alasan spesifiknya.
   - Manajer Tim login kembali untuk memperbaiki data atau mengunggah ulang dokumen **hanya pada pemain yang berstatus `Rejected`**, kemudian mengajukannya kembali untuk diperiksa ulang oleh admin (*Selective Re-submission*).

---

## 5. Alur End-to-End 4: Akses `/admin`, Keamanan Sesi, & Manajemen RBAC

1. **Gerbang Autentikasi `/admin`:**
   - Setiap upaya mengakses rute `/admin` atau sub-rutenya tanpa sesi aktif akan dicegat dan diarahkan ke halaman `/admin/login`.
   - Setelah kredensial diverifikasi, sistem memeriksa peran pengguna:
     - `superadmin` dan `finance` diarahkan menuju dashboard operasional admin dengan menu sidebar yang disesuaikan dengan izin masing-masing.
     - `team_manager` yang mencoba masuk melalui pintu `/admin` akan diarahkan kembali ke area khusus `/manager/dashboard` dan ditolak aksesnya dari seluruh modul internal panitia.
2. **Pengelolaan User, Role, & Permission oleh Superadmin:**
   - Melalui menu **Manajemen Pengguna & Akses**, Superadmin dapat menambahkan akun staf baru (misal: staf Finance baru atau admin verifikator baru), menetapkan Role, serta menyesuaikan matriks Permission secara langsung tanpa perlu mengubah struktur dasar aplikasi.