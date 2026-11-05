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

## 3. Keamanan, Autentikasi, & RBAC (Spatie)
- Gunakan `spatie/laravel-permission` untuk manajemen Role & Permission.
- Gunakan middleware dan Laravel `Policy` (`TeamPolicy`, `PlayerPolicy`, `EventPolicy`, `PaymentPolicy`) di setiap Controller action (`$this->authorize('update', $team)`).
- Pastikan **Data Isolation** untuk `team_manager`: Manajer tim hanya boleh melihat dan memodifikasi `Team`, `Player`, dan `Invoice` yang memiliki `user_id === auth()->id()`.
- Bagikan state otorisasi ke Vue melalui middleware `HandleInertiaRequests.php`:
  ```php
  'auth' => [
      'user' => $request->user() ? [
          'id' => $request->user()->id,
          'name' => $request->user()->name,
          'email' => $request->user()->email,
          'roles' => $request->user()->getRoleNames(),
          'permissions' => $request->user()->getAllPermissions()->pluck('name'),
      ] : null,
  ],