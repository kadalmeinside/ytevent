# PHASE_3_IMPLEMENTATION_PLAN.md - Rencana Implementasi Teknis & Arsitektur Fase 3

Dokumen ini memuat arsitektur teknis terperinci, alur kerja *end-to-end*, spesifikasi state global, sistem lencana (*counter badges*), serta panduan transisi antar-tahap untuk pengerjaan **Fase 3: In-Web Notification Engine, Live Sidebar Badges (Admin & Manajer), Peringatan Akreditasi Skuad, & Alur Utuh Pendaftaran Tim (*Guided End-to-End Team Readiness Flow*)**.

---

## 1. Tujuan & Ruang Lingkup Fase 3

Fase 3 berfokus pada **visibilitas operasional *real-time* di dalam web (*in-web notifications & counter badges*)** serta **pemandu alur kerja otomatis (*guided step-by-step workflow*)** agar setiap pengguna (baik Panitia Admin maupun Manajer Tim) langsung mengetahui tindakan apa yang harus dilakukan ketika satu syarat telah terpenuhi hingga tim selesai dan utuh menjadi peserta resmi yang terakreditasi:

1. **Global In-Web Notification & Live Counter Badges di Portal Admin (`AdminLayout.vue` & `Admin/Dashboard.vue`)**:
   - Lencana angka (*counter badge*) *real-time* pada menu sidebar panitia:
     - **Screening Pemain (`/admin/screening`)**: Jumlah pemain berstatus `submitted` yang menunggu verifikasi berkas.
     - **Verifikasi Transfer (`/admin/payments/manual-verification`)**: Jumlah bukti pembayaran manual berstatus `waiting_verification`.
     - **Antrean Email (`/admin/system/email-logs`)**: Jumlah pengiriman email berstatus `failed` yang memerlukan *retry*.
     - **Daftar Tim (`/admin/teams`)**: Indikator tim yang siap akreditasi (`all_passed`) dan tim yang telah mengunci skuad (`locked_roster`).
   - Baris Pintas Notifikasi Operasional (*Operational Alert Strip*) di header/dashboard admin untuk navigasi 1-klik ke antrean aktif.
   - Banner Penyelesaian Screening Tim pada `Admin/Screening/TeamReview.vue` saat seluruh pemain dalam satu tim telah disahkan (`all_passed`).

2. **Global In-Web Notification & Live Counter Badges di Portal Manajer (`ManagerLayout.vue`)**:
   - Lencana status & penghitung (*status/counter badges*) dinamis pada setiap menu sidebar manajer:
     - **Pembayaran Pendaftaran**: Badge `BAYAR` / `VERIFIKASI` / `REVISI` / `LUNAS`.
     - **Daftar Pemain & Berkas**: Badge jumlah pemain `Revisi` (merah) jika ada yang ditolak, jumlah `Draft` (amber) jika belum diajukan, jumlah `Proses` (indigo) saat diperiksa panitia, atau jumlah `Sah` (hijau).
     - **Pelatih & Ofisial**: Badge `WAJIB` (amber) jika belum ada ofisial saat tim sudah aktif, atau jumlah staf terdaftar.
     - **Akreditasi & Skuad**: Badge menonjol **`SIAP KUNCI`** ketika seluruh syarat skuad terpenuhi dan siap dikunci, atau **`TERKUNCI`** setelah akreditasi selesai.

3. **Peringatan Otomatis Akreditasi Saat Seluruh Pemain Sah (*Smart Accreditation Call-to-Action Banner*)**:
   - Begitu seluruh syarat kelayakan skuad terpenuhi (Tim aktif/lunas, kuota minimal pemain sah terpenuhi, minimal 2 Kiper/GK sah terpenuhi, minimal 1 Ofisial terdaftar, dan 0 pemain tersisa di antrean review), sistem memunculkan **Banner Peringatan Prioritas Tinggi** di seluruh halaman Portal Manajer (`Dashboard`, `Players`, `Officials`) yang menginstruksikan manajer untuk segera melakukan **Kunci Skuad Final** dan mengunduh **ID Card & Album Roster**.

4. **Alur Utuh 5 Tahap Pendaftaran Tim (*5-Stage End-to-End Team Readiness Stepper*)**:
   - Pembaruan menyeluruh pada `Manager/Dashboard.vue`, `Manager/Players.vue`, dan `Manager/Officials.vue` dengan pelacak 5 tahap berurutan dari awal hingga tuntas:
     - **Tahap 1 — Reservasi Slot & Pelunasan Biaya Pendaftaran**
     - **Tahap 2 — Pendaftaran Skuad Pemain (Kuota Minimal & Syarat 2 GK) + Dokumen**
     - **Tahap 3 — Pendaftaran Pelatih Kepala & Ofisial Pendamping**
     - **Tahap 4 — Pengajuan & Pemeriksaan Screening oleh Panitia**
     - **Tahap 5 — Kunci Skuad Final & Penerbitan Akreditasi Resmi (Album Roster & ID Card)**
   - Setiap kali satu syarat terpenuhi, sistem secara otomatis mengarahkan manajer ke syarat berikutnya melalui kartu panduan kontekstual.
   - Perbaikan mode baca (*Read-Only Mode*) pada halaman `Manager/Players.vue` ketika skuad telah berstatus `locked_roster` agar daftar pemain tetap tampil lengkap beserta tautan unduh ID Card.

---

## 2. Arsitektur Data Shared Props (`HandleInertiaRequests` & `ResolvesActiveTeam`)

### 2.1 Shared Prop `adminNotifications` (Khusus Role Internal Panitia)
Dibagikan secara otomatis melalui `HandleInertiaRequests::share()` untuk pengguna yang memiliki role `superadmin`, `finance`, atau `screening_officer`:

```php
'adminNotifications' => [
    'pending_payments_count' => int,        // Payment status = waiting_verification
    'refund_required_count' => int,         // Payment status = refund_required
    'pending_screening_players_count' => int, // Player screening_status = submitted
    'pending_screening_teams_count' => int, // Team active dengan player submitted
    'ready_to_lock_teams_count' => int,     // Team active & screening_status = all_passed
    'locked_teams_count' => int,            // Team status = locked_roster
    'failed_emails_count' => int,           // EmailNotificationLog status = failed
    'total_actionable' => int,              // Total antrean yang butuh tindakan segera
]
```

### 2.2 Objek `workflow` pada `team` & Shared Prop `managerWorkflow` (Khusus Manajer Tim)
Dibagikan melalui `ResolvesActiveTeam::formatTeamForManager()` sehingga selalu tersedia di `props.team.workflow` pada seluruh halaman Manajer (`Dashboard`, `Payments`, `Players`, `Officials`, `Accreditation`, `TeamProfile`):

```php
'workflow' => [
    'current_step' => int, // 1..5
    'is_paid' => bool,
    'is_min_players_met' => bool,
    'is_gk_met' => bool,
    'is_officials_met' => bool,
    'is_all_submitted_or_passed' => bool,
    'is_ready_to_lock' => bool,
    'is_roster_locked' => bool,
    'roster_locked_at' => ?string,
    'counts' => [
        'players_total' => int,
        'players_draft' => int,
        'players_submitted' => int,
        'players_passed' => int,
        'players_rejected' => int,
        'players_docs_complete' => int,
        'gk_total' => int,
        'gk_passed' => int,
        'min_players_required' => int,
        'max_players_allowed' => int,
        'min_gk_required' => 2,
        'officials_total' => int,
        'min_officials_required' => 1,
    ],
    'badges' => [
        'payment' => ['label' => string, 'variant' => string],
        'players' => ['label' => string, 'variant' => string],
        'officials' => ['label' => string, 'variant' => string],
        'accreditation' => ['label' => string, 'variant' => string],
    ],
    'banner' => [
        'type' => string,       // urgent_accreditation | completed_accredited | action_rejected | action_draft | action_quota | info_screening | action_payment
        'variant' => string,    // emerald | orange | rose | amber | indigo
        'title' => string,
        'message' => string,
        'cta_label' => ?string,
        'cta_route' => ?string,
        'secondary_cta_label' => ?string,
        'secondary_cta_route' => ?string,
    ],
]
```

---

## 3. Matriks Kondisi & Transisi Alur Utuh Tim (*End-to-End State Machine*)

| Tahap | Kondisi Pemicu | Badge Sidebar Aktif | Pesan Banner In-Web & Aksi Selanjutnya |
| :--- | :--- | :--- | :--- |
| **1A. Menunggu Pembayaran** | `team.status == 'pending_payment'` & `payment.status == 'unpaid'` | Pembayaran: `BAYAR` (Orange) | **Slot Ditahan Sementara**: Selesaikan pembayaran sebelum batas waktu habis $\rightarrow$ Tombol **Bayar Sekarang**. |
| **1B. Verifikasi Finance** | `payment.status == 'waiting_verification'` | Pembayaran: `VERIFIKASI` (Amber) | **Bukti Transfer Sedang Diperiksa**: Menunggu konfirmasi Finance $\rightarrow$ Tombol **Lihat Status Tagihan**. |
| **1C. Bukti Ditolak** | `payment.status == 'rejected'` | Pembayaran: `REVISI` (Rose) | **Bukti Transfer Ditolak**: Unggah ulang bukti transfer yang valid sebelum masa tenggang berakhir $\rightarrow$ Tombol **Unggah Ulang Bukti**. |
| **2A. Revisi Pemain Ditolak** | `is_paid` & `players_rejected > 0` | Pemain: `X REVISI` (Rose) | **Penting — Ada Pemain Perlu Revisi Berkas**: Terdapat `X` pemain ditolak panitia. Perbaiki berkas dan ajukan ulang $\rightarrow$ Tombol **Perbaiki Berkas Pemain**. |
| **2B. Kuota Pemain / GK Kurang** | `is_paid` & (`players_total < min` atau `gk_total < 2`) | Pemain: `X/Min` (Amber) | **Lengkapi Komposisi Skuad**: Tambahkan minimal `Min` pemain dan wajib minimal `2 GK` $\rightarrow$ Tombol **Tambah Pemain**. |
| **2C. Ofisial Belum Diisi** | `is_paid` & `officials_total < 1` | Ofisial: `WAJIB` (Amber) | **Data Pelatih & Ofisial Belum Lengkap**: Daftarkan minimal 1 Pelatih Kepala / Ofisial tim $\rightarrow$ Tombol **Tambah Ofisial Tim**. |
| **2D. Pemain Masih Draft** | `is_paid` & `players_draft > 0` | Pemain: `X DRAFT` (Amber) | **Ajukan Pemain untuk Screening**: Terdapat `X` pemain berstatus Draft. Lengkapi berkas wajib dan klik *Ajukan Screening* $\rightarrow$ Tombol **Kelola & Ajukan Pemain**. |
| **3. Menunggu Screening** | `is_paid` & `players_submitted > 0` & `players_rejected == 0` | Pemain: `X PROSES` (Indigo) | **Berkas Sedang Diperiksa Panitia**: `X` pemain sedang dalam antrean verifikasi panitia (`Y` pemain telah sah) $\rightarrow$ Tombol **Pantau Status Screening**. |
| **4. SIAP AKREDITASI (Semua Sah)** | `is_ready_to_lock == true` | Akreditasi: `SIAP KUNCI` (Orange/Emerald) | **PERINGATAN AKREDITASI — Seluruh Skuad Telah Sah!**: Semua syarat pemain (`>= Min`), kiper (`>= 2 GK`), dan ofisial telah terpenuhi. Segera **Kunci Skuad Final** untuk menerbitkan ID Card & Album Roster $\rightarrow$ Tombol **Kunci Skuad & Akreditasi Sekarang**. |
| **5. SELESAI & TERAKREDITASI** | `team.status == 'locked_roster'` | Akreditasi: `TERKUNCI` (Emerald) | **Tim Resmi Terdaftar & Terakreditasi Penuh**: Skuad telah dikunci resmi. Dokumen pertandingan siap digunakan $\rightarrow$ Tombol **Unduh Album Roster (PDF)** & **Unduh ID Cards (PDF)**. |

---

## 4. Rincian Langkah Implementasi (Milestones Fase 3)

### Milestone 3.1: Engine Workflow & In-Web Notification Shared State (Backend)
- [x] **3.1.1** Pembuatan helper kalkulasi workflow tim secara terpusat di `ResolvesActiveTeam.php` (`buildTeamWorkflow(Team $team)`).
- [x] **3.1.2** Penyatuan resolusi tim aktif di `AccreditationController.php` menggunakan trait `ResolvesActiveTeam` agar perpindahan tim (`switchTeam`) dan properti `can_manage_roster` serta `workflow` konsisten di seluruh halaman.
- [x] **3.1.3** Penambahan shared prop `adminNotifications` di `HandleInertiaRequests.php` untuk menyajikan counter *real-time* antrean pembayaran manual, antrean screening pemain, email gagal, serta statistik kesiapan akreditasi tim.
- [x] **3.1.4** Pengayaan metrik `Admin/DashboardController.php` dengan counter tim berstatus `all_passed` (siap kunci skuad) dan `locked_roster` (selesai akreditasi).

### Milestone 3.2: Komponen Banner Alur Pintar & Live Sidebar Badges
- [x] **3.2.1** Pembuatan komponen reusable `resources/js/Components/WorkflowStepBanner.vue` dengan tema *Persija Red-Orange Liquid Glass* dan kode warna kontekstual (`emerald`, `orange`, `rose`, `amber`, `indigo`) bebas dot/pulse/emoji.
- [x] **3.2.2** Pembaruan `ManagerLayout.vue`:
  - Menampilkan badge dinamis pada setiap item navigasi sidebar (`Pembayaran`, `Daftar Pemain`, `Pelatih & Ofisial`, `Akreditasi & Skuad`).
  - Menampilkan `WorkflowStepBanner` secara otomatis di bagian atas ruang kerja manajer sehingga peringatan akreditasi dan panduan langkah berikutnya selalu terlihat jelas.
- [x] **3.2.3** Pembaruan `AdminLayout.vue`:
  - Menampilkan badge angka (`counter pill`) pada menu sidebar **Screening Pemain**, **Verifikasi Transfer**, dan **Antrean Email**.
  - Menampilkan *Quick Operational Notification Strip* di header desktop admin saat ada antrean yang membutuhkan tindakan panitia.

### Milestone 3.3: 5-Stage End-to-End Team Readiness Stepper & Penyempurnaan Halaman
- [x] **3.3.1** Pembaruan `Manager/Dashboard.vue`:
  - Mengganti daftar 3 langkah lama menjadi **5-Stage End-to-End Team Readiness Stepper** (1. Pembayaran, 2. Skuad & 2 GK, 3. Pelatih & Ofisial, 4. Screening Panitia, 5. Kunci Skuad & ID Card).
  - Menambahkan kartu status progres akreditasi dan tombol pintas unduh PDF ketika skuad telah terkunci.
- [x] **3.3.2** Pembaruan `Manager/Players.vue`:
  - Memperbaiki tampilan saat `team.status === 'locked_roster'` agar daftar pemain tetap ditampilkan dalam mode baca (*Read-Only*) lengkap dengan banner akreditasi hijau, bukan pesan "pembayaran belum lunas".
  - Menambahkan bar indikator syarat skuad (*Kuota Minimal*, *Minimal 2 GK*, *Status Draft/Revisi/Sah*) dan tombol pintas langsung ke halaman Akreditasi ketika syarat telah terpenuhi.
- [x] **3.3.3** Pembaruan `Manager/Officials.vue`:
  - Memperbaiki tampilan saat `locked_roster` dan menambahkan panduan langkah selanjutnya setelah ofisial berhasil ditambahkan.
- [x] **3.3.4** Pembaruan `Admin/Dashboard.vue` & `Admin/Screening/TeamReview.vue`:
  - Menambahkan tautan langsung ke antrean screening serta ringkasan jumlah tim yang siap akreditasi vs sudah mengunci skuad di `Admin/Dashboard.vue`.
  - Menambahkan banner informasi kelolosan penuh tim (*All Players Passed*) beserta tombol unduh Roster Sheet PDF di `Admin/Screening/TeamReview.vue`.

### Milestone 3.4: Pengujian Otomatis (Automated Tests) & Build Produksi
- [x] **3.4.1** Penulisan Feature Test `tests/Feature/WorkflowAndNotificationBadgeTest.php` untuk memverifikasi:
  - Kebenaran perhitungan `adminNotifications` (antrean bayar, antrean screening, email gagal, tim siap kunci, tim terkunci).
  - Transisi otomatis `team.workflow` dari tahap 1 (belum bayar) $\rightarrow$ tahap 2 (kurang pemain/GK/ofisial/draft) $\rightarrow$ tahap 3 (sedang screening) $\rightarrow$ tahap 4 (`is_ready_to_lock = true` + peringatan akreditasi) $\rightarrow$ tahap 5 (`locked_roster`).
  - Akses halaman `Manager/Players` saat `locked_roster` tetap merender daftar pemain secara utuh.
- [x] **3.4.2** Eksekusi seluruh test suite (`php artisan test`) dan kompilasi produksi (`npm run build`).
