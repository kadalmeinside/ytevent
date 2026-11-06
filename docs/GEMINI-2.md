# GEMINI.md - Project Engineering Rules & Architecture Constraints

Dokumen ini adalah instruksi sistem wajib untuk AI Coding Agent saat menulis, memodifikasi, atau me-review kode pada proyek **Multi-Event Football Competition System**.

## 1. Tech Stack & Konvensi Arsitektur
- **Framework:** Laravel 11 + Inertia.js v1/v2 + Vue 3 (Composition API `<script setup>`) + Tailwind CSS.
- **Database:** MySQL 8.0+. Gunakan strict mode, `foreignId()->constrained()`, dan index pada kolom pencarian (`slug`, `status`, `event_id`, `team_id`).
- **Routing:** Gunakan `tightenco/ziggy` (`route('name')`) untuk seluruh navigasi Inertia (`<Link>` maupun `router.visit()`).
- **State & Form Handling:** Wajib menggunakan `useForm()` dari `@inertiajs/vue3` untuk semua operasi `POST`, `PUT`, `PATCH`, dan `DELETE`.

## 2. Aturan Validasi Berlapis (Frontend & Backend)
1. **Larangan Validasi Bawaan Browser:**
   - Setiap elemen `<form>` di Vue **WAJIB** memiliki atribut `novalidate` (`<form @submit.prevent="submit" novalidate>`).
   - **DILARANG** menggunakan atribut HTML5 `required`, `min`, `max`, `pattern`, atau `window.alert()`.
2. **Validasi Frontend (Client-Side Pre-flight & Reactive Feedback):**
   - Buat composable `useFormValidator.js` yang memeriksa aturan dasar (field kosong, format email regex, ukuran/ekstensi file logo `png/jpg <= 2MB`, format nomor telepon, dan checkbox persetujuan) sebelum mengeksekusi `form.post()`, atau langsung memetakan pesan error ke objek `clientErrors` / `form.setError()`.
   - Hapus pesan error pada field secara otomatis saat pengguna mulai mengetik ulang (`@input="form.clearErrors('field_name')"`).
3. **Validasi Backend (Laravel FormRequest):**
   - **DILARANG** menulis logika validasi langsung di dalam metode Controller (`$request->validate()`).
   - **WAJIB** membuat class `FormRequest` terpisah untuk setiap endpoint mutasi data (contoh: `RegisterTeamRequest`, `StorePlayerRequest`, `VerifyScreeningRequest`, `UploadPaymentProofRequest`).
   - Seluruh pesan error di `FormRequest::messages()` harus menggunakan Bahasa Indonesia yang jelas, operasional, dan spesifik.

## 4. Aturan Desain UI/UX & Larangan Visual Wajib
1. **Larangan Animasi Pulse / Blinking:**
   - **DILARANG KERAS** menggunakan kelas Tailwind `animate-pulse`, `animate-ping`, `animate-bounce`, atau CSS blinking pada kartu operasional, banner, ikon, tombol, maupun badge status. Antarmuka sepak bola profesional harus stabil, tenang, dan tidak mendistraksi konsentrasi operasional panitia dan manajer.
2. **Larangan Dot Indicator / Lingkaran Status:**
   - **DILARANG KERAS** menggunakan elemen titik bulat (`dot indicator` seperti `<span class="w-2 h-2 rounded-full ..."></span>`) baik yang berdiri sendiri maupun berdampingan dengan badge teks. Status wajib disajikan sebagai teks badge pill solid/terstruktur yang kontras tinggi dan tegas tanpa bulatan hiasan.
3. **Larangan Validasi Bawaan Browser:**
   - Formulir selalu menggunakan `<form novalidate>` dan validasi reaktif via composable / form error banner. Dilarang menggunakan HTML5 browser tooltips.
4. **Larangan Emoticon / Emoji Unicode:**
   - **DILARANG KERAS** menggunakan karakter emoji/emoticon Unicode mentah di seluruh template Vue, badge, banner notifikasi, maupun pesan flash. Gunakan selalu ikon SVG terstandar (Heroicons).
5. **Standar Badge Counter & Banner Panduan Alur (Fase 3):**
   - Semua badge counter di sidebar Admin (`adminNotifications`) dan Manajer (`team.workflow.badges`) wajib berupa pill teks/angka terstruktur yang kontras tinggi tanpa titik bulat (*no circular dots*) dan tanpa animasi kedip (*no pulse/ping*).
   - Portal Manajer wajib selalu menyertakan objek `team.workflow` melalui trait `ResolvesActiveTeam` agar banner panduan 5 tahap (`WorkflowStepBanner`) dan status kesiapan akreditasi selalu sinkron di setiap halaman.
