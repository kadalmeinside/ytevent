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

### 2.1 Pemisahan Mode, Validasi Provider, & Saluran Resmi
- **Pemisahan Mode & Provider (`PaymentService`):**
  - `activeMode()` mengenali `manual`, `midtrans`, `xendit`, atau `gateway`.
  - `isGatewayMode()`, `activeGatewayProvider()`, dan `isProviderEnabled(string $provider)` memvalidasi `payment_gateway_default`, `payment_enabled_providers`, serta kelengkapan kredensial (`midtransServerKey()` / `xenditSecretKey()`) sebelum mengizinkan pembuatan token/invoice gateway.
- **Validasi Enum & Normalisasi `payment_channel`:**
  - [`InitiateGatewayPaymentRequest`](../app/Http/Requests/Manager/InitiateGatewayPaymentRequest.php) hanya menerima nilai saluran pembayaran resmi berdasarkan `PaymentService::allowedChannelsForProvider()` (`MIDTRANS_AVAILABLE_CHANNELS`, `XENDIT_AVAILABLE_CHANNELS`, serta alias resmi). Nilai arbitrary langsung ditolak (`422`).
  - `resolveProviderChannel()` dan `calculateGatewayFee()` memetakan kanal terpilih ke filter provider (`enabled_payments` / `payment_methods`) serta tarif MDR terkait secara konsisten.
- **Larangan Silent Fallback:**
  - Apabila Midtrans menolak saluran pembayaran yang dipilih (`enabled_payments`), sistem **dilarang menghapus filter `enabled_payments` secara diam-diam** untuk mencoba ulang seluruh kanal. Sistem langsung mengembalikan pesan error validasi agar pengguna memilih saluran lain atau menghubungi panitia.

### 2.2 Pembuatan Tagihan & Eksekusi HTTP di Luar DB Transaction
- Setiap tim/peserta memiliki nomor invoice terstruktur (Contoh: `INV-2026-EVT01-0015` atau `INV-2026-AUD01-0015`).
- `initiateGatewayPayment()` dipisahkan menjadi tiga langkah untuk menghindari menahan row lock database selama panggilan HTTP eksternal berlangsung:
  1. **Transaksi Singkat 1 (Claim Lock):** Mengunci `Event` dan `Payment`, menyapu tagihan kedaluwarsa, memeriksa status/kedaluwarsa, menghitung biaya MDR/total dan `providerOrderId`, serta menyimpan `gateway_initiation_lock` (TTL 30 detik) untuk mencegah klik ganda paralel membuat invoice ganda.
  2. **Eksekusi HTTP di Luar Transaksi (`executeGatewayInitiationRequest()`):** Menghubungi Snap API Midtrans (dengan batas `expiry` mengikuti `expires_at`) atau Invoices API Xendit tanpa menahan lock InnoDB.
  3. **Transaksi Singkat 2 (Finalisasi):** Melepas `gateway_initiation_lock`, menjaga idempotensi bila webhook telah melunaskan di tengah penerbangan HTTP, serta menyimpan `method`, `payment_channel`, `gateway_fee`, `total_amount`, `external_reference_id`, `snap_token`/`payment_url`, dan `attempted_order_ids`.

### 2.3 Penanganan Webhook / Callback Pembayaran secara Aman
1. **Verifikasi Keaslian Sinyal (Signature & Token Validation):** Setiap sinyal masuk dari Midtrans diperiksa signature SHA-512 (`order_id + status_code + gross_amount + server_key`) dan callback Xendit diperiksa header `x-callback-token` terhadap `PaymentService::xenditCallbackToken()`. Permintaan tidak sah ditolak (`403 Forbidden`) dan dicatat ke `webhook_logs`.
2. **Validasi Kesesuaian Provider & Status Antrean Manual:**
   - Jika invoice sudah terikat pada provider tertentu (`$lockedPayment->method !== null`), callback dari provider berbeda wajib ditolak (`403 Forbidden`). Tagihan dengan `method === null` (legacy/belum diinisiasi) tetap dapat diproses.
   - Invoice yang sedang berstatus `waiting_verification` (antrean bukti manual) ditolak dari pelunasan gateway sampai proses verifikasi manual selesai.
3. **Validasi Nominal Ketat (`total_amount` ± `payment.amount_tolerance`):**
   - Toleransi default Rp1 (`config('payment.amount_tolerance', 1.0)`).
   - **Underpayment** (`callbackAmount < total_amount - tolerance`, termasuk percobaan membayar nominal pokok tanpa `gateway_fee`) langsung ditolak (`403 Forbidden`).
   - **Overpayment** (`callbackAmount > total_amount + tolerance`) pada sinyal settlement tidak mengaktifkan peserta/tim, melainkan menandai tagihan sebagai `refund_required` disertai catatan selisih di `rejection_reason` dan `AuditLog` (`payment.gateway.overpayment_flagged`) untuk rekonsiliasi Finance.
4. **Transaksi Terkunci, Idempotensi, & Null-Safety Individu:**
   - Handler mengunci baris `Event`, `Payment`, serta `Team` atau `AuditionParticipant` (`lockForUpdate()`) di dalam `DB::transaction()`. Jika invoice sudah berstatus `paid`, `refund_required`, atau `refunded`, sistem langsung mengembalikan respon `200 OK` secara idempoten.
   - Sinyal `EXPIRED` menjaga null-safety saat `team_id` bernilai `null` (event individu), membatalkan peserta (`cancelled`), dan mengembalikan `occupied_slots` sesi bila ada.
5. **Proteksi *Late Settlement* (Over-Quota Guard):**
   - Jika sinyal pelunasan tiba pada invoice yang sudah telanjur `expired` / peserta `cancelled`:
     - Apabila slot masih tersedia (`occupied < limit`), sistem memulihkan slot menjadi `active`, menyinkronkan kuota event, dan mengirim konfirmasi pelunasan (`PaymentConfirmedMail` / `AuditionPaymentConfirmedMail`).
     - Apabila kuota sudah terisi penuh (`occupied >= limit`), sistem **dilarang melampaui batas kuota** — status pembayaran diubah menjadi `refund_required` agar Finance menindaklanjuti pengembalian dana ke kolom terstruktur (`refund_amount`, `refund_reference`, `refund_method`, `refund_notes`, `refunded_at`) via `markRefunded()`.

---

## 3. Alur Verifikasi Pembayaran Manual (Peran Finance)
1. Manajer Tim mengunggah bukti transfer melalui halaman pembayaran khusus manajer sebelum melewati `expires_at`.
2. Di dalam transaksi terkunci (`lockForUpdate()`), sistem memastikan tagihan belum `expired`, lalu memindahkan status invoice menjadi `waiting_verification`. Selama berstatus `waiting_verification`, slot tim ditahan dan **dikecualikan dari pengguguran otomatis timer** sambil menunggu pemeriksaan Finance.
3. Staf Finance memeriksa gambar bukti transfer di layar berdampingan dengan nominal tagihan:
   - **Aksi Setujui (Approve) dengan Guard Kuota:** `approveManualPayment()` terlebih dahulu menyapu tagihan kedaluwarsa (`sweepExpiredForEvent`) dan mengecek sisa kuota event (kecuali tim/peserta terkait). Apabila kuota sudah penuh, tagihan otomatis dialihkan ke `refund_required` (dan tim/peserta ditandai `expired`/`cancelled`) agar kuota tidak terlampaui. Bila kuota masih tersedia, pembayaran diubah ke `paid`, tim/peserta diaktifkan, verifikator dicatat, dan email konfirmasi lunas dikirim (`PaymentConfirmedMail` untuk tim, `AuditionPaymentConfirmedMail` untuk individu).
   - **Aksi Tolak (Reject):** Mewajibkan staf Finance mengisi alasan penolakan, mengubah status invoice menjadi `rejected`, memperpanjang batas waktu unggah ulang secukupnya (*re-upload grace period*, misal `max(expires_at, now + 6 jam)`), dan mengirim email pemberitahuan penolakan agar manajer dapat mengunggah ulang bukti yang valid sebelum slot dilepas.
4. **Penyelesaian Refund Terstruktur (`markRefunded`):** Mencatat `refund_amount`, `refund_reference`, `refund_method`, `refund_notes`, dan `refunded_at` secara terpisah di tabel `payments` saat status dipindahkan dari `refund_required` ke `refunded` (sekaligus mengirim `PaymentRefundedMail` untuk transaksi tim).

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