# IMPLEMENTATION_PLAN.md — Almira Tiket Event

**Version:** 1.1  
**Status:** Dokumen hidup — status per fase diperbarui 27 Oktober 2026  
**Stack:** Laravel + MySQL + Blade  
**Source of truth:** `PRD.md`, `AGENTS.md`, `styling.md`  
**Runbook:** `docs/OPERATIONS.md` — deploy, QA manual per fase, keterbatasan yang diketahui

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

### 1.1 Status fase (per 27 Oktober 2026)

| Fase | Topik | Status | Verifikasi / QA |
| --- | --- | --- | --- |
| 01 | Fondasi & login admin | Terimplementasi | `tests/Feature/Auth` |
| 02 | Design system & layout | Terimplementasi | `DesignSystemTest`, `FormComponentTest` |
| 03 | Event & poster | Terimplementasi | `EventManagementTest`, `EventPosterTest`, `EventStatusTest` |
| 04 | Pemesanan & reservasi kuota | Terimplementasi | `OrderingTest`, `ConcurrentOrderingTest`, `QuotaIntegrityTest` |
| 05 | Pembayaran manual & bukti | Terimplementasi | `PaymentProofTest`, `PaymentProofValidationTest` |
| 06 | Tenggat & integritas kuota | Terimplementasi | `OrderExpiryTest`, `ExpireOverdueOrdersCommandTest` |
| 07 | Verifikasi & e-ticket | Terimplementasi | `PaymentVerificationTest`, `TicketIssuanceTest` |
| 08 | QR check-in | Terimplementasi | belum ada automated test; QA manual |
| 09 | Dashboard, runbook, QA rilis | Terimplementasi | `docs/OPERATIONS.md` §1–§6 |
| 10 | Role & permission | `READY_FOR_REVIEW` | `docs/OPERATIONS.md` §7 |
| 11 | Akun admin & undangan | `READY_FOR_REVIEW` | `docs/OPERATIONS.md` §8 |
| 12 | Metode pembayaran & audit log | `READY_FOR_REVIEW` | `docs/OPERATIONS.md` §9 |
| 12B | Metode pembayaran CRUD & per event | `READY_FOR_REVIEW` | `docs/OPERATIONS.md` §9 (tabel QA 15 langkah) |
| 13 | Email transaksional & PDF e-ticket | `READY_FOR_REVIEW` | `docs/OPERATIONS.md` §10 |
| 14 | Pengingat WhatsApp | `READY_FOR_REVIEW` | `docs/OPERATIONS.md` §11 |
| 15 | Integrasi review & QA manual akhir | `NOT_STARTED` | belum dimulai |

`Terimplementasi` berarti fase 01–09 sudah menjadi dasar fase berikutnya dan
tidak ada pekerjaan terbuka di dalamnya; catatan persetujuan tertulis per fase
tidak disimpan di repository ini.

`READY_FOR_REVIEW` berarti implementasi selesai dan verifikasi non-test sudah
dilakukan, tetapi pemilik bisnis belum menyatakan hasil QA manualnya. **Hanya
pemilik bisnis yang boleh mengubah status itu menjadi `DONE`.** Agen/developer
tidak menaikkan status sendiri, sekalipun seluruh checklist sudah dijalankan.

### 1.2 Cara memperbarui dokumen ini

1. Saat mulai mengerjakan fase: ubah statusnya menjadi `IN_PROGRESS` dan tulis
   tanggal mulai.
2. Saat implementasi selesai: ubah menjadi `READY_FOR_REVIEW`, lalu tambahkan
   bagian **Catatan implementasi** yang memuat keputusan teknis, migrasi,
   permission baru, konfigurasi, dan file penting. Bagian ini yang dibaca orang
   berikutnya; jangan diganti dengan ringkasan lisan.
3. Saat pemilik bisnis menyetujui: ubah menjadi `DONE` beserta tanggal dan nama
   penyetuju.
4. Setiap perubahan status ikut memperbarui tabel di §1.1 **dan** tabel status
   di `PRD.md` §1.2. Bila status berubah karena keputusan bisnis, `PRD.md` §6
   dan §15 juga harus diperbarui pada perubahan yang sama.
5. Catatan implementasi fase lama tidak dihapus. Dokumen ini adalah riwayat
   keputusan, bukan hanya daftar tugas.

## 2. Project Rules

1. Gunakan Laravel, MySQL, dan Blade sesuai konfigurasi proyek.
2. Pertahankan arsitektur yang sudah ada jika benar dan konsisten.
3. Gunakan migration untuk perubahan schema database.
4. Jangan menghapus data atau menjalankan operasi destruktif tanpa persetujuan.
5. Jangan menambahkan payment gateway atau integrasi eksternal tanpa persetujuan.
6. Jangan menerbitkan tiket sebelum pembayaran disetujui admin.
7. Terapkan validasi server-side dan otorisasi pada setiap operasi sensitif.
8. Pengujian mengikuti Testing Policy (bagian tanpa nomor di antara §8 dan §9): fase 01–06 memakai automated test yang sudah ada, sedangkan Phase 07–15 diverifikasi lewat QA manual dan pemeriksaan non-test. Jangan menambah automated test pada fase yang dicakup kebijakan itu.
9. Jika requirement bertentangan atau belum lengkap, hentikan bagian yang terdampak dan laporkan kebutuhan klarifikasi.
10. Setelah fase selesai, laporkan file yang berubah, hasil pengujian, dan pekerjaan tersisa.
11. Jangan menandai fase `DONE` atas inisiatif sendiri. Status itu milik pemilik bisnis setelah QA manual (§1.2).
12. Jangan menambah dependency, layanan eksternal, atau perubahan schema yang tidak diperlukan oleh fase aktif.
13. Perubahan yang terlihat benar dari `php -l`, Pint, atau pembacaan file belum tentu berjalan. Jalankan jalur tulis yang diubah (create/update) di lingkungan lokal sebelum melaporkan selesai.

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

## Testing Policy — berlaku untuk Phase 07 sampai 15

### Objective
Prioritaskan progres implementasi dan QA manual di atas automated testing untuk
Phase 07 sampai 15. Phase 01–06 sudah memiliki automated test yang dibuat
sebelum kebijakan ini berlaku; test itu dipertahankan apa adanya dan tidak
menjadi syarat untuk fase berikutnya.

### Rules
1. Jangan membuat unit test, feature test, integration test, browser test, atau end-to-end test untuk Phase 07 sampai Phase 15.
2. Jangan menjalankan suite otomatis (`php artisan test`, PHPUnit, Pest) pada fase-fase tersebut.
3. Jangan membuat, memperbarui, men-debug, atau memperbaiki automated test untuk fase-fase itu.
4. Jangan menahan penyelesaian fase karena automated test tidak ada atau tidak dijalankan.
5. Tetap ikuti konvensi Laravel, validasi input, penanganan error, otorisasi, dan integritas database.
6. Verifikasi non-test tetap wajib: `php -l`, Pint, `php artisan view:cache`, `route:list`, `schedule:list`, dan **menjalankan jalur tulis yang diubah** (create/update/delete) pada data uji yang di-rollback. Membaca kode atau lolos lint tidak membuktikan sebuah jalur tulis berjalan.
7. Dokumentasikan fitur, route, perilaku yang diharapkan, keterbatasan, dan langkah QA manual pengguna di `docs/OPERATIONS.md`.
8. Jika automated test lama gagal atau ada masalah terkait test, laporkan singkat tanpa memperluas test suite — kecuali kegagalan itu menunjukkan cacat aplikasi yang harus diperbaiki.

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

## 12. Phase 10 — Role & Permission Management

**Status:** `READY_FOR_REVIEW` — implementasi selesai, menunggu QA manual.

Membangun fondasi sistem hak akses.

- CRUD role dan permission per tindakan.
- Satu role untuk setiap admin.
- Role Super Admin permanen dan terlindungi.
- Perubahan permission berlaku pada request berikutnya.
- Proteksi backend dan audit terhadap perubahan akses.

Selesai jika: akses menu dan setiap tindakan backend mengikuti permission terbaru.

## 13. Phase 11 — Admin Account & Invitation

**Status:** `READY_FOR_REVIEW` — implementasi selesai, menunggu QA manual.

Pengelolaan akun dan profil admin.

- Undangan admin melalui email.
- Token sekali pakai, berlaku 24 jam.
- Pembuatan password oleh penerima undangan.
- Aktivasi, penonaktifan, dan aktivasi ulang akun.
- Profil admin: ubah nama dan password.
- Pencabutan sesi akun yang dinonaktifkan.
- Akun Super Admin terakhir tidak boleh dinonaktifkan.

Selesai jika: akun baru hanya aktif setelah undangan diterima dan akun nonaktif tidak dapat menggunakan dashboard.

Catatan implementasi:

- Status akun disimpan di `users.status` (`invited`, `active`, `inactive`).
  Akun undangan tidak memiliki password (`users.password` nullable), sehingga
  tidak dapat login sebelum undangan diterima.
- Token undangan disimpan sebagai hash SHA-256 di `admin_invitations`; tautan
  asli hanya ada di email. Menerbitkan ulang undangan mencabut tautan lama.
- Permission baru: `admin-accounts.view` dan `admin-accounts.manage`. Role
  Super Admin otomatis memilikinya; role lain harus diberikan lewat layar Role
  (Phase 10).
- Akun nonaktif dicabut sesinya (baris `sessions` dihapus) dan ditolak middleware
  `admin` pada request berikutnya.
- Pengaman tambahan: admin tidak dapat menonaktifkan akun sendiri, dan hanya
  Super Admin yang dapat menugaskan atau mengundang ulang akun Super Admin.
- Profil (`/admin/profile`) bersifat self-service: tidak memerlukan permission
  akun, tetapi perubahan password mewajibkan password saat ini.
- Email undangan dikirim sinkron; kegagalan pengiriman dicatat ke log dan tidak
  membatalkan akun. Status pengiriman, retry, dan template email transaksional
  penuh tetap menjadi scope Phase 13.


## 14. Phase 12 — Payment Settings & Audit Log

**Status:** `READY_FOR_REVIEW` — implementasi selesai, menunggu QA manual.

Mengatur metode pembayaran dan jejak aktivitas.

- Admin yang berizin dapat toggle metode pembayaran.
- Hanya Super Admin dapat mengubah detail rekening, VA, dan QRIS.
- Pesanan lama mempertahankan snapshot instruksi pembayaran.
- Audit log hanya dapat diakses Super Admin.
- Catat perubahan konfigurasi, role, verifikasi pembayaran, dan aktivitas penting lainnya.

Selesai jika: perubahan konfigurasi tidak mengubah instruksi pembayaran pesanan lama.

Catatan implementasi:

- Katalog metode pembayaran sekarang disimpan di tabel `payment_methods`
  (migrasi `2026_10_26_000002_create_payment_methods_table`). Selama tabel
  masih kosong, `PaymentMethodRegistry` membaca `config/payments.php` seperti
  Phase 05, sehingga instalasi lama tetap berjalan. `PaymentMethodSeeder`
  menyalin katalog config ke tabel secara idempotent (`firstOrCreate`), jadi
  perubahan yang dibuat lewat panel tidak tertimpa saat deploy berikutnya.
- Permission baru: `payment-methods.view` dan `payment-methods.manage`.
  Melihat daftar dan toggle aktif/nonaktif mengikuti permission tersebut.
  Mengubah detail rekening/VA/QRIS — termasuk menghapus penanda data contoh —
  hanya untuk role Super Admin dan diperiksa lewat policy, bukan permission
  yang bisa diberikan ke role lain.
- Metode bertanda `is_dummy` hanya ditawarkan di environment `local` dan
  `testing`; di production metode tersebut tidak muncul sama sekali sampai
  Super Admin mengisi detail resmi dan menghapus centangnya. Ini menjaga
  pertanyaan PRD 1, 2, dan 16 tetap terbuka tanpa menampilkan instruksi palsu.
- Pesanan menyimpan `orders.payment_instructions_snapshot` saat dibuat
  (`OrderService`). Halaman status pesanan dan form unggah bukti membaca
  snapshot itu lewat `PaymentMethodRegistry::forOrder()`, sehingga perubahan
  konfigurasi hanya memengaruhi pesanan baru. Metode yang sudah dinonaktifkan
  tetap dapat dipakai menyelesaikan pembayaran pesanan lama. Migrasi mengisi
  snapshot pesanan lama dari katalog yang aktif saat migrasi dijalankan.
- Toggle bersifat non-destruktif: tidak ada hapus metode, hanya aktif/nonaktif,
  agar `payments.method` dan snapshot lama tetap dapat ditelusuri.
- Audit log read-only dan hanya dapat dibuka Super Admin (`AuditLogPolicy`),
  tanpa permission yang bisa diberikan. `/admin/audit-logs` mendukung filter
  tindakan, pelaku, dan rentang tanggal, plus halaman detail. Tidak ada route
  create, update, atau delete.
- Aksi baru yang dicatat: `payment.method_updated`,
  `payment.method_activated`, `payment.method_deactivated`. Aksi Phase 07
  (`payment.*`), Phase 08 (`ticket.*`), Phase 10 (`role.*`, `permission.*`),
  dan Phase 11 (`admin.*`) masuk trail yang sama.
- Belum ada formulir tambah metode: `key` sengaja tidak dapat diubah karena
  dipakai `payments.method` dan snapshot. Metode baru masih lewat
  `config/payments.php` + `PaymentMethodSeeder`. Daftar final metode dan
  detail rekening/VA/QRIS tetap menunggu keputusan bisnis.

Catatan: tiga keterbatasan Phase 12 di atas — belum ada formulir tambah metode,
validasi detail yang belum mengikuti tipe, dan aktivasi yang masih global —
sudah ditutup di Phase 12B (§15). Bagian ini dibiarkan sebagai riwayat agar
alasan perubahan itu tetap terbaca.

## 15. Phase 12B — Payment Method Management (CRUD)

**Status:** `READY_FOR_REVIEW` — implementasi selesai, menunggu QA manual.

Melengkapi Phase 12: metode pembayaran dikelola dari dashboard, aktivasi diatur
per event, dan detail validasinya mengikuti tipe.

- Katalog global (`payment_methods`) diperluas dengan `type` dan `qr_image_path`.
  Tambah metode lewat formulir; kunci hanya diisi saat pembuatan karena dipakai
  `payments.method` dan snapshot pesanan, dan kunci yang masih dirujuk riwayat
  ditolak agar riwayat tidak berpindah ke metode baru.
- Tipe pembayaran (`App\Enums\PaymentMethodType`: transfer bank, virtual account,
  e-wallet, QRIS) menentukan detail mana yang wajib diisi dan apakah QR image
  diperlukan. Hanya baris detail yang labelnya dikenal aplikasi yang ditulis;
  baris lama bertanda tidak dikenal tetap disimpan dan tetap tampil.
- Aktivasi per event disimpan di tabel baru `event_payment_methods` sebagai
  override. Tanpa baris override, event mengikuti status global, sehingga
  instalasi yang tidak pernah membuka layar per event berperilaku sama seperti
  Phase 12. Layar aktivasi baru: `admin/events/{event}/payment-methods`, lengkap
  dengan tombol "Ikuti Global" untuk membatalkan override.
- Peserta hanya ditawari metode yang **aktif**, **lengkap** detailnya, dan tidak
  dimatikan untuk event tersebut. Snapshot instruksi pesanan dibuat per event
  (`PaymentMethodRegistry::snapshotForEvent`), sehingga aturan itu berlaku pada
  pesanan baru tanpa menyentuh pesanan lama.
- Permission dipecah dan diganti namanya: `payment_methods.view`,
  `.create`, `.update`, `.toggle`, `.delete`. Migrasi rename bekerja berdasarkan
  id sehingga role yang sudah diberi akses tidak kehilangan aksesnya. Menandai
  detail sebagai data resmi (menghapus `is_dummy`) tetap hanya Super Admin, dan
  pemeriksaan itu tidak dapat dilewati lewat request langsung. Kontrol `is_dummy`
  tidak dirender untuk non-Super Admin, dan flag yang tidak dikirim berarti
  status data **dipertahankan** — bukan diturunkan menjadi data contoh, karena
  penurunan itu akan menyembunyikan metode yang sudah dikonfirmasi bisnis.
- Hapus aman: metode yang sudah dirujuk `payments.method` atau tersimpan pada
  snapshot pesanan mana pun ditolak. Alternatifnya adalah menonaktifkan, yang
  ditawarkan di UI; `payment_methods.delete` hanya berguna untuk metode yang
  benar-benar belum pernah dipakai.
- Audit: `payment.method_created`, `payment.method_updated` (before/after),
  `payment.method_activated/deactivated`, `payment.method_event_toggled`
  (dengan `event_id` dan `event_name`), `payment.method_event_inherited`, dan
  `payment.method_deleted`.
- Model data: detail tetap global, aktivasi per event. Tidak ada detail rekening
  per event, karena itu di luar lingkup.
- Sesuai Testing Policy, tidak ada automated test baru. Verifikasi: empat migrasi
  berjalan (`type`+`qr_image_path`, `event_payment_methods`, rename permission,
  backfill tipe dari config), `RolePermissionSeeder` (19 permission, Admin tetap
  `payment_methods.view` + `.toggle`), `route:list` (10 route metode pembayaran),
  kompilasi Blade (`view:cache`), Pint, dan smoke check dalam transaksi yang
  di-rollback (scoping per event, snapshot pesanan, keamanan hapus).
- Dua bug runtime ditemukan saat QA manual dan sudah diperbaiki:
  `PaymentMethodController::store()` memanggil `nextSortOrder()` yang tidak
  pernah ada (kini helper privat: `max('sort_order') + 1`, menambahkan di akhir
  tanpa menomori ulang dan tanpa menutup celah), dan closure `DB::transaction`
  pada `update()` memakai `$before` tanpa menangkapnya, sehingga **setiap** simpan
  "Ubah Detail" gagal 500. Keduanya lolos `php -l`, Pint, dan pembacaan file —
  hanya terlihat ketika jalur tulis benar-benar dijalankan. Aturan ini sekarang
  tercatat di §2 butir 13 dan §23 butir 2.
- Verifikasi ulang setelah perbaikan: create/update dijalankan nyata lalu
  di-rollback (sort 2 → 3, `is_active=false`, `is_dummy=false`, detail dan
  langkah tersimpan, duplikat kunci ditolak validasi, role non-berizin 403,
  `is_dummy` dipertahankan saat flag tidak dikirim), lima halaman admin dirender
  lewat controller, dan perilaku snapshot diuji lewat `OrderService`
  (metode event 6 dikosongkan → instruksi kosong, dikembalikan → instruksi
  pulih; override dan pesanan tidak berubah setelah rollback).
- Yang belum tercakup verifikasi otomatis/manual: isolasi antar-event (butuh
  event kedua), unggah gambar QRIS sungguhan, dan aktivasi metode dengan detail
  belum lengkap (langkah 5 pada tabel QA `docs/OPERATIONS.md` §9).

## 16. Phase 13 — Email Notification & PDF E-ticket

**Status:** `READY_FOR_REVIEW` — implementasi selesai, menunggu QA manual.

Mengembangkan email transaksional menggunakan Laravel Mail.

- Email undangan admin.
- Email e-ticket dalam bentuk lampiran PDF.
- PDF memuat detail event dan QR token tiket yang sama dengan database.
- Tombol Kirim Ulang Tiket pada detail pesanan.
- Status pengiriman, retry, dan pencatatan hasil.
- Permission khusus untuk mengirim ulang tiket.

Selesai jika: email dapat dikirim ulang tanpa membuat tiket atau QR baru.

Catatan implementasi:

- Tabel baru `email_deliveries` (migrasi
  `2026_10_26_000004_create_email_deliveries_table`) mencatat **setiap** percobaan
  pengiriman email transaksional, baik e-ticket maupun undangan admin. Satu baris
  per percobaan bersifat append-only: kirim ulang menambah baris baru, bukan
  menimpa yang gagal, sehingga urutan "gagal lalu berhasil" tetap terbaca.
  Kolomnya: `emailable_type`/`emailable_id` (morph), `recipient_email`, `status`
  (`SENT`/`FAILED`/`SKIPPED`), `error_message`, `sent_by`, `created_at`.
- `EmailDeliveryStatus` membedakan tiga hasil. `SKIPPED` berarti email memang
  belum diaktifkan (`TICKET_EMAIL_ENABLED=false`), bukan kegagalan; tanpa
  pembedaan itu setiap pesanan pada instalasi tanpa layanan email akan tampak
  "gagal dikirim" dan operator akan mengabaikannya. `EmailDeliveryLogger`
  dibentuk seperti `AuditLogger`: penulisannya di luar transaksi pemanggil dan
  tidak pernah melempar exception, karena PRD FR-09 mewajibkan kegagalan email
  tidak membatalkan tiket yang sah.
- PDF dibuat tanpa dependency baru. `bacon/bacon-qr-code` sudah ada untuk QR di
  halaman tiket, dan matriks modulnya digambar sebagai vektor di dalam PDF 1.4
  yang disusun langsung oleh `TicketPdfRenderer` (font base-14 Helvetica,
  encoding Windows-1252). Tidak ada pustaka PDF yang ditambahkan, tidak ada font
  yang di-embed, dan berkasnya hanya beberapa kilobyte. Konsekuensinya teks
  dikonversi ke Windows-1252: huruf beraksen ditransliterasi dan karakter di
  luar set tersebut (mis. emoji atau aksara non-Latin) tidak muncul di PDF.
  Bila bisnis nanti menuntut tata letak berbasis HTML/CSS, penggantian ke
  pustaka seperti dompdf adalah keputusan terpisah karena menambah dependency.
- PDF berisi nama event, kode pesanan, nama peserta, waktu, lokasi, status tiket,
  waktu terbit, dan QR berisi `tickets.qr_token` — nilai yang sama yang
  divalidasi scanner. Tidak ada data pribadi di dalam QR (PRD FR-09). PDF
  dirender per permintaan dari database, tidak disimpan, sehingga tidak ada
  salinan usang yang bisa menyimpang dari status tiket sebenarnya.
- `TicketIssuedMail` sekarang melampirkan PDF dan tetap menyertakan tautan
  halaman tiket. `TicketMailer::send()` mengembalikan `EmailDelivery`, bukan
  boolean, sehingga pemanggil dapat membedakan terkirim, gagal, dan dilewati.
- Kirim ulang: route `POST /admin/orders/{order}/ticket-email`
  (`admin.orders.ticket-email`) memakai tiket dan QR yang sudah ada; alur ini
  tidak pernah menyentuh `TicketIssuanceService`, jadi tidak mungkin membuat
  tiket kedua. Otorisasinya `OrderPolicy::resendTicket` (permission baru
  `tickets.resend`, grup "Tiket") dan mensyaratkan tiket sudah ada. Setiap kirim
  ulang menulis audit `ticket.email_resent` berisi penerima dan hasilnya.
- Halaman detail pesanan menampilkan kartu **Email E-Ticket**: penerima, status
  percobaan terakhir, pesan error bila gagal, lima percobaan terakhir, dan tombol
  **Kirim Ulang Tiket** (hanya bila berizin). Bila email belum diaktifkan,
  muncul peringatan bahwa peserta masih dapat membuka tiket dari halaman status.
- Undangan admin (Phase 11) kini ikut tercatat. Daftar **Akun Admin** menampilkan
  badge status pengiriman terakhir pada kolom Undangan, dan pesan sukses/gagal
  pada pembuatan maupun kirim ulang undangan tetap seperti sebelumnya.
- Halaman tiket peserta mendapat tombol **Unduh PDF**
  (`GET /tickets/{token}/pdf`, `tickets.pdf`) dengan token akses yang sama seperti
  halaman tiket. Tombol cetak lama tetap ada.
- Retry masih manual (tombol kirim ulang). Pengiriman otomatis lewat queue,
  penjadwalan retry, dan batas jumlah percobaan belum dibuat: `QUEUE_CONNECTION`
  sudah `database` tetapi worker tidak dijalankan pada MVP, dan kebijakan retry
  otomatis belum ditetapkan bisnis. Semua percobaan tetap tercatat sehingga
  keputusan itu nanti tidak kehilangan data.


## 17. Phase 14 — WhatsApp Notification Integration

**Status:** `READY_FOR_REVIEW` — implementasi selesai, menunggu QA manual.

Integrasi gateway pihak ketiga dengan arsitektur yang fleksibel.

- Reminder pada menit ke-30 dan ke-50.
- Link aman menuju halaman status pesanan.
- Reminder hanya jika bukti pembayaran belum diunggah.
- Reminder berhenti setelah bukti diunggah atau pesanan tidak lagi valid.
- Notifikasi pembayaran berhasil dan informasi tiket dikirim melalui email.
- Retry terbatas, status pengiriman, dan pencegahan duplikasi pesan.

Selesai jika: pesan mengikuti status pesanan terbaru dan tidak mengirim reminder yang sudah tidak relevan.

Catatan implementasi:

- Tabel baru `whatsapp_deliveries` (migrasi
  `2026_10_26_000005_create_whatsapp_deliveries_table`) menyimpan satu baris per
  pesanan per tahap pengingat. Unique index `(order_id, kind)` adalah pengaman
  duplikasi; `attempts`, `status`, `driver`, `error_message`, dan `sent_at`
  adalah status pengiriman yang dibaca operator. Berbeda dengan
  `email_deliveries` yang append-only, baris di sini diperbarui selama percobaan
  ulang karena satu tahap hanya boleh menghasilkan satu pesan.
- Arsitektur gateway: `App\Services\WhatsApp\WhatsAppGateway` adalah kontrak
  tunggal; driver dipilih lewat `config/whatsapp.php`. Provider WhatsApp belum
  ditetapkan bisnis (PRD section 5), jadi satu-satunya driver yang dikirim adalah
  `log`. Driver itu tidak menghubungi provider mana pun, hanya menulis pesan ke
  log dan mencatat `driver=log`, sehingga QA dapat menguji seluruh alur tanpa
  kredensial. Menambahkan provider nyata = satu class adapter + satu entri
  config, tanpa mengubah service.
- `WhatsAppReminderService` hanya membuat pengingat untuk pesanan
  `PENDING_PAYMENT` yang belum punya baris `payments`, belum lewat `expires_at`,
  dan sudah berumur 30/50 menit. Jika scheduler terlambat, hanya tahap terbaru
  yang berlaku: pesanan yang baru terlihat pada menit ke-55 hanya menerima
  pengingat menit ke-50, dan baris menit ke-30 ditutup `SKIPPED`. Pesanan yang
  sudah punya bukti, sudah diputuskan, atau sudah kedaluwarsa tidak pernah
  dikirimi pengingat.
- Pengingat berjalan lewat command `whatsapp:send-payment-reminders` yang
  dijadwalkan setiap menit (`routes/console.php`, `withoutOverlapping`).
  Percobaan diklaim dengan `UPDATE ... WHERE attempts = ...` sebelum gateway
  dipanggil, sehingga dua proses tidak dapat mengirim baris yang sama. Retry
  otomatis dibatasi `WHATSAPP_MAX_ATTEMPTS` (default 3) dan hanya berjalan
  selama pesanan masih relevan; tidak ada tombol kirim ulang manual untuk
  pengingat.
- Pesan memakai tautan `orders/{access_token}` (halaman status), bukan token QR.
  Token QR tetap hanya untuk scanner check-in. Notifikasi pembayaran berhasil
  dan e-ticket tetap dikirim lewat email Phase 13; Phase 14 tidak menambah pesan
  WhatsApp untuk pesanan yang sudah dibayar.
- Detail pesanan admin mendapat kartu **Pengingat WhatsApp** berisi penerima,
  status setiap tahap, jumlah percobaan, driver, waktu kirim, dan alasan
  `SKIPPED`/gagal. Tidak ada permission baru karena pengingat sepenuhnya
  otomatis.
- Default `WHATSAPP_ENABLED=false`. Mengaktifkannya dengan driver `log` hanya
  untuk QA lokal/staging. Adapter provider nyata, kredensial, redaksi pesan
  resmi, kebijakan opt-out, dan pengiriman lewat queue masih menunggu keputusan
  bisnis.
- Sesuai Testing Policy, tidak ada automated test baru untuk Phase 14.
  Verifikasi dilakukan lewat migration, `schedule:list`, kompilasi Blade, Pint,
  dan QA manual.


## 18. Phase 15 — Integration Review & Manual QA

**Status:** `NOT_STARTED` — belum dimulai; menunggu QA manual Phase 10–14 selesai.

Memastikan seluruh improvement bekerja bersama.

- Periksa alur role, undangan, pembayaran, email, dan WhatsApp.
- Siapkan checklist QA manual dengan langkah dan hasil yang diharapkan.
- Periksa log, konfigurasi, dan kegagalan pengiriman.
- Dokumentasikan isu yang belum selesai.
- Tidak membuat unit test otomatis untuk fase ini jika kamu ingin kebijakan tanpa automated testing diterapkan juga pada task improvement.

Selesai jika: kamu dapat menjalankan checklist QA manual dan menyetujui hasilnya.

---

## 19. Testing Strategy

Bagian ini berlaku untuk **Phase 01–06**. Phase 07–15 mengikuti bagian *Testing
Policy* (tanpa automated test baru, verifikasi lewat QA manual). Keduanya bukan
aturan yang bertentangan: Testing Policy menang untuk fase yang dicakupnya.

Untuk fase yang memakai automated test:
1. Jalankan automated tests yang relevan.
2. Tambahkan regression tests untuk perubahan yang menyentuh alur yang sudah ada.
3. Uji validasi dan otorisasi.
4. Uji error states dan kasus input tidak valid.
5. Untuk kuota, pembayaran, penerbitan tiket, dan check-in, uji integritas data serta request bersamaan.
6. Jangan menandai fase selesai jika pengujian yang diwajibkan gagal.

Untuk fase yang dicakup Testing Policy, "pengujian yang diwajibkan" berarti:
- verifikasi non-test pada butir 6 Testing Policy, dan
- QA manual di `docs/OPERATIONS.md` yang disetujui pemilik bisnis.

---

## 20. Task Execution Protocol

Ketika diminta mengerjakan task:

1. Identifikasi fase dan scope yang diminta.
2. Baca `AGENTS.md` serta bagian relevan `PRD.md` dan `styling.md`.
3. Periksa kode, migrasi, dan test yang sudah ada.
4. Rencanakan file yang akan diubah.
5. Implementasikan hanya scope task tersebut.
6. Jalankan verifikasi yang relevan — §19 untuk Phase 01–06, Testing Policy untuk Phase 07–15.
7. Perbaiki regresi yang disebabkan perubahan.
8. Laporkan hasil dan sisa pekerjaan.
9. Perbarui `IMPLEMENTATION_PLAN.md` §1.1, catatan implementasi fase, dan `docs/OPERATIONS.md` sebelum menyatakan selesai.

Jangan otomatis mengerjakan fase selanjutnya.

---

## 21. Progress Tracking

Tandai fase sebagai:
- `NOT_STARTED` — belum dikerjakan.
- `IN_PROGRESS` — sedang dikerjakan.
- `READY_FOR_REVIEW` — implementasi dan verifikasi non-test selesai; QA manual menunggu pemilik bisnis.
- `BLOCKED` — menunggu keputusan atau akses; sebutkan apa yang ditunggu.
- `DONE` — pemilik bisnis sudah menyetujui hasil QA manual.

Jangan menandai `DONE` tanpa acceptance criteria terpenuhi, verifikasi yang relevan dijalankan, dan persetujuan eksplisit pemilik bisnis.

Fase `BLOCKED` harus menyebutkan penghalangnya secara konkret (mis. "butuh event kedua untuk menguji isolasi per event") agar penghalang itu bisa dihilangkan, bukan sekadar dicatat.

Catat keputusan bisnis yang belum terjawab sebagai open decisions di `PRD.md` §15, bukan asumsi final.

---

## 22. Final Deliverable

Aplikasi dianggap siap untuk evaluasi operasional setelah:
- Semua fase yang termasuk MVP selesai dan tidak ada fase berstatus `READY_FOR_REVIEW` atau `BLOCKED`.
- Acceptance criteria terpenuhi.
- Pengujian alur kritis lulus.
- Konfigurasi pembayaran, email, dan environment telah diverifikasi.
- Dokumentasi setup dan operasional tersedia.
- Risiko dan keputusan terbuka telah ditinjau oleh pemilik bisnis.
- Keterbatasan di `docs/OPERATIONS.md` §12 ditinjau dan diterima pemilik bisnis.

## 23. Catatan serah terima untuk developer berikutnya

### Yang perlu diketahui sebelum menyentuh kode

1. **Baca kode, bukan hanya dokumen.** Dokumen ini mencatat niat dan keputusan;
   route, policy, dan migration adalah kenyataan. Bila keduanya berbeda,
   perbaiki dokumen pada perubahan yang sama.
2. **Verifikasi jalur tulis dengan menjalankannya.** Dua bug Phase 12B (helper
   `nextSortOrder()` yang tidak ada dan variabel `$before` yang tidak ditangkap
   closure transaksi) lolos `php -l`, Pint, dan penelusuran file; keduanya hanya
   terlihat saat `store()`/`update()` benar-benar dijalankan. Jalankan create dan
   update pada data uji yang di-rollback sebelum melaporkan selesai.
3. **Jangan menaikkan status fase sendiri.** `READY_FOR_REVIEW` → `DONE` adalah
   keputusan pemilik bisnis (§1.2, §21).
4. **Jangan menambah dependency tanpa persetujuan.** PDF e-ticket sengaja dibuat
   tanpa pustaka PDF baru; email dan WhatsApp memakai driver bawaan.
5. **Data contoh tidak boleh tampil di production.** Metode pembayaran bertanda
   `is_dummy` disembunyikan di luar `local`/`testing`. Jangan melonggarkan aturan
   ini untuk kemudahan QA.
6. **Snapshot adalah pengaman riwayat.** Instruksi pembayaran disimpan per
   pesanan; perubahan katalog tidak boleh menyentuh pesanan lama.
7. **Uji isolasi per event butuh dua event.** QA Phase 12B langkah 6–8 tidak
   dapat dilakukan pada instalasi yang hanya punya satu event.

### Di mana mencari sesuatu

| Kebutuhan | Lokasi |
| --- | --- |
| Kebutuhan produk & open decisions | `PRD.md` §8, §15 |
| Status fase & acceptance criteria | dokumen ini §1.1 dan bagian fase |
| Perintah deploy, backup, scheduler | `docs/OPERATIONS.md` §1–§6 |
| Checklist QA manual per fase | `docs/OPERATIONS.md` §7–§11 |
| Keterbatasan yang sudah diketahui | `docs/OPERATIONS.md` §12 |
| Skema database | `database/migrations` |
| Katalog permission | `app/Enums/AdminPermission.php` + `RolePermissionSeeder` |
| Katalog metode pembayaran awal | `config/payments.php` + `PaymentMethodSeeder` |
| Konfigurasi email/WhatsApp/cron | `config/tickets.php`, `config/whatsapp.php`, `config/cron.php` |

### Pekerjaan yang tersisa

- QA manual pemilik bisnis untuk Phase 10, 11, 12, 12B, 13, dan 14.
- Phase 15 (integrasi review & QA akhir) belum dimulai.
- Keputusan bisnis terbuka: `PRD.md` §15 (metode dan detail pembayaran resmi,
  provider email dan WhatsApp, kebijakan unggah ulang bukti, retry otomatis,
  reset check-in, zona waktu, disk produksi, pemisahan tugas admin).

### Riwayat dokumen

| Versi | Tanggal | Perubahan |
| --- | --- | --- |
| 1.0 | 21 Oktober 2026 | Rencana awal Phase 01–09 dan improvement 10–15 |
| 1.1 | 27 Oktober 2026 | Status per fase (§1.1), aturan pembaruan dokumen (§1.2), penomoran bagian dirapikan (nomor bagian = nomor fase + 2), Testing Policy diperluas ke Phase 07–15 dan direkonsiliasi dengan Testing Strategy, catatan serah terima (§23), status `READY_FOR_REVIEW` ditambahkan ke Progress Tracking |

**End of Implementation Plan**
