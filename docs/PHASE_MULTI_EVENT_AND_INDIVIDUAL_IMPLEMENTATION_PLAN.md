# Implementation Plan: Ekosistem Multi-Event & Audisi Bakat Individual (Versi 2.0)

Dokumen ini adalah panduan teknis langkah-demi-langkah (*step-by-step technical implementation plan*) untuk merealisasikan **Ekosistem Multi-Event & Audisi/Seleksi Bakat Individual** sesuai dengan [PRD_MULTI_EVENT_INDIVIDUAL.md](file:///Users/macbook/Application/ytevent/docs/PRD_MULTI_EVENT_INDIVIDUAL.md).

---

## Ringkasan 6 Sub-Fase Pekerjaan:

```
[Sub-Fase 1: Kuota Dinamis & Form Presets]
      │
      ▼
[Sub-Fase 2: Universal Account & Unified Dashboard]
      │
      ▼
[Sub-Fase 3: Registrasi Audisi, Sesi Waktu & Screening]
      │
      ▼
[Sub-Fase 4: Nomor Dada PDF & Scanner Absensi Multi-Hari]
      │
      ▼
[Sub-Fase 5: Mobile Quick Scouting Web Panel]
      │
      ▼
[Sub-Fase 6: Kelolosan Bertahap, Email & E-Sertifikat]
```

---

## 🔹 SUB-FASE 1: Konfigurasi Kuota Dinamis & Custom Form Presets Engine

### 1.1 Migrasi Database Tabel `events`
* Tambahkan kolom:
  - `event_type`: enum `['tournament', 'audition', 'coaching_clinic', 'festival']` default `'tournament'`.
  - `participant_type`: enum `['team', 'individual']` default `'team'`.
  - `quota_type`: enum `['fixed', 'unlimited']` default `'fixed'`.
  - `max_participants`: integer unsigned nullable.
  - `position_quota_caps`: json nullable (misal `{"GK": 10, "DF": 30, "MF": 30, "FW": 30}`).
  - `form_presets`: json nullable (misal `{"position": true, "foot": true, "team_origin": true, "experience": true, "jersey": true}`).
  - `custom_fields_schema`: json nullable (array objek definisi field dinamis).

### 1.2 Model `Event.php` & Helper Logic
* Tambahkan relasi dan cast attributes untuk field JSON di atas.
* Modifikasi helper `availableSlotsCount()`:
  - Jika `quota_type === 'unlimited'`, kembalikan `null` atau kapasitas terbuka.
* Modifikasi `canAcceptRegistration()`:
  - Jika `quota_type === 'unlimited'`, pendaftaran diterima selama status `open` dan berada dalam jendela waktu `isWithinRegistrationWindow()`.
  - Jika `quota_type === 'fixed'`, wajib memenuhi ketersediaan slot (`availableSlotsCount() > 0`).

### 1.3 Pembaruan Form Admin (`Admin/Events/Form.vue`)
* Tambahkan kartu konfigurasi:
  - Radio button: **Tipe Event** (*Turnamen Tim*, *Audisi Pemain*, *Coaching Clinic*, *Festival*).
  - Radio button: **Mode Pembatasan Pendaftaran** (*Kuota Terbatas / Fixed Quota* vs *Buka Sampai Batas Waktu / Unlimited until Deadline*).
  - Bagian **Preset Formulir Pendaftaran (Custom Form Presets)** dengan sakelar toggle visual:
    - [x] Wajibkan Posisi Lapangan (GK/DF/MF/FW)
    - [x] Wajibkan Kaki Dominan (Kanan/Kiri/Kedua Kaki)
    - [x] Wajibkan Asal Tim / SSB / Sekolah
    - [x] Wajibkan Antropometri (Tinggi & Berat Badan)
    - [x] Wajibkan Riwayat Pengalaman / Prestasi
    - [x] Wajibkan Ukuran Jersey / Rompi (S/M/L/XL/XXL)
  - Builder sederhana untuk **Custom Questions** (Label pertanyaan, tipe input, opsi).

### 1.4 Pembaruan Tampilan Publik (`Public/EventDetail.vue`)
* Jika event bertipe `quota_type === 'unlimited'`:
  - Ganti progress bar slot dengan badge: *"🟢 Pendaftaran Terbuka s.d. [Tanggal Closing]"* dan counter jumlah pendaftar.
* Jika event bertipe `quota_type === 'fixed'`:
  - Tetap menampilkan progress bar slot kuota terisi.

### 1.5 Automated Tests Sub-Fase 1
* Buat Feature Test: `Phase7DynamicQuotaAndFormPresetsTest.php`.
* Uji pembuatan event dengan mode unlimited quota.
* Uji validasi pendaftaran mode unlimited melewati kuota tanpa terblokir.

---

## 🔹 SUB-FASE 2: Universal Account & Unified Portal Header / Dashboard

### 2.1 Perubahan Header Publik (`PublicLayout.vue`)
* Ubah tombol navigasi `"Login Manajer"` $\rightarrow$ **`"Masuk"`** atau **`"Masuk / Akun Saya"`**.
* Arahkan ke rute terpadu `/login`.
* Dropdown profil setelah login: Dasbor Terpadu, Tim Saya, Pendaftaran Audisi Anak, Invoice/Pembayaran, Logout.

### 2.2 Dashboard Terpadu (`Manager/Dashboard.vue` atau `Dashboard.vue`)
* Menampilkan dua tab/seksi:
  - **🏆 Tim Turnamen yang Anda Kelola**: Kartu tim turnamen, status bayar, kelola pemain, roster, jadwal.
  - **⭐ Pendaftaran Audisi & Seleksi Anak**: Kartu data anak, status screening, unduh nomor dada, rapor hasil tes.

---

## 🔹 SUB-FASE 3: Pendaftaran Audisi, Sesi Waktu, & Screening Dokumen

### 3.1 Migrasi Database
* Tabel `audition_stages` (Hari 1, Hari 2, Hari 3).
* Tabel `event_sessions` (Gelombang/Sesi Waktu Lapangan).
* Tabel `audition_participants` (Biodata atlet, antropometri, kontak darurat, persetujuan medis, status berjenjang).

### 3.2 Halaman Form Pendaftaran Audisi (`Public/RegisterAudition.vue`)
* Auto-fill data wali jika pengguna telah login.
* Dynamic field rendering berdasarkan `form_presets` dan `custom_fields_schema` event.
* Checkbox wajib Pernyataan Bebas Tanggung Jawab Medis (*Liability Waiver*).
* Integrasi pembayaran otomatis (Midtrans VA/QRIS atau Manual Transfer).

### 3.3 Panel Verifikasi Dokumen Screening Admin
* Antarmuka review berkas anak (Akta Kelahiran, KK, Pas Foto).
* Validasi umur otomatis berdasarkan cut-off usia.
* Aksi Approve (Passed) vs Reject (wajib alasan penolakan + *re-upload grace period* 24 jam).

---

## 🔹 SUB-FASE 4: Nomor Dada (Bib Tag PDF) & Absensi Multi-Hari QR Scanner

### 4.1 Generator PDF Nomor Dada (`barryvdh/laravel-dompdf`)
* Layout A4 Landscape beresolusi tinggi.
* Nomor dada berukuran besar di tengah.
* Pas foto pemain, nama panggilan, posisi utama, logo resmi event.
* QR Code unik terenkripsi untuk check-in lapangan.
* SOP: Tombol cetak hanya aktif jika LUNAS dan LOLOS SCREENING.

### 4.2 Web Scanner QR Kehadiran Lapangan (`/admin/audition/scanner`)
* Perekaman absensi harian per hari (Hari 1, Hari 2, dst.) pada tabel `audition_attendances`.
* Validasi gerbang (*Gatekeeper Guard*): menolak scan pemain yang tidak lolos di hari sebelumnya.
* Penyelarasan rompi fisik panitia di meja pendaftaran ulang.

---

## 🔹 SUB-FASE 5: Mobile Quick Scouting Web Panel

### 5.1 Peran RBAC & Akses `talent_scout`
* Role baru `talent_scout` dengan izin khusus penilaian audisi.

### 5.2 Antarmuka Web Pemandu Bakat (`/scout/events/{event}`)
* Mobile-first untuk tablet / smartphone pelatih di pinggir lapangan.
* Quick Search Nomor Dada.
* Penilaian Cepat Lapangan (*Field Quick Grade*):
  - Rekomendasi: `Lolos (A)`, `Cadangan (B)`, `Gugur (C)`.
  - Skor Bintang: 1 s/d 5.
  - Chip Tag Keunggulan (*#KecepatanTinggi*, *#VisiBagus*, dll.).
  - Catatan teks pelatih.
* Sistem Blind Scoring (pelatih tidak melihat skor pelatih lain saat pertandingan berjalan).
* Fitur kunci penilaian harian (*locked session*) oleh Head Coach.

---

## 🔹 SUB-FASE 6: Kelolosan Bertahap, Siklus Email Otomatis, & E-Sertifikat

### 6.1 Panel Seleksi & Kelulusan Bertahap Admin
* Admin/Head Coach menyaring pemain dari Hari 1 $\rightarrow$ Hari 2 $\rightarrow$ Final Day.

### 6.2 Otomatisasi Siklus Email
* Background job pengiriman email hasil seleksi per hari (rilis terjadwal sebelum 18.00 WIB).
* Lampiran tiket Hari 2 bagi yang lolos vs link rapor & e-sertifikat bagi yang belum lolos.

### 6.3 E-Sertifikat Berjenjang & Rapor Digital
* Template PDF Sertifikat Keikutsertaan (Hari 1), Finalis (Hari 2/3), dan Pemain Terpilih (Akademi).
* Halaman verifikasi publik QR Code sertifikat.
* Unduhan Rapor Evaluasi Pelatih untuk orang tua murid.

