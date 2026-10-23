# IMPLEMENTATION_PLAN.md — Almira Tiket Event

**Version:** 1.0  
**Status:** Initial implementation plan  
**Stack:** Laravel + MySQL + Blade  
**Source of truth:** `PRD.md`, `AGENTS.md`, `styling.md`

---

## 1. Purpose

Dokumen ini membagi implementasi Almira Tiket Event menjadi fase yang dapat dikerjakan dan diverifikasi secara bertahap.

AI coding agent wajib:
- Mengerjakan satu task aktif pada satu waktu.
- Memeriksa repository dan implementasi yang sudah ada sebelum coding.
- Mengikuti `AGENTS.md` dan requirement pada `PRD.md`.
- Menggunakan `styling.md` sebagai acuan visual.
- Tidak melanjutkan fase berikutnya sebelum acceptance criteria fase aktif terpenuhi.
- Tidak mengarang keputusan bisnis yang belum dikonfirmasi.

## 2. Project Rules

1. Gunakan Laravel, MySQL, dan Blade sesuai konfigurasi proyek.
2. Pertahankan arsitektur yang sudah ada jika benar dan konsisten.
3. Gunakan migration untuk perubahan schema database.
4. Jangan menghapus data atau menjalankan operasi destruktif tanpa persetujuan.
5. Jangan menambahkan payment gateway atau integrasi eksternal tanpa persetujuan.
6. Jangan menerbitkan tiket sebelum pembayaran disetujui admin.
7. Terapkan validasi server-side dan otorisasi pada setiap operasi sensitif.
8. Setiap fase harus menyertakan pengujian yang relevan.
9. Jika requirement bertentangan atau belum lengkap, hentikan bagian yang terdampak dan laporkan kebutuhan klarifikasi.
10. Setelah fase selesai, laporkan file yang berubah, hasil pengujian, dan pekerjaan tersisa.

## 3. Phase 01 — Project Foundation

### Objective
Mempersiapkan aplikasi Laravel yang dapat dijalankan dan terhubung ke MySQL.

### Scope
- Periksa struktur repository dan versi dependency.
- Konfigurasi environment lokal tanpa mengekspos secret.
- Pastikan koneksi database bekerja.
- Siapkan autentikasi admin.
- Siapkan struktur dasar routes, controllers, views, dan layout.
- Pastikan migration dapat dijalankan pada database development.

### Acceptance Criteria
- Aplikasi berjalan pada environment development.
- Koneksi MySQL berhasil.
- Admin dapat login dan logout.
- Route admin terlindungi autentikasi.
- Migration berjalan tanpa error.
- Konfigurasi sensitif tidak masuk ke source control.

### Dependencies
Tidak ada.

---

## 4. Phase 02 — Design System & Layout

### Objective
Menerapkan pola visual yang konsisten sebelum halaman utama dikembangkan.

### Scope
- Baca `styling.md`.
- Implementasikan design tokens dan layout dasar yang sesuai dengan stack.
- Buat komponen bersama untuk tombol, input, label, tabel, badge status, alert, dan dialog bila diperlukan.
- Siapkan layout admin dan layout halaman publik.
- Implementasikan responsive behavior.

### Acceptance Criteria
- Komponen utama konsisten dengan `styling.md`.
- Layout dapat digunakan kembali.
- Form memiliki validation/error states yang jelas.
- Tampilan responsif pada desktop dan mobile.
- Tidak ada design system baru yang dibuat tanpa dasar dari sumber desain.

### Dependencies
Phase 01.

---

## 5. Phase 03 — Event Management

### Objective
Memungkinkan admin mengelola banyak event.

### Scope
- Event model dan migration.
- CRUD event.
- Pengaturan harga dan kuota per event.
- Upload, penggantian, dan pengelolaan poster.
- Status draft/published/closed sesuai kebutuhan PRD.
- Validasi dan otorisasi.

### Acceptance Criteria
- Admin dapat membuat, melihat, mengubah, dan mengelola event.
- Harga dan kuota disimpan per event.
- Poster terkait dengan event yang benar.
- Event yang belum dipublikasikan tidak tersedia untuk pemesanan publik.
- Perubahan event tidak mengubah snapshot transaksi yang sudah dibuat.
- Validasi input dan upload berfungsi.

### Dependencies
Phase 01–02.

---

## 6. Phase 04 — Public Event & Ordering

### Objective
Memungkinkan peserta memesan satu tiket untuk event yang tersedia.

### Scope
- Halaman daftar dan detail event.
- Poster berada di bagian paling atas halaman pemesanan.
- Form peserta: nama, nomor WhatsApp, dan email.
- Pembuatan referensi pesanan unik.
- Snapshot harga saat pesanan dibuat.
- Reservasi satu slot kuota.
- Penyimpanan waktu pembuatan dan tenggat pembayaran 60 menit.
- Penanganan kuota habis.

### Acceptance Criteria
- Peserta hanya dapat memesan event yang dipublikasikan dan tersedia.
- Satu pesanan mewakili satu tiket.
- Data peserta divalidasi di server.
- Snapshot harga tersimpan.
- Satu slot kuota ditahan untuk pesanan yang berhasil dibuat.
- Kuota tidak dapat melebihi kapasitas akibat request bersamaan.
- Peserta tidak dapat melihat data pesanan milik orang lain.

### Dependencies
Phase 03.

---

## 7. Phase 05 — Manual Payment

### Objective
Memungkinkan peserta membayar secara manual dan mengirim bukti pembayaran.

### Scope
- Tampilan metode dan instruksi pembayaran.
- Konfigurasi metode pembayaran yang telah dikonfirmasi bisnis.
- Upload bukti pembayaran wajib.
- Penyimpanan bukti secara privat.
- Pencatatan waktu bukti diterima.
- Status menunggu pembayaran dan menunggu verifikasi.

### Acceptance Criteria
- Instruksi yang ditampilkan berasal dari konfigurasi yang benar.
- Bukti wajib tersedia sebelum pesanan masuk antrean verifikasi.
- File yang tidak valid ditolak.
- Bukti hanya dapat dilihat pihak berwenang.
- Unggahan bukti tidak otomatis menandai pembayaran sebagai lunas.
- Bukti yang diterima sebelum tenggat mengubah status ke `PENDING_VERIFICATION`.

### Dependencies
Phase 04.

### Open Decisions
- Daftar final metode pembayaran.
- Detail rekening, VA, atau QR merchant.
- Batas ukuran dan tipe file bukti.
- Kebijakan unggah ulang setelah penolakan.

Jangan mengarang detail pembayaran yang belum diberikan.

---

## 8. Phase 06 — Deadline & Quota Integrity

### Objective
Memastikan tenggat 60 menit dan reservasi kuota berjalan konsisten.

### Business Rules
- Tenggat adalah 60 menit sejak pesanan dibuat.
- Saat pesanan dibuat, satu slot kuota ditahan.
- Jika bukti belum diterima sebelum tenggat, pesanan menjadi `EXPIRED` dan kuota dilepas.
- Jika bukti diterima sebelum tenggat, pesanan menjadi `PENDING_VERIFICATION`.
- Pesanan `PENDING_VERIFICATION` tetap menahan kuota sampai admin menyetujui atau menolak.
- Proses kedaluwarsa tidak boleh melepas kuota dua kali.
- Pesanan yang telah disetujui tidak boleh kedaluwarsa.

### Scope
- Penyimpanan `expires_at`.
- Proses kedaluwarsa menggunakan mekanisme terjadwal yang sesuai.
- Transaksi database dan locking untuk perubahan kuota.
- Penanganan request bersamaan saat tenggat.
- Pengujian kasus batas waktu.

### Acceptance Criteria
- Pesanan tanpa bukti setelah tenggat menjadi `EXPIRED`.
- Kuota dilepas tepat satu kali.
- Bukti yang diterima sebelum tenggat mempertahankan reservasi.
- Pesanan menunggu verifikasi tidak kedaluwarsa hanya karena melewati menit ke-60.
- Dua request bersamaan tidak dapat mengambil slot yang sama.
- Proses dapat dijalankan ulang tanpa merusak data.

### Dependencies
Phase 04–05.

---

## Testing Policy — Phase 07, 08, and 09

### Objective
Prioritize implementation progress and manual QA over automated testing for Phases 07, 08, and 09.

### Rules
1. Do NOT create unit tests, feature tests, integration tests, browser tests, or end-to-end tests for Phase 07, Phase 08, or Phase 09.
2. Do NOT run automated test suites such as `php artisan test`, PHPUnit, Pest, or other automated testing commands during these phases.
3. Do NOT spend time creating, updating, debugging, or fixing automated tests for these phases.
4. Do NOT block phase completion because automated tests are missing or have not been executed.
5. Continue to follow Laravel coding conventions, validate user input, handle errors, enforce authorization, and maintain database integrity.
6. Perform basic non-test verification where practical, such as checking routes, reviewing migrations, checking logs, and confirming that the application can start without errors. Do not turn these checks into an automated testing effort.
7. Document the features implemented, relevant routes, expected behavior, known limitations, and manual QA steps for the user.
8. If an existing automated test fails or a test-related issue is discovered, report it briefly without spending time fixing or expanding the test suite, unless the issue reveals an actual application defect that must be corrected.

### Manual QA Handoff
At the end of each phase, provide:
- Implementation summary.
- Files created or modified.
- Database migrations or configuration changes, if applicable.
- Manual QA checklist with step-by-step instructions.
- Expected results for each checklist item.
- Known issues or unresolved dependencies.

The user will perform manual QA before approving the phase.

### Completion Criteria
A phase may be marked `READY_FOR_REVIEW` when the implementation is complete, basic non-test verification has been performed where practical, and the manual QA checklist is provided.

Mark the phase `DONE` only after the user has completed manual QA and approved it, or follow the project's explicitly defined approval workflow.

---

## 9. Phase 07 — Admin Verification & E-ticket

### Objective
Memungkinkan admin memverifikasi pembayaran dan menerbitkan tiket digital.

### Scope
- Antrean verifikasi admin.
- Detail pesanan dan bukti.
- Aksi approve/reject.
- Pencatatan keputusan, waktu, dan admin.
- Pencatatan alasan penolakan.
- Pembuatan token QR unik.
- Halaman tiket dan unduhan.
- Pengiriman email jika konfigurasi email telah tersedia.

### Acceptance Criteria
- Hanya admin berwenang dapat memverifikasi pembayaran.
- Persetujuan menerbitkan paling banyak satu tiket per pesanan.
- Penolakan tidak menerbitkan tiket.
- Token QR unik dan tidak mudah ditebak.
- Proses approve yang dijalankan ulang tidak membuat tiket duplikat.
- Tiket dapat diakses secara aman.
- Kegagalan email tidak membatalkan tiket yang sah.

### Dependencies
Phase 05–06.

### Open Decisions
- Apakah peserta boleh mengunggah ulang bukti setelah ditolak?
- Bagaimana kuota diperlakukan setelah penolakan?
- Layanan email yang digunakan.
- Format akhir tiket yang dapat diunduh.

---

## 10. Phase 08 — QR Check-in

### Objective
Memvalidasi tiket saat peserta datang ke event.

### Scope
- Halaman scanner QR berbasis web.
- Permintaan izin kamera.
- Validasi token di server.
- Pencatatan waktu dan petugas check-in.
- Pencegahan check-in ganda.
- Tampilan hasil valid, tidak valid, atau sudah digunakan.

### Acceptance Criteria
- Tiket valid yang belum digunakan dapat check-in.
- Token tidak valid ditolak.
- Tiket yang sudah check-in tidak dapat dipakai lagi dalam kondisi normal.
- Dua request check-in bersamaan tidak menghasilkan dua check-in sukses.
- Check-in dicatat dengan waktu dan petugas.
- Kegagalan izin kamera ditangani dengan pesan yang jelas.

### Dependencies
Phase 07.

### Open Decisions
- Apakah diperlukan pencarian tiket manual sebagai alternatif scanner?
- Apakah admin boleh membatalkan atau mereset check-in?

---

## 11. Phase 09 — Dashboard, QA & Release

### Objective
Memastikan sistem dapat digunakan secara operasional dan alur kritis telah diuji.

### Scope
- Dashboard event, pesanan, pembayaran, tiket, kuota, dan check-in.
- Pencarian/filter daftar pesanan.
- Pengujian integrasi end-to-end.
- Pemeriksaan keamanan dan otorisasi.
- Pemeriksaan backup dan pemulihan.
- Verifikasi konfigurasi production dan logging.

### Acceptance Criteria
- Angka dashboard sesuai dengan data dan definisi status.
- Alur dari pemesanan hingga check-in berhasil diuji.
- Tidak ada tiket terbit sebelum pembayaran disetujui.
- Kuota konsisten pada kasus normal dan request bersamaan.
- Data sensitif tidak terbuka untuk pengguna yang tidak berwenang.
- Prosedur backup tersedia.
- Konfigurasi production tidak menggunakan credential development.
- Semua masalah yang belum selesai didokumentasikan.

### Dependencies
Phase 03–08.

---

## 12. Testing Strategy

Untuk setiap fase:
1. Jalankan automated tests yang relevan.
2. Tambahkan regression tests untuk perubahan yang menyentuh alur yang sudah ada.
3. Uji validasi dan otorisasi.
4. Uji error states dan kasus input tidak valid.
5. Untuk kuota, pembayaran, penerbitan tiket, dan check-in, uji integritas data serta request bersamaan.
6. Jangan menandai fase selesai jika pengujian yang diwajibkan gagal.

---

## 13. Task Execution Protocol

Ketika diminta mengerjakan task:

1. Identifikasi fase dan scope yang diminta.
2. Baca `AGENTS.md` serta bagian relevan `PRD.md` dan `styling.md`.
3. Periksa kode dan test yang sudah ada.
4. Rencanakan file yang akan diubah.
5. Implementasikan hanya scope task tersebut.
6. Jalankan pengujian yang relevan.
7. Perbaiki regresi yang disebabkan perubahan.
8. Laporkan hasil dan sisa pekerjaan.

Jangan otomatis mengerjakan fase selanjutnya.

---

## 14. Progress Tracking

Tandai fase sebagai:
- `NOT_STARTED`
- `IN_PROGRESS`
- `BLOCKED`
- `DONE`

Jangan menandai `DONE` tanpa memenuhi acceptance criteria dan menjalankan pengujian yang relevan.

Catat keputusan bisnis yang belum terjawab sebagai open decisions, bukan asumsi final.

---

## 15. Final Deliverable

Aplikasi dianggap siap untuk evaluasi operasional setelah:
- Semua fase yang termasuk MVP selesai.
- Acceptance criteria terpenuhi.
- Pengujian alur kritis lulus.
- Konfigurasi pembayaran, email, dan environment telah diverifikasi.
- Dokumentasi setup dan operasional tersedia.
- Risiko dan keputusan terbuka telah ditinjau oleh pemilik bisnis.

**End of Implementation Plan**
