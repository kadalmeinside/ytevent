# UI/UX Design Specification: High-Density Operational Utility + Clean Enterprise Frosted Glass

## 1. Filosofi Desain & Prinsip Anti-Slop
Aplikasi dirancang sebagai **perangkat kerja operasional sepak bola berdensitas tinggi** (seperti dashboard data FIFA TMS / PremierLeague Competition Portal versi terang) yang dipadukan dengan estetika **Clean Enterprise Frosted Glass**.
- **Utamakan Scannability:** Panitia turnamen, tim finance, dan manajer tim harus dapat memindai ratusan baris pemain, tanggal lahir, NIK, dan status pembayaran dalam hitungan detik.
- **Zero Visual Slop:** Tidak ada dekorasi tanpa fungsi, tidak ada gradien mencolok di area kerja utama, dan tidak ada animasi berlebih yang memperlambat alur verifikasi.

---

## 2. Larangan Visual Mutlak (Strict Visual Prohibitions)
1. **DILARang Keras:** Menggunakan latar belakang hitam pekat (`#000000` / `#09090b`) dengan teks neon menyala (hijau stabilo/cyan terang) untuk tabel utama atau halaman kerja.
2. **DILARANG Keras:** Menggunakan efek *pulse* (`animate-pulse`), *ping* (`animate-ping`), atau titik bulat berkedip (*dot indicator*) di dalam komponen **Badge Status**.
3. **DILARANG Keras:** Menggunakan atribut validasi HTML5 bawaan (`required`, `minlength`, `type="email"` browser tooltip) atau `window.alert()` / `window.confirm()` bawaan browser.
4. **DILARANG Keras:** Menyembunyikan informasi krusial (seperti alasan gagal screening atau batas usia) di balik tooltip kecil yang harus di-hover satu per satu.

---

## 3. Palet Warna (Light Enterprise Frosted Canvas)

### 3.1 Permukaan & Latar Belakang (Canvas & Glass)
- **Base App Canvas:** `bg-slate-50` (`#F8FAFC`) dengan aksen *mesh subtle* sangat tipis di area header (`from-slate-100/80 to-emerald-50/30`).
- **Primary Frosted Surface (Cards & Containers):** `bg-white/85 backdrop-blur-xl border border-slate-200/80 shadow-sm`.
- **Sticky Header & Modal Surface:** `bg-white/90 backdrop-blur-md border-b border-slate-200`.
- **Table Surface:**
  - Header Tabel: `bg-slate-100/90 text-slate-700 border-b border-slate-200`.
  - Baris Tabel: `bg-white hover:bg-slate-50/90 transition-colors duration-100 divide-y divide-slate-200/70`.

### 3.2 Tipografi & Aksen Warna Sepak Bola (Tactile Pitch Accents)
- **Primary Text:** `text-slate-900` (`#0F172A`)
- **Secondary / Label Text:** `text-slate-600` (`#475569`)
- **Muted / Helper Text:** `text-slate-400` (`#94A3B8`)
- **Primary Brand / Pitch Action:** `bg-emerald-700 hover:bg-emerald-800 text-white` (`#047857` — hijau rumput stadion profesional, bukan hijau neon).
- **Secondary Action:** `bg-white hover:bg-slate-50 text-slate-700 border border-slate-300`.

---

## 4. Tipografi & Hierarki Data
- **UI & Body Font:** `Inter` atau `Plus Jakarta Sans` — tajam pada ukuran kecil (`12px`–`14px`).
- **Monospace / Tabular Data:** `JetBrains Mono` atau `font-mono tabular-nums` wajib diterapkan pada:
  - Nomor Punggung Pemain
  - Tanggal Lahir & Perhitungan Usia
  - NIK / Nomor Identitas
  - Nominal Rupiah & Nomor Invoice (`INV-...`)
- **Ukuran Tabel High-Density:**
  - Tinggi baris (*row height*): Kompak (`py-2.5 px-3.5`, tinggi ~40px–44px per baris).
  - Ukuran teks sel tabel: `text-xs` (`12px`) untuk metadata/kode, `text-sm` (`14px`) untuk nama tim/pemain.

---

## 5. Komponen Standar

### 5.1 Badge Status (Tanpa Dot & Tanpa Pulse)
Gunakan bentuk *pill* tegas atau *rounded-md* (`px-2.5 py-0.5 text-xs font-semibold tracking-wide uppercase border`) dengan kontras teks tinggi:
- **Lolos / Lunas (`PASSED` / `PAID`):** `bg-emerald-50 text-emerald-800 border-emerald-300`
- **Menunggu Verifikasi (`SUBMITTED` / `PENDING`):** `bg-amber-50 text-amber-800 border-amber-300`
- **Gagal / Ditolak (`REJECTED` / `FAILED`):** `bg-rose-50 text-rose-800 border-rose-300`
- **Draft / Belum Lengkap (`DRAFT`):** `bg-slate-100 text-slate-700 border-slate-300`

### 5.2 Sistem Validasi Form & Error State (Custom Vue UI)
- Seluruh tag `<form>` wajib menyertakan atribut `novalidate`.
- **Kondisi Normal:** `bg-white border border-slate-300 rounded-lg px-3.5 py-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-600/20 focus:border-emerald-600`.
- **Kondisi Error (`form.errors.field`):**
  - Border input berubah menjadi `border-rose-500 bg-rose-50/30 focus:ring-rose-500/20 focus:border-rose-600`.
  - Di bawah field muncul komponen `<InputError :message="form.errors.field" />` dengan ikon SVG tanda seru statis (`w-4 h-4 text-rose-600`) dan teks `text-xs font-medium text-rose-600 mt-1`.
- **Global Form Error Banner:** Jika terdapat error validasi saat submit, tampilkan banner *Frosted Rose* di bagian atas form (`bg-rose-50/90 backdrop-blur-md border border-rose-200 text-rose-800 px-4 py-3 rounded-xl text-sm`) yang merangkum jumlah field yang perlu diperbaiki.

### 5.3 Responsivitas Desktop & Mobile
- **Desktop (`>= 1024px`):**
  - Sidebar navigasi kiri *fixed* (`w-64 bg-white/85 backdrop-blur-xl border-r border-slate-200`).
  - Tabel data menampilkan seluruh kolom penting secara horizontal dengan *sticky action column* di sebelah kanan.
  - Panel *Screening Pemain* menggunakan layout *Split-View* (Kiri: Daftar & Data Pemain, Kanan: Preview Dokumen Akta/KK/Foto dengan fitur Zoom/Rotate).
- **Mobile (`< 768px`):**
  - Navigasi menggunakan *Top Frosted Navbar* + *Slide-over Drawer* (untuk Admin) atau *Bottom Action Bar* (untuk Manajer Tim).
  - Tabel berdensitas tinggi memiliki *horizontal scroll* halus dengan kolom pertama (Nama Tim/Pemain) *sticky*, atau berubah menjadi *Compact Data Card* terstruktur yang tetap memuat informasi utama tanpa harus membuka halaman baru.