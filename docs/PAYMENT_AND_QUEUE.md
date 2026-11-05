# PAYMENT_AND_QUEUE.md - Spesifikasi Pembayaran, Webhook, & Antrean Email

Dokumen ini mengatur mekanisme integrasi pembayaran otomatis (Midtrans & Xendit), alur verifikasi manual oleh Finance, pengendalian kuota tim, serta arsitektur antrean email latar belakang (Queue).

---

## 1. Pengendalian Kuota Maksimal Tim & Pencegahan Race Condition Pendaftaran
Dalam turnamen populer, sering terjadi lonjakan pendaftaran serentak saat kuota hampir habis (misal 5 tim berebut 1 slot terakhir dari kuota 32 tim):

1. **Pessimistic Row-Level Locking (`SELECT ... FOR UPDATE`):**
   - Pemeriksaan dan reservasi kuota wajib dijalankan di dalam satu transaksi database (`DB::transaction()`) dengan mengunci baris event secara eksklusif (`Event::whereKey($eventId)->lockForUpdate()->firstOrFail()`).
   - Permintaan pendaftaran paralel pada event yang sama akan diantrekan secara serial oleh mesin transaksi InnoDB sehingga tidak mungkin ada dua tim yang membaca sisa slot yang sama secara bersamaan.
2. **Lazy Expiration + Perhitungan Slot Akurat (`occupied_slots`):**
   - Tepat setelah baris event dikunci dan sebelum menghitung sisa kuota, sistem menjalankan *Lazy Expiration Sweep* pada event tersebut: setiap tim `pending_payment` yang invoice-nya masih `unpaid` namun sudah melewati `expires_at` langsung diubah menjadi `expired`.
   - `occupied_slots` dihitung dari tim berstatus `pending_payment` (yang belum kedaluwarsa atau sedang `waiting_verification`), `active`, dan `locked_roster`.
   - Jika `occupied_slots >= max_teams`, transaksi dibatalkan, status event disinkronkan menjadi `quota_full`, dan pengguna menerima pesan bahwa kuota baru saja penuh.
   - Jika `occupied_slots < max_teams`, tim disimpan (`pending_payment`), invoice dibuat (`unpaid` dengan `expires_at`), dan apabila slot yang diambil adalah slot terakhir (`occupied_slots + 1 == max_teams`), status event langsung diperbarui menjadi `quota_full` sebelum transaksi di-`commit`.
3. **Batas Waktu Pembayaran (Temporary Slot Hold & Scheduled Release):**
   - Setiap pendaftaran baru menahan 1 slot sementara hingga batas waktu pelunasan (`payment_deadline_hours`, misal 24 jam).
   - Selain *Lazy Expiration* saat ada pendaftar baru, pekerjaan terjadwal (`Scheduler` setiap menit) menyapu seluruh tagihan `unpaid` yang telah melewati `expires_at` menjadi `expired`, melepas slot tim terkait (`expired`), serta mengembalikan status event dari `quota_full` ke `open` apabila masa pendaftaran masih aktif.
4. **Klaim Ulang Slot (*Reclaim Slot*):**
   - Tim yang pendaftarannya berubah menjadi `expired` tidak perlu membuat akun baru; Manajer Tim cukup menekan tombol **Ajukan Ulang Slot** di halaman pembayaran yang akan memeriksa dan mengunci sisa kuota menggunakan mekanisme `lockForUpdate()` yang sama.

---

## 2. Arsitektur Integrasi Payment Gateway (Midtrans & Xendit)

### 2.1 Pembuatan Tagihan & Permintaan Pembayaran
- Setiap tim memiliki nomor invoice terstruktur (Contoh: `INV-2026-CUP01-0015`).
- Saat Manajer Tim memilih bayar otomatis melalui Midtrans atau Xendit, server mengirimkan rincian tagihan, data tim, dan batas waktu kedaluwarsa (`expiry` disamakan dengan `expires_at` slot) ke penyedia gateway agar kanal pembayaran otomatis tertutup saat masa reservasi slot berakhir.

### 2.2 Penanganan Webhook / Callback Pembayaran secara Aman
1. **Verifikasi Keaslian Sinyal (Signature Validation):** Setiap sinyal masuk dari Midtrans maupun Xendit wajib diperiksa keaslian kunci kriptografinya menggunakan kunci rahasia server. Permintaan tanpa tanda tangan yang sah langsung ditolak (`403 Forbidden`).
2. **Transaksi Terkunci & Prinsip Idempotensi:** Handler webhook wajib mengunci baris `Event` dan `Payment` (`lockForUpdate()`) di dalam `DB::transaction()`. Jika invoice sudah berstatus `paid`, sistem langsung mengembalikan respon `200 OK` tanpa memicu pengiriman email ganda atau pencatatan keuangan ganda.
3. **Pembaruan Status Otomatis & Proteksi *Late Settlement* (Over-Quota Guard):**
   - Jika invoice berstatus `unpaid` atau `rejected` (masih memegang slot), status pembayaran diubah menjadi `paid` dan status tim menjadi `active`.
   - Jika terjadi sinyal pelunasan terlambat (*late webhook settlement*) pada invoice yang sudah telanjur `expired`:
     - Apabila `occupied_slots < event.max_teams`, sistem memulihkan slot tim menjadi `active` dan mencatat pembayaran `paid` (serta memperbarui status event ke `quota_full` jika menjadi slot terakhir).
     - Apabila `occupied_slots >= event.max_teams` (slot sudah terisi penuh oleh tim lain), sistem **dilarang melampaui `max_teams`** — status pembayaran diubah menjadi `refund_required` agar tim Finance segera memproses pengembalian dana atau tindak lanjut manual.

---

## 3. Alur Verifikasi Pembayaran Manual (Peran Finance)
1. Manajer Tim mengunggah bukti transfer melalui halaman pembayaran khusus manajer sebelum melewati `expires_at`.
2. Di dalam transaksi terkunci (`lockForUpdate()`), sistem memastikan tagihan belum `expired`, lalu memindahkan status invoice menjadi `waiting_verification`. Selama berstatus `waiting_verification`, slot tim ditahan dan **dikecualikan dari pengguguran otomatis timer** sambil menunggu pemeriksaan Finance.
3. Staf Finance memeriksa gambar bukti transfer di layar berdampingan dengan nominal tagihan:
   - **Aksi Setujui (Approve):** Mengubah status menjadi `paid`, mengaktifkan status tim menjadi `active`, mencatat nama staf Finance pemeriksa beserta waktunya, membuka akses pendaftaran pemain bagi tim tersebut, dan mengirim email lunas.
   - **Aksi Tolak (Reject):** Mewajibkan staf Finance mengisi alasan penolakan, mengubah status invoice menjadi `rejected`, memperpanjang batas waktu unggah ulang secukupnya (*re-upload grace period*, misal `max(expires_at, now + 6 jam)`), dan mengirim email pemberitahuan penolakan agar manajer dapat mengunggah ulang bukti yang valid sebelum slot dilepas.

---

## 4. Spesifikasi Sistem Antrean Email (Background Queue)

Seluruh pengiriman email menggunakan pekerja antrean latar belakang dengan kebijakan percobaan ulang otomatis sebanyak 3 kali apabila terjadi gangguan jaringan sementara pada server email.

### Daftar Template & Pemicu Email Transaksional:

| Nama Antrean Email | Pemicu Pengiriman | Penerima | Isi Utama Pesan Email |
| :--- | :--- | :--- | :--- |
| **1. Pendaftaran Tim Berhasil** | Sesaat setelah form pendaftaran publik berhasil disimpan ke database | Email Manajer Tim | Ucapan selamat datang, ringkasan nama tim & event, informasi email login, rincian nomor invoice pendaftaran, batas waktu bayar, dan tombol menuju halaman pembayaran. |
| **2. Pembayaran Pendaftaran Lunas** | Webhook Midtrans/Xendit sukses ATAU disetujui manual oleh Finance | Email Manajer Tim | Tanda terima lunas resmi, perubahan status tim menjadi Aktif, dan tombol pintas untuk mulai mengisi daftar pemain beserta berkas screening. |
| **3. Pembayaran Manual Ditolak** | Staf Finance menekan tombol Tolak pada bukti transfer manual | Email Manajer Tim | Pemberitahuan bahwa bukti bayar belum dapat disetujui, kutipan alasan penolakan dari staf Finance, serta tautan untuk mengunggah ulang bukti transfer. |
| **4. Hasil Verifikasi Screening Pemain** | Admin menekan tombol Kirim Hasil Screening pada panel verifikasi tim | Email Manajer Tim | Ringkasan jumlah pemain yang diperiksa, tabel daftar nama pemain yang **Lolos Screening**, serta tabel daftar nama pemain yang **Gagal Screening** lengkap dengan catatan alasan penolakan per pemain dan tautan menuju portal perbaikan berkas. |
| **5. Pengesahan Roster Final Tim** | Seluruh pemain dalam kuota tim telah dinyatakan Lolos Screening atau roster dikunci | Email Manajer Tim | Konfirmasi bahwa skuad tim telah sah sepenuhnya untuk bertanding, disertai tautan untuk mengunduh lembar pengesahan tim dan kartu identitas pertandingan. |