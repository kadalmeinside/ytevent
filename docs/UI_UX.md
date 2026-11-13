# UI/UX Design Specification: High-Density Operational Utility + Liquid Glass Persija Red-Orange

## 1. Filosofi Desain & Karakter Visual
Aplikasi dirancang sebagai **perangkat kerja operasional sepak bola berdensitas tinggi** (seperti portal kompetisi resmi FIFA TMS / Premier League) yang dipadukan dengan estetika **Ultra-Clean Liquid Glass** dan identitas warna **Persija Red-Orange (Merah-Oranye Api / Macan Kemayoran)**.

- **Vibrant & Energetic yet Professional:** Dominasi oranye menyala (`#EA580C` / `#F97316`) dan merah kobaran api (`#DC2626` / `#C2410C`) berpadu dengan kanvas kaca terang (*Light Frosted Liquid Glass*) yang tajam, kontras tinggi, dan berkelas enterprise.
- **Utamakan Scannability & Anti-Overlap:** Setiap baris data, kartu metrik, status pembayaran, dan nomor invoice memiliki zona spasial tersendiri tanpa tumpang-tindih (*zero text collision*).
- **Refined Micro-Interactions & Liquid Glass Depth:** Lapisan kartu transparan (`backdrop-blur-xl`, `border-white/80`, `shadow-[0_8px_30px_rgb(0,0,0,0.04)]`) dengan efek pendar halus di latar belakang (*ambient atmospheric mesh*).

---

## 2. Palet Warna (Persija Red-Orange Liquid Glass)

### 2.1 Aksen Utama Persija Red-Orange (Pitch & Fire Accents)
- **Primary Brand / Pitch Fire Action:** `bg-gradient-to-r from-orange-600 via-orange-600 to-red-600 hover:from-orange-700 hover:to-red-700 text-white shadow-sm shadow-orange-500/25` (Aksen dinamis merah-oranye berkarakter kuat).
- **Primary Solid:** `#EA580C` (`orange-600`), `#C2410C` (`orange-700`), `#9A3412` (`orange-800`).
- **Accent Red Glow:** `#DC2626` (`red-600`), `#B91C1C` (`red-700`).
- **Highlight Flame:** `#FB923C` (`orange-400`), `#FDBA74` (`orange-300`), `#FFEDD5` (`orange-100`).
- **Focus Rings:** `focus:ring-2 focus:ring-orange-500/20 focus:border-orange-600`.
- **Selection:** `selection:bg-orange-600 selection:text-white`.

### 2.2 Permukaan & Latar Belakang (Liquid Canvas & Glass)
- **Base Canvas:** `bg-slate-50` dengan ambient mesh gradient lembut di latar:
  `bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-orange-100/35 via-slate-50/90 to-slate-100/70`.
- **Liquid Frosted Card:** `bg-white/80 backdrop-blur-xl border border-white/90 shadow-[0_4px_20px_rgba(15,23,42,0.04)] rounded-2xl transition-all duration-150`.
- **Sticky Header & Modals:** `bg-white/85 backdrop-blur-md border-b border-slate-200/80 shadow-xs`.
- **Active Navigation Pill:** `bg-gradient-to-r from-orange-500/10 to-red-500/10 text-orange-800 font-bold border-l-4 border-orange-600 rounded-r-xl`.

### 2.3 Tipografi & Hierarki Data
- **Body & Heading:** `Plus Jakarta Sans` atau `Inter` (bersih, tajam pada ukuran 12–14px).
- **Tabular / Monospace:** `JetBrains Mono` (`font-mono tabular-nums`) pada nominal Rupiah, tanggal lahir, NIK, dan nomor invoice.

---

## 3. Komponen Standar & Aturan Anti-Overlap

### 3.1 Badge Status (Tanpa Dot & Tanpa Pulse)
Pill tegas dengan teks kontras tinggi tanpa bulatan ataupun animasi:
- **Lunas / Aktif / Lolos (`PAID` / `ACTIVE` / `PASSED` / `OPEN`):** `bg-emerald-50 text-emerald-800 border-emerald-300 font-semibold text-xs px-2.5 py-0.5 rounded-md`
- **Menunggu Verifikasi / Belum Bayar (`WAITING_VERIFICATION` / `UNPAID` / `PENDING_PAYMENT` / `SUBMITTED`):** `bg-amber-50 text-amber-900 border-amber-300 font-semibold text-xs px-2.5 py-0.5 rounded-md`
- **Ditolak / Kedaluwarsa / Kuota Penuh (`REJECTED` / `EXPIRED` / `QUOTA_FULL`):** `bg-rose-50 text-rose-800 border-rose-300 font-semibold text-xs px-2.5 py-0.5 rounded-md`
- **Draft / Terkunci:** `bg-slate-100 text-slate-700 border-slate-300 font-semibold text-xs px-2 py-0.5 rounded-md`

### 3.2 Menu Sidebar Bebas Tumpukan (Anti-Block Clutter)
- Jangan gunakan badge teks bulky `[TERKUNCI]` yang memecah baris judul menu.
- Gunakan **ikon gembok halus (Padlock Icon)** di sisi kanan item menu yang terkunci (`w-3.5 h-3.5 text-slate-400 shrink-0`).
- Sertakan ikon semantik pada setiap menu sidebar agar *scannable* dan tertata proporsional.

### 3.3 Kartu Dashboard Proporsional (Grid Metrics Anti-Overlap)
Setiap kartu pada ringkasan tim wajib memiliki 3 tingkatan visual vertikal yang independen:
1. **Header:** Label kategori (abu-abu, uppercase kecil) + Icon/Status Badge di pojok kanan atas.
2. **Body:** Angka/Nominal utama ukuran besar (`text-xl` s/d `text-2xl font-black tabular-data`).
3. **Footer:** Keterangan sekunder (No. Invoice, Asal Askot, atau batas kuota) dengan baris terpisah dan spasi proporsional.

---

## 4. Larangan Desain Visual Wajib & Standardisasi Komponen (Strict Prohibitions)
1. **DILARANG menggunakan Animasi `pulse`, `ping`, atau efek berkedip (*blinking*):**
   - Tidak boleh menggunakan `animate-pulse`, `animate-ping`, atau animasi kedap-kedip pada ikon, status badge, tombol, maupun kartu metrik. Antarmuka harus tenang, kokoh, dan berstandar utilitas operasional enterprise.
2. **DILARANG menggunakan Dot Indicator / Titik Bulat Status:**
   - Tidak boleh menaruh elemen dot bulat hiasan (`rounded-full w-2 h-2` dsb.) baik standalone maupun di dalam pill badge. Status selalu dinyatakan secara eksplisit dengan teks badge yang jelas.
3. **DILARANG menggunakan Validasi Bawaan Browser:**
   - Semua form wajib `<form novalidate>`. Pesan error disajikan melalui composable reaktif dan `FormErrorBanner`.
4. **DILARANG menggunakan Emoticon / Emoji Karakter Unicode (Strict Unicode Emoji Ban):**
   - Tidak boleh menggunakan karakter emoticon/emoji unicode mentah seperti jam pasir (`⏳`), centang (`✓`), tanda silang (`✕`), kertas dokumen (`📄`), atau peringatan (`⚠️`) di status badge, tombol, teks notifikasi, maupun label berkas.
   - **Wajib menggunakan Icon SVG Terstandar (Heroicons):** Seluruh status, ikon dokumen, indikator jam/waktu, dan aksi wajib menggunakan SVG vektor murni yang terdefinisi ukurannya (`w-4 h-4` / `w-5 h-5`) dengan stroke/fill yang selaras dengan palet tema Persija Red-Orange dan varian status semantic (Emerald, Amber, Rose, Slate).
5. **Standardisasi Modal Viewer Berkas (`DocumentViewerModal`):**
   - Komponen modal inspeksi berkas wajib terintegrasi in-app tanpa memaksa pengguna membuka tab baru untuk alur pemeriksaan rutin.
   - **Mendukung Multi-Format:**
     - **File Gambar (JPG, PNG, WEBP):** Dilengkapi kontrol manipulasi visual terpadu: Zoom In (`+`), Zoom Out (`-`), Reset Skala 1:1, dan Putar/Rotasi 90 Derajat (`Rotate 90°`).
     - **File Dokumen PDF:** Dilengkapi *Embedded Responsive Iframe Viewer* sehingga dokumen multi-halaman dapat dibaca langsung di dalam modal, disertai tombol alternatif *"Buka di Tab Baru / Unduh"* jika verifikator membutuhkan inspeksi eksternal.
6. **Aturan Best Practice Tombol Aksi & Disable State (Operational UX Rules):**
   - **Disable State Transparan dengan Tooltip Informatif:** Tombol aksi operasional (seperti *"Loloskan Pemain (Sah)"*, *"Tolak Berkas"*, *"Kunci Skuad"*) tidak boleh sekadar dinonaktifkan (`disabled`) tanpa penjelasan. Wajib memberikan indikator visual yang jelas (`opacity-50 cursor-not-allowed`) serta atribut `title` / tooltip yang menjelaskan secara rinci mengapa tombol belum dapat ditekan (contoh: *"Pemain telah terkunci"*, *"Usia pemain tidak memenuhi batas regulasi"*, *"Masih ada berkas yang ditandai butuh revisi"*).
   - **Pencegahan Double-Submit & Indikator Loading:** Seluruh tombol mutasi wajib mendeteksi status pemrosesan (`form.processing`) dan menampilkan animasi pemintal (*spinner*) halus yang menonaktifkan klik ganda selama request berjalan.
   - **Konfirmasi Modal untuk Aksi Kritis / Destruktif:** Setiap tindakan yang berpotensi mengubah status data secara permanen atau berdampak besar (misalnya menolak berkas dengan catatan revisi, membuka kunci darurat / *Unlock*, atau mengunci skuad final) wajib melalui dialog konfirmasi modal dengan rincian konsekuensi aksi.

### 4.7 Simplifikasi Aksi Daftar Event & Workspace Hub (`/admin/events/{event}`)
- **Card Action Budget (Maks. 4 Aksi di `/admin/events`)**: Kartu daftar event (`Admin/Events/Index.vue`) **DILARANG** memajang lebih dari 4 tombol aksi. Hanya tampilkan aksi tingkat-event: `Kelola Event` (primary CTA menuju `/admin/events/{event}`), `Edit`, `Sync Kuota`, dan `Lihat Publik`.
- **Event Workspace Hub Beradaptasi Konteks (`Admin/Events/Show.vue`)**: Seluruh operasi per event (screening peserta/tim, daftar hadir QR Scanner, panel scout, kelolosan & piagam, klasemen/jadwal turnamen) dipusatkan di halaman detail workspace menggunakan 3 tab terstruktur:
  1. `Ringkasan & Kuota` (progres kuota utama + breakdown posisi/grup)
  2. `Peserta & Screening` / `Tim & Screening` (tabel & shortcut langsung ke Split-View Screening)
  3. `Operasional & Lapangan` (adaptif: Turnamen Tim menampilkan Klasemen & Jadwal; Audisi menampilkan Sesi, Tahapan, QR Scanner, Panel Scout, & Kelolosan; Coaching Clinic menampilkan Sesi Pelatihan & QR Check-In Digital Pass tanpa modul eliminasi/scout).

### 4.8 Paritas UX Screening Perorangan (Split-View Workstation & Digital Pass)
- **Larangan Tautan Berkas Tab Eksternal**: Halaman screening peserta individual (`Admin/Screening/Auditions.vue` & `AuditionReview.vue`) **DILARANG** membuka dokumen sensitif menggunakan `target="_blank"`. Seluruh inspeksi Akta Lahir, Kartu Keluarga, Pas Foto, dan Dokumen Tambahan wajib dirender melalui `DocumentViewerModal` tersemat (*zoom*, *rotate*, status validasi, catatan).
- **Split-View Workstation (`/admin/screening/auditions/events/{event}`)**: Antarmuka pemeriksaan peserta individual wajib memiliki paritas UX dengan `TeamReview.vue`:
  - Panel kiri berisi daftar antrean peserta beserta pill progres dokumen (`X/Y Sah`) dan pencarian instan.
  - Panel kanan menampilkan header identitas, kotak **Validasi Cut-Off Tanggal Lahir** (`Sah Sesuai Usia` vs `Di Luar Batas Usia Event`), kartu berkas interaktif, dan tombol **Quick Verification Wizard**.
  - Bilah keputusan tetap di bagian bawah dengan konfirmasi modal `<form novalidate>`, auto-fill catatan penolakan berdasarkan berkas invalid, dan fitur **Pembukaan Kunci Darurat (*Emergency Unlock*)** khusus pemegang izin `screening.unlock_player`.
- **Standar Penamaan Digital Pass (E-Tiket)**: Gunakan istilah **Digital Pass** / **E-Tiket** (berbasis `registration_number`) untuk tiket masuk & QR Check-In seluruh event individual agar selaras antara Audisi dan Coaching Clinic; nomor rompi (`bib_number`) ditampilkan hanya sebagai pelengkap opsional pada event Audisi.
