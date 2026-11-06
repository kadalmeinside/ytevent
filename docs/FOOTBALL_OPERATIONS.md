# FOOTBALL_OPERATIONS.md - Aturan Operasional Kompetisi & Screening Sepak Bola

Dokumen ini merangkum aturan bisnis spesifik penyelenggaraan turnamen dan liga sepak bola agar sistem selaras dengan praktik nyata verifikasi pemain di lapangan.

---

## 1. Aturan Format Kompetisi Multi-Event
Saat Superadmin membuat event baru, sistem menyediakan pengaturan perilaku berdasarkan format yang dipilih:
1. **Format Cup (Sistem Gugur / Knockout):** Fokus pada kuota tim kelipatan bagan (8, 16, 32, 64 tim) dan batas waktu penguncian daftar pemain sebelum undian bagan (*Technical Meeting*) dimulai.
2. **Format League (Kompetisi Penuh):** Mendukung jendela pendaftaran pemain yang lebih panjang dan kuota pemain per tim yang lebih besar (hingga 30 pemain).
3. **Format Group + Knockout (Fase Grup lanjut Gugur):** Kombinasi pembagian grup awal dan babak gugur, umum digunakan pada turnamen regional maupun nasional.
4. **Format Grassroots Festival (Festival Usia Dini U-8 s/d U-12):** Memiliki aturan khusus seperti wajib mencantumkan NISN/KIA, batas usia ketat per tahun kelahiran, dan jumlah pemain dalam skuad yang disesuaikan dengan format lapangan (7v7 atau 9v9).

---

## 2. Standar Operasional Screening Pemain (Anti Pencurian Umur & Pemain Ganda)

Proses *screening* adalah jantung dari kredibilitas kompetisi sepak bola kelompok usia maupun amatir. Sistem menegakkan empat lapisan pemeriksaan:

1. **Penyaringan Tanggal Lahir Otomatis (Age Cut-Off Filter):**
   - Setiap event memiliki aturan batas kelahiran (Contoh: Kategori U-15 mewajibkan pemain lahir antara 1 Januari 2011 hingga 31 Desember 2012).
   - Saat Manajer Tim mengetik tanggal lahir di luar rentang tersebut, sistem langsung menolak input sebelum berkas diunggah.
2. **Pencegahan Pemain Terdaftar di Dua Tim (NIK Duplicate Lock):**
   - Di dalam satu event yang sama, satu Nomor Induk Kependudukan (NIK) hanya boleh terdaftar pada satu tim.
   - Jika Manajer Tim B mencoba mendaftarkan pemain yang NIK-nya sudah didaftarkan oleh Tim A pada event tersebut, sistem otomatis menolak dan menampilkan pesan bahwa pemain tersebut telah terdaftar di tim lain.
3. **Pemeriksaan Dokumen Berdampingan (Visual Cross-Check):**
   - Pada layar *Split-View Screening* milik Admin, verifikator memeriksa keselarasan antara:
     - Nama lengkap, tempat tanggal lahir, dan nama orang tua pada **Akta Kelahiran**.
     - Nomor NIK, tanggal pencetakan dokumen, dan susunan keluarga pada **Kartu Keluarga (KK)**.
     - Kecocokan wajah terbaru pada **Pas Foto**, **Rapor Sekolah / Ijazah**, atau **Kartu Identitas Anak (KIA)**.
4. **Siklus Revisi Terarah (Selective Re-submission):**
   - Ketika sebuah tim mendaftarkan 20 pemain, sering kali 17 pemain dinyatakan lolos dan 3 pemain dinyatakan bermasalah (misal foto akta terpotong).
   - Sistem hanya mengunci 17 pemain yang sudah berstatus `Passed`, dan hanya membuka akses edit/unggah ulang pada 3 pemain yang berstatus `Rejected` tersebut. Manajer tidak perlu menginput ulang seluruh tim.
5. **Mekanisme Tarik Pengajuan (*Recall to Draft*) & Penguncian Berkas:**
   - Setelah pemain diajukan (`submitted`), modal form dan tombol unggah berkas otomatis dikunci demi menjamin dokumen tidak dimanipulasi di tengah-tengah proses pemeriksaan panitia.
   - Apabila manajer tim menyadari adanya salah unggah sebelum panitia memverifikasi, manajer dapat memanfaatkan fitur **Tarik Pengajuan (*Recall*)** untuk mengembalikan status ke `draft` secara resmi. Setiap perubahan status dicatat dalam `screening_logs`.

---

## 3. Aturan Kuota Komposisi Tim & Pengesahan Akhir
1. **Validasi Komposisi Skuad:**
   - Nomor punggung wajib unik di dalam satu tim (1 sampai 99).
   - Setiap tim wajib mendaftarkan minimal jumlah pemain yang ditentukan event (misal minimal 14 pemain) dan wajib memiliki minimal 2 pemain berposisi Penjaga Gawang (`GK`) sebelum dapat mencetak dokumen pengesahan final.
2. **Output Pengesahan Tim (Matchday Readiness):**
   - Pemain yang telah berstatus `Passed` otomatis mendapatkan tanda pengesahan digital.
   - Manajer Tim dan Panitia dapat mengunduh **Album Pemain Resmi (Team Roster Sheet)** yang memuat Logo Tim, Asal Askot/Askab, Nama Manajer, Nama Pelatih, serta kisi foto seluruh pemain yang lolos screening beserta Nama, Nomor Punggung, Tanggal Lahir, dan Posisi untuk keperluan pemeriksaan wasit/pengawas pertandingan di pinggir lapangan.