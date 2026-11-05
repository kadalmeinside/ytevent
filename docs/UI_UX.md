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

## 4. Larangan Desain Visual Wajib (Strict Prohibitions)
1. **DILARANG menggunakan Animasi `pulse`, `ping`, atau efek berkedip (*blinking*):**
   - Tidak boleh menggunakan `animate-pulse`, `animate-ping`, atau animasi kedap-kedip pada ikon, status badge, tombol, maupun kartu metrik. Antarmuka harus tenang, kokoh, dan berstandar utilitas operasional enterprise.
2. **DILARANG menggunakan Dot Indicator / Titik Bulat Status:**
   - Tidak boleh menaruh elemen dot bulat hiasan (`rounded-full w-2 h-2` dsb.) baik standalone maupun di dalam pill badge. Status selalu dinyatakan secara eksplisit dengan teks badge yang jelas.
3. **DILARANG menggunakan Validasi Bawaan Browser:**
   - Semua form wajib `<form novalidate>`. Pesan error disajikan melalui composable reaktif dan `FormErrorBanner`.
