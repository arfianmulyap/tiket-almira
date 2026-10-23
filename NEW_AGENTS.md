# AGENTS.md — Almira Tiket Event

## 1. Project Context

Almira Tiket Event adalah aplikasi web untuk mengelola event, pemesanan tiket, pembayaran manual, verifikasi pembayaran, e-ticket QR, dan check-in peserta.

### Tech Stack
- Backend: Laravel
- Database: MySQL
- Frontend: Laravel Blade
- JavaScript: gunakan sesuai kebutuhan proyek
- CSS/UI: ikuti `styling.md` jika tersedia

Jangan mengganti stack atau memperkenalkan framework utama baru tanpa persetujuan.

## 2. Source of Truth

Gunakan dokumen berikut sesuai prioritas dan tanggung jawabnya:

1. `PRD.md` — kebutuhan produk, aturan bisnis, scope, dan acceptance criteria.
2. `styling.md` — design system, visual direction, komponen UI, dan responsive rules.
3. `IMPLEMENTATION_PLAN.md` — urutan implementasi dan dependensi task.
4. `NEW_AGENTS.md` — aturan kerja dan konvensi coding.

Jika dokumen saling bertentangan, jangan menebak keputusan bisnis. Identifikasi konflik dan minta klarifikasi sebelum mengimplementasikan bagian yang terdampak.

Jika requirement belum ditetapkan, jangan mengarang detail seolah-olah sudah disetujui. Usulkan pilihan dan tandai sebagai keputusan yang membutuhkan konfirmasi.

## 3. Workflow Sebelum Coding

Sebelum mengerjakan task:

1. Periksa struktur repository dan teknologi yang sudah digunakan.
2. Baca instruksi yang relevan dan bagian PRD yang terkait.
3. Periksa model, migration, routes, controllers, views, dan komponen yang sudah ada.
4. Identifikasi dependensi serta file yang kemungkinan perlu diubah.
5. Tentukan acceptance criteria yang akan digunakan untuk memverifikasi hasil.
6. Jika ada keputusan bisnis yang menghalangi implementasi, tanyakan terlebih dahulu.

Jangan mengasumsikan repository masih kosong. Pertahankan implementasi yang sudah benar dan dapat digunakan.

## 4. Coding Conventions

- Ikuti konvensi Laravel dan PSR-12 yang relevan.
- Gunakan nama class, method, variable, route, dan database column yang konsisten serta deskriptif.
- Gunakan singular untuk nama model dan plural untuk nama tabel, mengikuti konvensi Laravel.
- Gunakan Form Request untuk validasi yang kompleks atau digunakan ulang.
- Gunakan middleware dan policy/gate untuk otorisasi yang sesuai.
- Pisahkan business logic yang kompleks dari controller jika meningkatkan keterbacaan dan maintainability.
- Gunakan Blade components untuk UI yang berulang jika sesuai.
- Hindari duplikasi logic.
- Jangan menambahkan dependency baru jika fitur dapat dibuat dengan teknologi yang sudah tersedia.
- Jangan membuat abstraksi berlebihan untuk kebutuhan yang sederhana.

Ikuti pola yang sudah digunakan repository jika pola tersebut konsisten dan aman. Jangan melakukan refactor besar yang tidak berkaitan dengan task.

## 5. Database and Data Integrity

- Gunakan Laravel migrations untuk perubahan schema.
- Jangan mengubah schema database secara manual sebagai pengganti migration yang seharusnya masuk ke repository.
- Tentukan foreign key, index, unique constraint, dan tipe data sesuai kebutuhan.
- Hindari menghapus atau mengubah data secara destruktif tanpa persetujuan.
- Gunakan database transactions untuk operasi yang melibatkan beberapa perubahan data yang harus konsisten.
- Pastikan operasi penting aman jika request atau job dijalankan lebih dari sekali.
- Jangan menganggap validasi frontend cukup; validasi kritis harus dilakukan di server.
- Jangan menjalankan reset, refresh, atau penghapusan database produksi.

## 6. Business Rules

Semua aturan bisnis harus mengikuti `PRD.md`.

Untuk Almira Tiket Event, aturan yang sudah ditetapkan meliputi:

- Satu pesanan berisi satu tiket.
- Data peserta: nama, nomor WhatsApp, dan email.
- Harga dan kuota ditetapkan per event.
- Pembayaran dilakukan secara manual dan bukti pembayaran wajib diunggah.
- Pembayaran diverifikasi oleh admin.
- Batas pembayaran adalah 60 menit sejak pesanan dibuat.
- Saat pesanan dibuat, satu slot kuota ditahan.
- Jika bukti belum diterima sebelum tenggat, pesanan kedaluwarsa dan kuota dilepas.
- Jika bukti diterima sebelum tenggat, kuota tetap ditahan sampai admin menyetujui atau menolak pembayaran.
- Tiket hanya diterbitkan setelah pembayaran disetujui.
- Satu pesanan tidak boleh menghasilkan tiket ganda.
- Setiap tiket memiliki token QR unik.
- Check-in normal hanya dapat dilakukan satu kali untuk setiap tiket.

Jangan mengubah aturan ini tanpa pembaruan PRD atau persetujuan eksplisit.

## 7. Security and Privacy

- Jangan menyimpan secret, password, API key, atau credential di source code.
- Gunakan environment variables untuk konfigurasi sensitif.
- Jangan menampilkan isi `.env` atau credential dalam laporan.
- Lindungi halaman admin dengan autentikasi dan otorisasi.
- Terapkan CSRF protection, server-side validation, dan output escaping.
- Simpan bukti pembayaran di lokasi privat atau gunakan akses terotorisasi.
- Validasi tipe dan ukuran file yang diunggah.
- Jangan mempercayai harga, status pembayaran, token QR, atau status check-in yang dikirim langsung oleh browser.
- Verifikasi perubahan status penting di server.
- Jangan mengekspos data pribadi peserta kepada pengguna lain.
- Jangan mengirim data sensitif ke layanan eksternal tanpa kebutuhan dan persetujuan yang sesuai.

## 8. UI/UX Implementation

- Gunakan `styling.md` sebagai acuan visual utama jika tersedia.
- Periksa komponen dan pola UI yang sudah ada sebelum membuat komponen baru.
- Pertahankan konsistensi warna, tipografi, spacing, border, radius, dan state komponen.
- Pastikan halaman responsif pada desktop dan perangkat mobile.
- Sertakan loading, empty, success, validation error, dan failure states jika relevan.
- Gunakan label form yang jelas serta pesan error yang dapat dipahami.
- Jangan mengganti design system atau membuat visual style baru tanpa alasan dan persetujuan.
- Jangan menambahkan animasi atau dependency visual yang tidak diperlukan.

Jika `styling.md` belum tersedia atau tidak menjelaskan detail yang dibutuhkan, gunakan pola UI yang sudah ada dan laporkan bagian yang memerlukan keputusan desain.

## 9. Testing and Verification

Setelah mengubah kode:

1. Jalankan pengujian yang relevan dengan task.
2. Tambahkan atau perbarui automated tests untuk business logic yang kritis.
3. Periksa hasil migration dan relasi yang terkait.
4. Periksa validasi, otorisasi, error handling, serta dampak terhadap fitur yang sudah ada.
5. Jalankan formatter atau linter yang sudah dikonfigurasi jika tersedia.
6. Jangan menyatakan pengujian berhasil jika belum benar-benar dijalankan.

Untuk perubahan pembayaran, kuota, penerbitan tiket, dan check-in, prioritaskan pengujian integritas data dan kondisi bersamaan.

Jika pengujian gagal karena perubahan yang dibuat, perbaiki sebelum menyatakan task selesai. Jika tidak dapat menjalankan pengujian, jelaskan alasannya.

## 10. Task Scope and Change Management

- Kerjakan hanya task yang sedang diminta.
- Jangan lanjut ke task berikutnya tanpa instruksi.
- Jangan mengubah file yang tidak berkaitan tanpa alasan teknis yang jelas.
- Jangan menghapus fitur yang sudah ada untuk mempermudah implementasi.
- Jangan mengganti package, framework, arsitektur, atau struktur besar tanpa persetujuan.
- Jangan mengarang konfigurasi pembayaran, detail rekening, VA, QR merchant, layanan email, atau kebijakan bisnis yang belum diberikan.
- Jika menemukan bug di luar scope, laporkan secara terpisah kecuali bug tersebut menghalangi task saat ini.

## 11. Completion Report

Setelah task selesai, berikan laporan ringkas yang mencakup:

1. Fitur yang berhasil diimplementasikan.
2. File yang dibuat atau diubah.
3. Migration atau perubahan database yang dilakukan.
4. Pengujian yang dijalankan dan hasilnya.
5. Asumsi atau keputusan yang masih menunggu konfirmasi.
6. Masalah yang belum terselesaikan.
7. Task berikutnya yang direkomendasikan berdasarkan `IMPLEMENTATION_PLAN.md`.

Jangan menyatakan fitur siap produksi jika masih ada konfigurasi, pengujian, atau keputusan penting yang belum diselesaikan.