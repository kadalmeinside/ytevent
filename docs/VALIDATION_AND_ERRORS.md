# VALIDATION_AND_ERRORS.md - Standar Validasi Berlapis & Kamus Pesan Kesalahan

Dokumen ini menetapkan aturan validasi di sisi layar pengguna (Frontend) dan di sisi server (Backend) untuk memastikan seluruh form bebas dari peringatan bawaan HTML5 dan memiliki penanganan kesalahan yang seragam.

---

## 1. Mekanisme Teknis Validasi Tanpa Bawaan HTML5
1. **Penonaktifan Peringatan Browser:** Seluruh elemen formulir wajib dinonaktifkan dari pemeriksaan bawaan browser agar tidak pernah memunculkan gelembung peringatan standar peramban.
2. **Alur Pemeriksaan Frontend (Sebelum Request Dikirim):**
   - Saat tombol kirim ditekan, lapisan validasi Vue memeriksa kelengkapan dan format seluruh kolom secara instan.
   - Jika ditemukan kesalahan, pengiriman ke server dibatalkan, kotak ringkasan kesalahan bernuansa *Frosted Rose* ditampilkan di bagian atas form, layar bergulir halus ke kolom pertama yang salah, dan pesan kesalahan tampil tepat di bawah kolom terkait.
   - Saat pengguna mulai mengetik atau memilih ulang berkas pada kolom yang bermasalah, warna merah dan pesan kesalahan pada kolom tersebut otomatis menghilang.
3. **Alur Pemeriksaan Backend (FormRequest di Server):**
   - Jika data lolos dari pemeriksaan layar pengguna, server kembali memvalidasi seluruh aturan format sekaligus memeriksa aturan database (seperti keunikan email, ketersediaan kuota event, keaslian berkas gambar, dan batas usia kelahiran).
   - Jika server menemukan kesalahan, daftar pesan kesalahan dikembalikan ke halaman form yang sama dan ditampilkan pada komponen pesan kesalahan yang persis sama dengan validasi frontend.

---

## 2. Kamus Validasi Form Pendaftaran Tim Publik

| Nama Field | Aturan Validasi Frontend & Backend | Teks Pesan Kesalahan Standar (Bahasa Indonesia) |
| :--- | :--- | :--- |
| **Nama Tim** | Wajib isi, 3–100 karakter, unik pada event yang sama | - *"Nama tim wajib diisi."*<br>- *"Nama tim minimal terdiri dari 3 karakter."*<br>- *"Nama tim ini sudah terdaftar pada kompetisi yang dipilih."* |
| **Logo Tim** | Wajib unggah, format PNG/JPG/JPEG, ukuran maks 2 MB | - *"Logo resmi tim wajib diunggah."*<br>- *"Format berkas logo harus berupa PNG atau JPG."*<br>- *"Ukuran berkas logo tidak boleh melebihi 2 MB."* |
| **Kota / Asosiasi Asal (Askot/Askab)** | Wajib isi/pilih, 3–120 karakter | - *"Kota atau Asosiasi Asal (Askot/Askab) wajib diisi."* |
| **Nama Manajer Tim** | Wajib isi, 3–100 karakter, hanya huruf dan tanda baca nama | - *"Nama lengkap manajer atau penanggung jawab wajib diisi."* |
| **Email Aktif** | Wajib isi, format email valid, belum terdaftar di sistem | - *"Alamat email aktif wajib diisi."*<br>- *"Format alamat email yang dimasukkan tidak valid."*<br>- *"Email ini sudah terdaftar. Silakan login atau gunakan email lain."* |
| **Password** | Wajib isi, minimal 8 karakter, kombinasi huruf dan angka | - *"Kata sandi wajib diisi."*<br>- *"Kata sandi minimal 8 karakter dan wajib mengandung huruf serta angka."* |
| **Konfirmasi Password** | Wajib isi, harus sama persis dengan kolom Password | - *"Konfirmasi kata sandi wajib diisi."*<br>- *"Konfirmasi kata sandi tidak cocok dengan kata sandi yang dimasukkan."* |
| **No. Telepon / WhatsApp** | Wajib isi, format nomor seluler Indonesia (10–15 digit) | - *"Nomor telepon/WhatsApp aktif wajib diisi."*<br>- *"Masukkan nomor WhatsApp Indonesia yang valid (contoh: 081234567890)."* |
| **Nama Pelatih Kepala** | Wajib isi, 3–100 karakter | - *"Nama pelatih kepala wajib diisi."* |
| **Persetujuan Syarat & Ketentuan** | Wajib dicentang (bernilai benar) | - *"Anda wajib menyetujui syarat, ketentuan, dan regulasi kompetisi untuk melanjutkan pendaftaran."* |

---

## 3. Kamus Validasi Form Data Pemain & Berkas Screening

| Nama Field | Aturan Validasi Frontend & Backend | Teks Pesan Kesalahan Standar (Bahasa Indonesia) |
| :--- | :--- | :--- |
| **Nama Lengkap Pemain** | Wajib isi, 3–100 karakter sesuai Akta Kelahiran | - *"Nama lengkap pemain sesuai Akta Kelahiran wajib diisi."* |
| **Nomor Punggung** | Wajib isi, angka bulat 1–99, unik di dalam tim yang sama | - *"Nomor punggung wajib diisi antara angka 1 hingga 99."*<br>- *"Nomor punggung ini sudah digunakan oleh pemain lain di tim Anda."* |
| **Posisi Bermain** | Wajib pilih salah satu (`GK`, `DF`, `MF`, `FW`) | - *"Posisi bermain wajib dipilih."* |
| **Tanggal Lahir** | Wajib isi, tanggal valid, tidak boleh melewati rentang batas tanggal lahir kategori usia event | - *"Tanggal lahir pemain wajib diisi."*<br>- *"Tanggal lahir pemain tidak memenuhi syarat batas usia pada kategori kompetisi ini."* |
| **NIK / Nomor Identitas** | Wajib isi, tepat 16 digit angka, unik dalam event yang sama (mencegah pemain ganda di dua tim berbeda) | - *"Nomor Induk Kependudukan (NIK) wajib terdiri dari 16 digit angka."*<br>- *"Pemain dengan NIK ini sudah terdaftar di tim lain pada kompetisi yang sama."* |
| **Pas Foto Pemain** | Wajib unggah, format JPG/PNG, maks 2 MB | - *"Pas foto resmi pemain wajib diunggah (format JPG/PNG, maks 2 MB)."* |
| **Berkas Dokumen Wajib (Akta, KK, KIA/Rapor)** | Wajib unggah sesuai persyaratan event, format JPG/PNG/PDF, maks 2 MB per berkas | - *"Berkas [Nama Dokumen] wajib diunggah."*<br>- *"Berkas [Nama Dokumen] harus berformat JPG, PNG, atau PDF dengan ukuran maksimal 2 MB."* |

---

## 4. Kamus Validasi Form Pembayaran & Verifikasi Admin

1. **Form Unggah Bukti Pembayaran Manual (Manajer Tim):**
   - Nama Bank Pengirim: *"Nama bank pengirim wajib diisi."*
   - Nama Pemilik Rekening: *"Nama pemilik rekening pengirim wajib diisi."*
   - Berkas Bukti Transfer: *"Bukti transfer wajib diunggah dalam format JPG, PNG, atau PDF (maksimal 2 MB)."*
   - Masa Berlaku Tagihan: *"Batas waktu pembayaran untuk tagihan ini telah berakhir. Silakan ajukan ulang slot pendaftaran jika kuota masih tersedia."*
2. **Form Keputusan Penolakan Pembayaran / Screening (Admin & Finance):**
   - Catatan Alasan Penolakan: *"Alasan penolakan wajib diisi secara jelas (minimal 10 karakter) sebagai petunjuk perbaikan bagi Manajer Tim."*

---

## 5. Kamus Validasi Kuota Maksimal Event & Pencegahan Race Condition

| Konteks / Field | Aturan Pemeriksaan Atomik (`lockForUpdate`) & FormRequest | Teks Pesan Kesalahan Standar (Bahasa Indonesia) |
| :--- | :--- | :--- |
| **Kuota Pendaftaran Tim (`event_quota`)** | Diperiksa secara eksklusif di dalam transaksi terkunci (`occupied_slots < max_teams`) sesaat sebelum menyimpan tim atau saat klaim ulang slot | - *"Maaf, kuota pendaftaran untuk kompetisi ini baru saja terpenuhi oleh tim lain."* |
| **Jadwal Pendaftaran (`registration_window`)** | Status event wajib `open` dan waktu server berada di antara tanggal buka dan tutup pendaftaran | - *"Pendaftaran untuk kompetisi ini belum dibuka atau telah ditutup oleh panitia."* |
| **Kuota Maksimal pada Form Admin (`max_teams`)** | Wajib bilangan bulat `>= 2` dan tidak boleh lebih kecil dari jumlah slot tim yang sedang aktif/terisi (`occupied_slots`) | - *"Kuota maksimal tim wajib berupa angka minimal 2 tim."*<br>- *"Kuota maksimal tidak boleh dikurangi di bawah jumlah tim yang saat ini sedang memegang slot ([N] tim)."* |
| **Batas Kuota Pemain per Tim (`player_quota`)** | Jumlah pemain yang didaftarkan tidak boleh melampaui `max_players_per_team` pada event terkait | - *"Jumlah pemain pada tim Anda telah mencapai batas kuota maksimal kompetisi ini ([N] pemain)."* |