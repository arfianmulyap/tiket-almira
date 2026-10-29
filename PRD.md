# Product Requirements Document (PRD)

## Almira Tiket Event

**Versi:** 3.0\
**Status:** Aktif — seluruh fase 01–14 sudah diimplementasikan dan sedang menunggu QA manual pemilik bisnis. Keputusan bisnis yang belum terjawab dicatat di §15, bukan diasumsikan.\
**Tanggal:** 21 Oktober 2026; direvisi 27 Oktober 2026\
**Platform:** Web application\
**Stack:** Laravel + MySQL + Laravel Blade

**Dokumen pendamping** — dokumen ini memuat kebutuhan produk; status pekerjaan hanya diringkas di §1.2 dan rinciannya ada di dokumen pendamping berikut:

| Dokumen | Isi |
| --- | --- |
| `IMPLEMENTATION_PLAN.md` | Pembagian fase, status tiap fase, acceptance criteria, kebijakan pengujian |
| `docs/OPERATIONS.md` | Runbook: scheduler, backup, checklist rilis, QA manual per fase, keterbatasan |
| `docs/DEPLOY_VERCEL.md` | Deployment serverless |
| `styling.md` | Sumber kebenaran visual (warna, tipografi, komponen) |
| `AGENTS.md` | Aturan kerja agen/developer di repository ini |

------------------------------------------------------------------------

## 1. Ringkasan Produk

Almira Tiket Event adalah aplikasi web internal untuk mengelola berbagai
event yang diselenggarakan oleh Almira. Aplikasi mencakup pengelolaan
event, pemesanan tiket, pembayaran manual, verifikasi oleh admin,
penerbitan e-ticket dengan QR unik, dan check-in peserta.

Sistem dirancang untuk digunakan kembali pada banyak event. Setiap event
mempunyai konfigurasi sendiri, termasuk poster, harga, kuota, informasi
acara, dan metode/instruksi pembayaran yang tersedia.

Pembayaran MVP dilakukan secara manual. Peserta melakukan transfer,
mengunggah bukti pembayaran, lalu menunggu verifikasi admin. Tiket hanya
diterbitkan setelah pembayaran disetujui.

### 1.1 Cara membaca dokumen ini

- Angka **FR-\*** adalah persyaratan fungsional yang harus terpenuhi.
- Bagian yang belum diputuskan bisnis ditulis sebagai **open decision** dan
  dikumpulkan di §15. Tidak ada keputusan bisnis baru yang boleh diciptakan
  oleh developer; perubahan detail implementasi harus tetap dapat ditelusuri ke
  salah satu persyaratan di sini.
- Kriteria penerimaan di §13 sengaja tidak dicentang di dokumen ini. Penandaan
  hanya dilakukan pemilik bisnis setelah QA manual, karena bagian ini adalah
  catatan persetujuan, bukan daftar pekerjaan.
- Nama tabel, kelas, dan route di dokumen ini adalah **contoh bentuk data**
  (§12), bukan kontrak. Kontrak teknisnya ada di kode dan `docs/OPERATIONS.md`.

### 1.2 Ringkasan status implementasi

| Area | Fase | Status |
| --- | --- | --- |
| Fondasi Laravel, MySQL, login admin | 01 | Terimplementasi |
| Design system & layout | 02 | Terimplementasi |
| Event management & poster | 03 | Terimplementasi |
| Pemesanan publik & reservasi kuota | 04 | Terimplementasi |
| Pembayaran manual & unggah bukti | 05 | Terimplementasi |
| Tenggat 60 menit & integritas kuota | 06 | Terimplementasi |
| Verifikasi pembayaran & e-ticket | 07 | Terimplementasi |
| QR check-in | 08 | Terimplementasi |
| Dashboard, runbook, QA rilis | 09 | Terimplementasi |
| Role & permission | 10 | Terimplementasi — `READY_FOR_REVIEW` |
| Akun admin & undangan | 11 | Terimplementasi — `READY_FOR_REVIEW` |
| Metode pembayaran & audit log | 12 | Terimplementasi — `READY_FOR_REVIEW` |
| Metode pembayaran CRUD & aktivasi per event | 12B | Terimplementasi — `READY_FOR_REVIEW` |
| Email transaksional & PDF e-ticket | 13 | Terimplementasi — `READY_FOR_REVIEW` |
| Pengingat WhatsApp | 14 | Terimplementasi — `READY_FOR_REVIEW` |
| Integrasi review & QA manual akhir | 15 | Belum dimulai |

"Terimplementasi" berarti fitur ada di kode dan dipakai oleh fase berikutnya.
Fase 10–14 berstatus `READY_FOR_REVIEW` karena QA manual belum diselesaikan
pemilik bisnis, sehingga belum boleh ditandai `DONE`. Fase 15 menutup risikonya:
memeriksa seluruh alur bersama setelah semua QA per fase selesai.

## 2. Latar Belakang dan Masalah

Pengelolaan tiket secara manual dapat menyebabkan data peserta tersebar,
kesulitan memeriksa pembayaran, ketidakakuratan kuota, serta check-in
yang lambat atau sulit diaudit.

Almira membutuhkan sistem terpusat untuk: - Mengelola beberapa event
dari satu panel admin. - Menampilkan informasi dan poster event. -
Mencatat pesanan dan data peserta secara konsisten. - Memverifikasi
pembayaran manual. - Menjaga kuota agar tidak melebihi kapasitas. -
Menerbitkan tiket digital unik setelah pembayaran disetujui. -
Memvalidasi tiket saat check-in dan mencegah penggunaan berulang. -
Menyediakan riwayat tindakan operasional penting.

## 3. Tujuan Produk

1.  Memusatkan pengelolaan event, pesanan, pembayaran, tiket, dan
    check-in.
2.  Membuat proses pemesanan mudah dipahami peserta.
3.  Memastikan tiket tidak terbit sebelum pembayaran disetujui admin.
4.  Mengurangi risiko overselling dan ketidaksesuaian status transaksi.
5.  Memudahkan admin meninjau bukti pembayaran dan menelusuri keputusan.
6.  Mendukung penggunaan berulang untuk event baru tanpa mengubah kode.

### Bukan tujuan MVP

-   Payment gateway otomatis.
-   Pembuatan nomor VA unik otomatis per pesanan.
-   Marketplace multi-organizer.
-   Beberapa tiket dalam satu pesanan.
-   Aplikasi mobile native.
-   Refund otomatis.
-   Rekonsiliasi bank otomatis.

## 4. Pengguna dan Hak Akses

### Peserta

Peserta dapat melihat event yang dipublikasikan, membuka detail event,
membuat satu pesanan untuk satu tiket, mengisi nama/WhatsApp/email,
melihat instruksi pembayaran, mengunggah bukti, melihat status pesanan
melalui mekanisme akses yang akan ditentukan, serta mengakses/mengunduh
tiket setelah diterbitkan.

Peserta tidak dapat menyetujui pembayaran, menerbitkan QR, melihat bukti
milik peserta lain, atau mengakses panel admin.

### Admin internal

Admin dapat login, mengelola event dan poster, mengatur
harga/kuota/instruksi pembayaran, melihat pesanan, meninjau bukti,
menyetujui atau menolak pembayaran, mencatat alasan penolakan, melihat
tiket, melakukan check-in, mengirim ulang e-ticket, serta melihat ringkasan
operasional dan riwayat audit.

Sejak Phase 10–14 hak akses tidak lagi seragam untuk semua admin:

- **Super Admin** — role permanen yang tidak dapat dihapus atau diubah. Satu-
  satunya yang dapat membuka audit log dan menandai detail metode pembayaran
  sebagai data resmi. Mengubah detail rekening/VA/QRIS sendiri merupakan
  permission yang dapat diberikan (`payment_methods.update`); yang tidak dapat
  didelegasikan hanyalah pernyataan "data ini resmi" (menghapus `is_dummy`).
- **Role lain** — dibuat admin lewat layar Role dan diberi permission per
  tindakan (melihat event, memverifikasi pembayaran, mengunduh bukti,
  mengaktifkan metode, mengundang akun, dan seterusnya). Satu akun admin
  memegang tepat satu role.
- **Akun** — dibuat lewat undangan, baru aktif setelah penerima menetapkan
  password. Akun nonaktif kehilangan sesinya dan ditolak pada request
  berikutnya. Perubahan permission berlaku pada request berikutnya, bukan hanya
  setelah login ulang.

Pemisahan tugas finance/operasional belum ditetapkan bisnis; yang tersedia
adalah bahan untuk memisahkannya (role dan permission), bukan pembagian
perannya.

### Peserta tanpa akun

Peserta tidak memiliki akun pada MVP. Akses ke status pesanan dan tiket
memakai token tak terduga di URL (`orders/{token}`, `tickets/{token}`), bukan
login. Konsekuensi keamanannya dicatat di `docs/OPERATIONS.md` §4 dan masih
menjadi open decision §15 pertanyaan 6.

## 5. Cakupan Produk

### Termasuk MVP

1.  Login admin dan proteksi panel admin.
2.  Dashboard admin.
3.  CRUD event dan publikasi event.
4.  Upload/ganti poster per event.
5.  Daftar dan detail event untuk peserta.
6.  Form pemesanan satu tiket.
7.  Pembayaran manual dengan instruksi sesuai metode.
8.  Upload bukti pembayaran wajib.
9.  Verifikasi pembayaran oleh admin.
10. Reservasi kuota dan tenggat 60 menit dengan aturan yang ditentukan.
11. E-ticket dengan QR/token unik.
12. Halaman tiket dan mekanisme unduh.
13. Pengiriman email jika layanan email dikonfigurasi.
14. Scanner QR/check-in berbasis web.
15. Audit tindakan penting.
16. Daftar pesanan, status pembayaran, tiket, dan check-in.
17. Role dan permission per tindakan, termasuk role Super Admin permanen (Phase 10).
18. Undangan akun admin lewat email, aktivasi, penonaktifan, dan profil sendiri (Phase 11).
19. Pengelolaan katalog metode pembayaran dari dashboard: tambah, ubah detail,
    aktif/nonaktif, hapus aman, dan aktivasi per event (Phase 12 & 12B).
20. E-ticket PDF yang dilampirkan ke email, plus tombol kirim ulang tanpa
    membuat tiket atau QR baru (Phase 13).
21. Pengingat pembayaran WhatsApp pada menit ke-30 dan ke-50 dengan pencatatan
    status kirim (Phase 14).

### Di luar cakupan atau belum dikonfirmasi

Payment gateway otomatis, VA unik per pesanan, pembelian beberapa tiket
sekaligus, refund otomatis, voucher/diskon, pajak/biaya layanan, akun peserta,
integrasi akuntansi, transfer tiket, ekspor CSV/Excel, reset check-in, dan
adapter provider WhatsApp nyata (yang tersedia baru driver `log`). Detail
rekening/VA/QRIS resmi juga belum ditetapkan (§15).

## 6. Asumsi dan Keputusan yang Telah Ditetapkan

  -----------------------------------------------------------------------
  Topik                               Keputusan
  ----------------------------------- -----------------------------------
  Produk                              Almira Tiket Event

  Penggunaan                          Web app internal untuk operasional
                                      event Almira

  Pengelola                           Satu admin internal pada MVP

  Stack                               Laravel + MySQL

  UI                                  Laravel Blade; JavaScript/Alpine.js
                                      bila dibutuhkan

  Pesanan                             Satu tiket per pesanan

  Data peserta                        Nama, nomor WhatsApp, email

  Harga                               Ditetapkan per event

  Harga awal rujukan                  Rp200.000; bukan harga tetap semua
                                      event

  Kuota awal rujukan                  200 tiket; bukan kuota tetap semua
                                      event

  Pembayaran                          Manual

  Bukti pembayaran                    Wajib diunggah

  Verifikasi                          Manual oleh admin

  Tenggat                             60 menit sejak pesanan dibuat

  Kuota saat pesanan dibuat           Satu slot ditahan

  Bukti diterima tepat waktu          Status menunggu verifikasi; kuota
                                      tetap ditahan

  Tidak ada bukti sampai tenggat      Pesanan kedaluwarsa dan kuota
                                      dilepas

  Poster                              Dikelola admin per event

  Tiket                               QR/token unik; diterbitkan setelah
                                      pembayaran disetujui

  Email                               E-ticket dikirim melalui email jika
                                      layanan telah dikonfigurasi

  Virtual Account                     Transfer manual ke rekening atau VA
                                      yang memang tersedia bagi bisnis;
                                      bukan asumsi VA unik otomatis
  Hak akses admin                     Role & permission per tindakan; satu role
                                      per akun. Super Admin permanen dan
                                      satu-satunya yang membuka audit log.

  Katalog metode pembayaran           Disimpan di tabel `payment_methods`,
                                      diisi dari konfigurasi saat seeder
                                      pertama dijalankan, lalu dikelola admin
                                      lewat dashboard.

  Data contoh pembayaran              Metode bertanda `is_dummy` hanya muncul
                                      di local/testing; di production metode
                                      itu tidak ditampilkan sampai detail
                                      resminya diisi dan tanda contoh dihapus
                                      Super Admin.

  Aktivasi metode per event           Detail metode global; aktif/nonaktif
                                      dapat di-override per event. Tanpa
                                      override, event mengikuti status global.

  Snapshot instruksi pembayaran       Pesanan menyimpan instruksi pembayaran
                                      saat dibuat, sehingga perubahan
                                      konfigurasi hanya memengaruhi pesanan
                                      baru.

  E-ticket PDF                        Dirender per permintaan dari database,
                                      tidak disimpan; tidak ada dependency PDF
                                      baru.

  Email e-ticket                      Nonaktif secara default
                                      (`TICKET_EMAIL_ENABLED=false`); layanan
                                      email produksi belum ditetapkan.

  Pengingat WhatsApp                  Berjalan lewat scheduler tiap menit;
                                      driver default `log`, provider nyata
                                      belum dipilih.

  Audit log                           Read-only; hanya Super Admin. Tidak ada
                                      permission yang bisa diberikan ke role
                                      lain untuk membacanya.

  Zona waktu operasional              `APP_TIMEZONE=Asia/Jakarta` pada
                                      `.env.example`; konfirmasi resmi masih
                                      menunggu bisnis (§15).
  -----------------------------------------------------------------------

## 7. Alur Utama

### 7.1 Peserta memesan

1.  Peserta membuka daftar event dan memilih event yang dipublikasikan.
2.  Sistem menampilkan detail event. Poster berada di bagian paling atas
    halaman pemesanan.
3.  Peserta mengisi nama, nomor WhatsApp, dan email.
4.  Sistem memvalidasi data serta ketersediaan kuota.
5.  Sistem membuat pesanan satu tiket, menyimpan snapshot harga, waktu
    pembuatan, dan tenggat 60 menit; satu slot kuota ditahan.
6.  Sistem menampilkan referensi pesanan dan instruksi pembayaran.
7.  Peserta melakukan transfer dan mengunggah bukti sebelum tenggat.
8.  Bukti yang diterima tepat waktu membuat status menjadi
    `PENDING_VERIFICATION`; kuota tetap ditahan.
9.  Admin memeriksa bukti.
10. Jika disetujui, sistem menerbitkan tiket dan peserta dapat
    mengaksesnya. Email dikirim jika layanan email aktif.

### 7.2 Verifikasi admin

1.  Admin login dan membuka antrean verifikasi.
2.  Admin memeriksa pesanan, nominal, metode, dan bukti.
3.  Admin menyetujui atau menolak pembayaran.
4.  Sistem mencatat keputusan, waktu, admin yang bertindak, serta alasan
    jika ditolak.
5.  Persetujuan mengubah status menjadi `PAID` dan menerbitkan paling
    banyak satu tiket.
6.  Penolakan tidak menerbitkan tiket. Perlakuan kuota dan kesempatan
    unggah ulang setelah penolakan masih perlu dikonfirmasi.

### 7.3 Kedaluwarsa

1.  Sistem menetapkan `expires_at = created_at + 60 menit`.
2.  Jika tenggat terlewati tanpa bukti yang diterima, status menjadi
    `EXPIRED` dan kuota dilepas.
3.  Jika bukti diterima sebelum tenggat, pesanan masuk
    `PENDING_VERIFICATION` dan tidak otomatis kedaluwarsa setelah menit
    ke-60.
4.  Proses harus aman terhadap permintaan bersamaan di sekitar batas
    tenggat.

### 7.4 Check-in

1.  Admin/petugas membuka scanner web dan memberi izin kamera.
2.  Sistem memvalidasi QR di sisi server.
3.  QR valid yang belum digunakan menghasilkan check-in dan pencatatan
    waktu/petugas.
4.  QR tidak valid atau sudah digunakan ditolak.
5.  Kebijakan reset/pembatalan check-in perlu ditentukan.

### 7.5 Admin mengundang admin lain (Phase 11)

1.  Admin dengan permission `admin-accounts.manage` membuka **Akun Admin** dan
    mengisi nama, email, serta role penerima.
2.  Sistem membuat akun berstatus `invited` **tanpa password** dan mengirim
    tautan undangan berisi token sekali pakai yang berlaku 24 jam.
3.  Token disimpan sebagai hash; tautan asli hanya ada di email. Menerbitkan
    ulang undangan mencabut tautan lama.
4.  Penerima membuka tautan dan menetapkan password sendiri; akun menjadi
    `active`.
5.  Menonaktifkan akun menghapus sesinya, sehingga request berikutnya ditolak.
    Aktivasi ulang mengembalikan akses tanpa mengubah data historis.
6.  Pengaman: admin tidak dapat menonaktifkan akun sendiri, dan hanya Super
    Admin yang dapat mengundang atau menugaskan akun Super Admin.

### 7.6 Admin mengelola metode pembayaran (Phase 12 & 12B)

1.  Admin dengan permission terkait membuka **Pengaturan → Metode Pembayaran**.
2.  Menambah metode: nama, kunci mesin (hanya saat pembuatan, karena dipakai
    `payments.method` dan snapshot pesanan), tipe, detail penerima, dan langkah
    pembayaran. Tipe menentukan detail mana yang wajib diisi; kunci yang masih
    dirujuk riwayat ditolak.
3.  Mengubah detail, mengaktifkan/menonaktifkan, atau menghapus. Hapus ditolak
    bila metode masih dirujuk pembayaran atau snapshot pesanan mana pun.
4.  Menandai detail sebagai data resmi (menghapus `is_dummy`) hanya dapat
    dilakukan Super Admin; kontrolnya tidak dirender untuk role lain dan
    pemeriksaannya tetap berjalan di server.
5.  Membuka **Event → Metode Pembayaran** untuk mematikan metode pada satu event
    saja, atau menekan **Ikuti Global** untuk membatalkan pengecualian itu.
6.  Setiap perubahan menulis audit log. Pesanan yang sudah dibuat tidak
    terpengaruh karena instruksinya tersimpan sebagai snapshot.

## 8. Persyaratan Fungsional

### FR-01 --- Autentikasi

-   Admin harus login untuk mengakses panel admin.
-   Terapkan autentikasi, otorisasi, validasi server-side, dan proteksi
    CSRF.
-   Password harus di-hash menggunakan mekanisme framework.

### FR-02 --- Pengelolaan event

-   Admin dapat membuat, melihat, mengubah, memublikasikan, dan
    menonaktifkan event.
-   Harga dan kuota dapat berbeda per event.
-   Event yang belum dipublikasikan tidak dapat dipesan.
-   Pesanan menyimpan snapshot harga agar perubahan harga event tidak
    mengubah transaksi lama.
-   Perubahan event setelah penjualan dimulai tidak boleh merusak
    pesanan/tiket yang sudah ada.

### FR-03 --- Poster

-   Admin dapat mengunggah dan mengganti poster per event.
-   Poster tampil di bagian paling atas halaman pemesanan.
-   Validasi tipe dan ukuran file wajib diterapkan.
-   Poster disimpan dengan nama/lokasi yang tidak menimpa file event
    lain.
-   Perubahan poster tidak mengubah pesanan yang sudah ada.
-   Format, batas ukuran, rasio, dan apakah poster wajib diisi masih
    perlu dikonfirmasi.

### FR-04 --- Pemesanan

-   Satu pesanan hanya memuat satu tiket.
-   Data wajib: nama, nomor WhatsApp, email.
-   Sistem membuat referensi pesanan unik dan menyimpan event, data
    peserta, snapshot harga, status, `created_at`, dan `expires_at`.
-   Sistem menolak pesanan ketika kuota tidak tersedia.
-   Akses peserta ke status/tiket harus aman dan tidak membocorkan data
    peserta lain.

### FR-05 --- Instruksi pembayaran

-   Sistem menampilkan instruksi berdasarkan metode pembayaran yang
    tersedia.
-   Metode yang pernah dipertimbangkan: DANA, OVO, GoPay, ShopeePay,
    transfer/VA BSI, transfer/VA Mandiri, dan QRIS.
-   Daftar final metode aktif belum dikonfirmasi.
-   Detail rekening, VA, dan QR merchant harus berasal dari data resmi
    bisnis.
-   Instruksi disimpan sebagai konfigurasi, bukan tersebar di kode.
-   Katalog metode dikelola admin dari dashboard (tambah, ubah, aktif/nonaktif,
    hapus aman) dan tersimpan di database; berkas konfigurasi hanya dipakai
    untuk mengisi katalog awal.
-   Peserta hanya ditawari metode yang **aktif**, **detailnya lengkap**, dan
    tidak dimatikan untuk event tersebut. Metode bertanda data contoh tidak
    pernah ditawarkan di production.
-   Aktivasi dapat diatur per event tanpa menduplikasi detail rekening.
-   Sistem tidak menganggap pembayaran berhasil hanya karena bukti
    diunggah.
-   VA unik per pesanan tidak termasuk MVP kecuali fasilitas penyedia
    telah dikonfirmasi.

### FR-06 --- Bukti pembayaran

-   Bukti wajib diunggah untuk masuk antrean verifikasi.
-   Sistem memvalidasi file, ukuran, dan tipe yang diizinkan.
-   Bukti disimpan privat atau diakses melalui otorisasi.
-   Peserta tidak dapat melihat bukti milik peserta lain.
-   Waktu penerimaan bukti dicatat.
-   Bukti harus diterima sebelum `expires_at` agar status masuk
    `PENDING_VERIFICATION`.
-   Kebijakan unggah ulang setelah penolakan belum ditetapkan.

### FR-07 --- Verifikasi

-   Admin dapat melihat antrean verifikasi dan detail bukti.
-   Admin dapat menyetujui atau menolak.
-   Sistem mencatat status, waktu, admin, dan alasan penolakan jika ada.
-   Keputusan bersamaan pada satu pembayaran harus ditangani dengan
    aman.
-   Persetujuan menghasilkan paling banyak satu tiket.
-   Penolakan tidak menerbitkan tiket.
-   Aturan pelepasan kuota dan pengajuan ulang setelah penolakan masih
    perlu dikonfirmasi.

### FR-08 --- Tenggat dan kuota

-   Tenggat adalah 60 menit sejak pesanan dibuat.
-   Satu slot kuota ditahan saat pesanan berhasil dibuat.
-   Jika bukti belum diterima saat tenggat, status menjadi `EXPIRED` dan
    slot dilepas.
-   Jika bukti diterima sebelum tenggat, status menjadi
    `PENDING_VERIFICATION` dan slot tetap ditahan sampai admin
    menyetujui atau menolak.
-   Tenggat tidak diperpanjang secara implisit.
-   Alokasi kuota, unggah bukti, dan kedaluwarsa harus aman terhadap
    permintaan bersamaan.
-   Kuota tidak boleh terjual melebihi kapasitas.
-   Pesanan yang telah disetujui tidak boleh kehilangan kuota akibat
    proses kedaluwarsa.

### FR-09 --- E-ticket

-   Tiket hanya diterbitkan setelah pembayaran disetujui.
-   Satu pesanan menghasilkan paling banyak satu tiket.
-   Setiap tiket mempunyai token QR unik dan sulit ditebak.
-   QR tidak boleh memuat data pribadi secara langsung jika tidak
    diperlukan.
-   Peserta dapat melihat/mengunduh tiket melalui akses terlindungi.
-   Tiket dapat diunduh sebagai PDF yang memuat detail event, kode pesanan,
    nama peserta, waktu, lokasi, status tiket, dan QR berisi token tiket yang
    sama dengan yang divalidasi scanner. QR tidak memuat data pribadi.
-   Email dikirim jika layanan email sudah dikonfigurasi.
-   Kegagalan email tidak membatalkan tiket yang sudah sah; perlu
    mekanisme pencatatan dan pengiriman ulang.
-   Pengiriman ulang memakai tiket dan QR yang sudah ada, sehingga tidak pernah
    membuat tiket kedua, dan setiap percobaan pengiriman tercatat.

### FR-10 --- Check-in

-   Sistem menyediakan scanner berbasis web yang menggunakan kamera
    perangkat jika didukung.
-   Token divalidasi di server.
-   Tiket hanya dapat check-in satu kali dalam kondisi normal.
-   Check-in mencatat tiket, waktu, dan petugas.
-   QR tidak valid atau sudah digunakan ditolak.
-   Alternatif pencarian/manual check-in untuk perangkat tanpa kamera
    perlu diputuskan.
-   Reset check-in belum ditetapkan.

### FR-11 --- Dashboard dan laporan

-   Dashboard menampilkan ringkasan event, pesanan, status pembayaran,
    tiket terbit, kuota tersisa, dan check-in.
-   Admin dapat mencari/memfilter pesanan berdasarkan event, referensi,
    data peserta, status, atau rentang waktu sesuai kebutuhan.
-   Ekspor CSV/Excel belum menjadi komitmen MVP kecuali dikonfirmasi.
-   Definisi metrik harus konsisten dengan status sistem.

### FR-12 --- Audit

-   Catat tindakan penting: persetujuan/penolakan pembayaran, penerbitan
    tiket, perubahan status, dan check-in.
-   Riwayat mencakup waktu dan pengguna yang bertindak jika tersedia.
-   Audit tidak dapat diedit bebas oleh peserta.
-   Audit log hanya dapat dibaca Super Admin, bersifat read-only, dan tidak
    menyediakan route membuat, mengubah, atau menghapus catatan.
-   Cakupan yang dicatat: verifikasi pembayaran, penerbitan tiket, check-in,
    kedaluwarsa pesanan, perubahan role/permission, perubahan akun admin, dan
    perubahan konfigurasi metode pembayaran.
-   Catatan audit tidak menyimpan password, token akses, atau OTP.

### FR-13 --- Role dan permission (Phase 10)

-   Admin dapat membuat role dan menetapkan permission per tindakan.
-   Satu akun admin memegang tepat satu role.
-   Role Super Admin permanen: tidak dapat dihapus, dan selalu memiliki seluruh
    permission.
-   Perubahan permission berlaku pada request berikutnya, tanpa perlu login
    ulang.
-   Setiap tindakan sensitif diperiksa di backend lewat policy/permission, bukan
    hanya disembunyikan di UI; request langsung tanpa izin harus ditolak.
-   Perubahan role dan permission dicatat di audit log.

### FR-14 --- Akun admin dan undangan (Phase 11)

-   Akun admin dibuat lewat undangan email; penerima menetapkan passwordnya
    sendiri.
-   Token undangan sekali pakai dan berlaku 24 jam; tautan lama dicabut saat
    undangan diterbitkan ulang.
-   Akun baru hanya dapat login setelah undangan diterima.
-   Akun dapat diaktifkan, dinonaktifkan, dan diaktifkan ulang.
-   Akun nonaktif kehilangan sesinya dan ditolak middleware admin.
-   Admin dapat mengubah nama dan password sendiri; perubahan password
    mewajibkan password saat ini.
-   Akun Super Admin terakhir tidak dapat dinonaktifkan.
-   Keberhasilan pengiriman email undangan tidak menentukan sah/tidaknya akun,
    tetapi setiap percobaan pengirimannya dicatat.

### FR-15 --- Pengelolaan metode pembayaran (Phase 12 & 12B)

-   Admin berizin dapat menambah, mengubah, mengaktifkan/menonaktifkan, dan
    menghapus metode pembayaran dari dashboard.
-   Tipe metode (transfer bank, virtual account, e-wallet, QRIS) menentukan
    detail mana yang wajib diisi dan apakah gambar QR diperlukan.
-   Kunci metode hanya diisi saat pembuatan dan tidak dapat diubah; kunci yang
    masih dirujuk riwayat pembayaran atau snapshot pesanan tidak dapat dipakai
    ulang.
-   Detail metode bersifat global. Yang bersifat per event hanya aktif/nonaktif,
    dengan tiga keadaan: mengikuti global, dipaksa aktif, atau dipaksa nonaktif.
    Pengecualian dapat dibatalkan kembali ke global.
-   Metode yang masih dirujuk pembayaran atau snapshot pesanan tidak dapat
    dihapus; alternatifnya adalah menonaktifkan.
-   Menghapus tanda data contoh (menyatakan detail sebagai data resmi) hanya
    dapat dilakukan Super Admin. Jika flag itu tidak dikirim pada request
    pembaruan, status data lama dipertahankan, bukan diturunkan.
-   Gambar QR merchant disimpan pada disk yang dapat dibaca publik karena
    peserta perlu memuatnya untuk memindai.

### FR-16 --- Email transaksional dan PDF e-ticket (Phase 13)

-   Setiap percobaan pengiriman email transaksional dicatat: penerima, status,
    pesan error, dan pelaku yang memicu pengiriman.
-   Status percobaan dibedakan menjadi terkirim, gagal, dan dilewati (email
    belum diaktifkan), agar instalasi tanpa layanan email tidak terlihat
    "gagal" terus-menerus.
-   PDF e-ticket dilampirkan pada email dan dapat diunduh peserta dari halaman
    tiket memakai kredensial yang sama.
-   Kirim ulang tiket tersedia bagi admin berizin dan tidak pernah membuat tiket
    atau QR baru.
-   Pengiriman bersifat sinkron pada MVP; retry otomatis, queue worker, dan
    batas jumlah percobaan belum ditetapkan.

### FR-17 --- Pengingat WhatsApp (Phase 14)

-   Pengingat dikirim pada menit ke-30 dan ke-50 untuk pesanan yang belum
    memiliki bukti pembayaran dan belum lewat tenggat.
-   Pengingat berhenti setelah bukti diunggah, setelah pesanan diputuskan, atau
    setelah pesanan kedaluwarsa.
-   Tautan pada pesan mengarah ke halaman status pesanan memakai token akses,
    bukan token QR.
-   Satu tahap pengingat hanya menghasilkan satu pesan; percobaan ulang dibatasi
    dan dicatat beserta statusnya.
-   Pengiriman berjalan lewat scheduler; jika scheduler mati, pengingat tidak
    terkirim tetapi status pesanan dan tenggat tetap benar.
-   Provider WhatsApp nyata belum ditetapkan; driver `log` disediakan untuk QA
    tanpa kredensial.

## 9. Status dan Transisi

### Status pesanan/pembayaran yang diusulkan

-   `PENDING_PAYMENT`: pesanan dibuat, menunggu pembayaran/bukti.
-   `PENDING_VERIFICATION`: bukti diterima tepat waktu, menunggu admin.
-   `PAID`: pembayaran disetujui.
-   `REJECTED`: pembayaran/bukti ditolak admin.
-   `EXPIRED`: tenggat berakhir sebelum bukti diterima.
-   `CANCELLED`: pesanan dibatalkan sesuai kebijakan yang akan
    ditetapkan.

Status pesanan dan pembayaran dapat dipisah pada desain database jika
diperlukan.

### Transisi inti

-   `PENDING_PAYMENT` → `PENDING_VERIFICATION` jika bukti diterima
    sebelum tenggat.
-   `PENDING_PAYMENT` → `EXPIRED` jika tenggat lewat tanpa bukti.
-   `PENDING_VERIFICATION` → `PAID` jika disetujui admin.
-   `PENDING_VERIFICATION` → `REJECTED` jika ditolak admin.
-   Transisi dari `REJECTED` belum ditentukan.
-   `EXPIRED` tidak boleh otomatis menjadi `PAID` tanpa prosedur
    pembayaran terlambat yang disepakati.

### Status tiket

-   `VALID`: tiket terbit dan belum digunakan.
-   `CHECKED_IN`: tiket sudah digunakan untuk check-in.
-   `VOID`: tiket dinonaktifkan sesuai kebijakan.

### Catatan implementasi

-   Status pesanan dan status pembayaran disimpan pada **satu kolom**
    (`orders.status`) karena satu pesanan membawa tepat satu tiket dan satu
    pembayaran, sehingga status pembayaran tidak pernah bergerak sendiri.
    Pemisahan kolom dapat dilakukan kemudian tanpa mengubah aturan di atas.
-   `CANCELLED` ada di daftar status tetapi belum dipakai alur mana pun:
    kebijakan pembatalan/refund belum ditetapkan (§15).
-   Tidak ada transisi otomatis dari `REJECTED` maupun `EXPIRED` menuju `PAID`.
    Kebijakan unggah ulang bukti setelah penolakan belum ditetapkan (§15).
-   `VOID` tersedia sebagai status tiket, tetapi belum ada alur yang
    menetapkannya karena reset/pembatalan check-in belum ditetapkan (§15).
-   Nama status di kode sama dengan nama di dokumen ini
    (`PENDING_PAYMENT`, `PENDING_VERIFICATION`, `PAID`, `REJECTED`, `EXPIRED`,
    `CANCELLED`; tiket `VALID`, `CHECKED_IN`, `VOID`).

## 10. Aturan Bisnis

1.  Setiap event dapat memiliki harga dan kuota berbeda.
2.  Satu pesanan hanya berisi satu tiket.
3.  Data peserta wajib terdiri dari nama, WhatsApp, dan email.
4.  Satu slot kuota ditahan ketika pesanan berhasil dibuat.
5.  Batas pembayaran adalah 60 menit sejak pembuatan pesanan.
6.  Pesanan tanpa bukti yang diterima sebelum tenggat menjadi `EXPIRED`
    dan slot dilepas.
7.  Bukti yang diterima tepat waktu membuat pesanan menunggu verifikasi;
    slot tetap ditahan hingga admin menyetujui atau menolak.
8.  Bukti unggahan bukan konfirmasi final bahwa dana telah diterima.
9.  Tiket tidak diterbitkan sebelum pembayaran disetujui.
10. Satu pesanan menghasilkan paling banyak satu tiket.
11. Satu token tiket hanya boleh dipakai untuk satu check-in sah.
12. Snapshot harga pesanan tidak berubah ketika harga event diperbarui.
13. Bukti pembayaran dan data pribadi dilindungi dari akses tanpa izin.
14. Pembayaran yang masuk setelah pesanan kedaluwarsa harus ditangani
    melalui prosedur rekonsiliasi yang ditetapkan.
15. Sistem mencegah dua permintaan bersamaan mengambil slot kuota
    terakhir.

## 11. Persyaratan Nonfungsional

### Keamanan

-   Gunakan autentikasi, otorisasi, CSRF protection, validasi
    server-side, dan escaping output.
-   Password di-hash menggunakan framework.
-   Bukti pembayaran disimpan privat.
-   Token QR acak dan sulit ditebak.
-   Jangan mengekspos data pribadi pada URL/QR jika tidak diperlukan.
-   Batasi ukuran dan tipe upload.
-   Lindungi halaman status pesanan dan tiket.

### Integritas data

-   Gunakan transaksi database dan penguncian yang sesuai untuk alokasi
    kuota dan perubahan status kritis.
-   Gunakan constraint/indeks untuk referensi dan token unik.
-   Cegah tiket ganda melalui idempotensi.
-   Proses kedaluwarsa harus aman dijalankan berulang dan tidak melepas
    kuota dua kali.
-   Gunakan acuan waktu yang konsisten dan zona waktu operasional yang
    dikonfirmasi.

### Kinerja dan keandalan

-   UI responsif pada desktop dan mobile.
-   Mendukung browser modern yang ditentukan saat implementasi.
-   Target angka kinerja, volume pengguna bersamaan, dan ketersediaan
    belum ditentukan.
-   Scanner memerlukan izin kamera dan dukungan perangkat/browser.
-   Sediakan backup database dan prosedur pemulihan.
-   Kegagalan email tidak membatalkan tiket sah.
-   Proses kedaluwarsa dapat dijalankan terjadwal menggunakan
    scheduler/queue Laravel bila diperlukan.

## 12. Entitas Data Konseptual

Ini rancangan awal, bukan skema database final.

### `users`

-   `id`, `name`, `email`, `password`, role/otorisasi, timestamps.

### `events`

-   `id`, `name`, `slug`, `description`, `poster_path`, `ticket_price`,
    `quota`, `status`, informasi tanggal/waktu/lokasi sesuai kebutuhan,
    timestamps.

### `orders`

-   `id`, `order_reference`, `event_id`, `participant_name`,
    `participant_whatsapp`, `participant_email`,
    `ticket_price_snapshot`, `status`, `created_at`, `expires_at`,
    `access_token` (token halaman status peserta),
    `payment_instructions_snapshot` (konfigurasi pembayaran saat pesanan
    dibuat), kolom hasil verifikasi (pelaku, waktu, alasan penolakan),
    timestamps.

### `payments`

-   `id`, `order_id`, `method`, `amount`, `proof_path`, `status`,
    `submitted_at`, `reviewed_by`, `reviewed_at`, `rejection_reason`,
    timestamps.

### `tickets`

-   `id`, `order_id`, `qr_token`, `status`, `issued_at`, timestamps.

### `check_ins`

-   `id`, `ticket_id`, `checked_in_by`, `checked_in_at`, timestamps.

### `roles`, `permissions`, `permission_role`

-   `roles`: `id`, `name`, `description`, `is_super_admin`, timestamps.
-   `permissions`: `id`, `name` (kunci mesin), `label`, `group_name`,
    `description`, timestamps.
-   `permission_role`: pasangan `role_id` + `permission_id` (kunci primer
    gabungan).

### `admin_invitations`

-   `id`, `user_id`, `email`, `role_id`, `token_hash` (SHA-256, unik),
    `expires_at`, `accepted_at`, `revoked_at`, `invited_by`, timestamps.

### `payment_methods`

-   `id`, `key` (unik, hanya diisi saat pembuatan), `label`, `type`
    (`bank_transfer`, `virtual_account`, `ewallet`, `qris`), `is_active`,
    `is_dummy`, `account` (JSON baris detail penerima), `steps` (JSON langkah
    pembayaran), `qr_image_path`, `sort_order`, `updated_by`, timestamps.

### `event_payment_methods`

-   `id`, `event_id`, `payment_method_id`, `is_active`, `updated_by`,
    timestamps. Unik per pasangan event–metode. Tidak adanya baris berarti
    event mengikuti status global.

### `email_deliveries`

-   `id`, `emailable_type`/`emailable_id` (morph), `recipient_email`, `status`,
    `error_message`, `sent_by`, `created_at`. Satu baris per percobaan
    pengiriman (append-only).

### `whatsapp_deliveries`

-   `id`, `order_id`, `kind` (tahap pengingat), `attempts`, `status`, `driver`,
    `error_message`, `sent_at`, timestamps. Unik per `(order_id, kind)`.

Tabel `users`, `events`, `orders`, `payments`, `tickets`, `check_ins`, dan
`audit_logs` juga bertambah kolom setelah rancangan awal (mis. `users.status`,
`users.role_id`, `orders.payment_instructions_snapshot`). **Skema yang berlaku
adalah kode migrasi di `database/migrations`, bukan daftar di dokumen ini.**

Data rekening/VA dan QR merchant diisi setelah informasi resmi tersedia.

## 13. Kriteria Penerimaan MVP

> **Cara membaca:** kotak di bawah sengaja **tidak dicentang** di dokumen ini.
> Penandaan hanya dilakukan pemilik bisnis setelah QA manual — lihat
> `IMPLEMENTATION_PLAN.md` §21 dan checklist QA per fase di
> `docs/OPERATIONS.md` §7–§11. Status implementasi per area ada di §1.2.

### Event

-   [ ] Admin dapat membuat event dengan harga dan kuota sendiri.
-   [ ] Admin dapat mengunggah poster; poster tampil di bagian atas
    halaman pemesanan.
-   [ ] Event draft/tidak aktif tidak dapat dipesan.

### Pemesanan dan kuota

-   [ ] Peserta dapat membuat pesanan dengan nama, WhatsApp, dan email.
-   [ ] Satu pesanan menahan satu slot.
-   [ ] Harga pesanan tetap sama jika harga event berubah.
-   [ ] Kuota tidak negatif atau melebihi kapasitas.
-   [ ] Pesanan gagal dibuat jika kuota habis.

### Tenggat dan bukti

-   [ ] Sistem menetapkan tenggat 60 menit sejak pesanan dibuat.
-   [ ] Pesanan tanpa bukti saat tenggat menjadi `EXPIRED` dan kuota
    dilepas.
-   [ ] Bukti yang diterima sebelum tenggat mengubah status menjadi
    `PENDING_VERIFICATION`.
-   [ ] Pesanan `PENDING_VERIFICATION` tetap menahan kuota setelah menit
    ke-60.
-   [ ] Bukti tidak dapat diakses oleh pihak yang tidak berwenang.

### Verifikasi dan tiket

-   [ ] Admin dapat menyetujui/menolak bukti.
-   [ ] Tindakan admin tercatat.
-   [ ] Persetujuan menerbitkan satu tiket dengan QR unik.
-   [ ] Penolakan tidak menerbitkan tiket.
-   [ ] Pemrosesan ulang tidak membuat tiket duplikat.

### Check-in

-   [ ] QR valid dan belum digunakan dapat check-in.
-   [ ] QR tidak valid ditolak.
-   [ ] QR yang sudah digunakan tidak dapat check-in kembali secara
    normal.
-   [ ] Sistem mencatat waktu dan petugas check-in.

### Hak akses dan akun admin (Phase 10–11)

-   [ ] Menu dan tindakan mengikuti permission terbaru tanpa perlu login ulang.
-   [ ] Request langsung tanpa izin ditolak di backend (403), bukan hanya
    disembunyikan di UI.
-   [ ] Role Super Admin tidak dapat dihapus atau kehilangan akses.
-   [ ] Akun hasil undangan tidak dapat login sebelum undangan diterima.
-   [ ] Token undangan kedaluwarsa dalam 24 jam dan tidak dapat dipakai dua kali.
-   [ ] Akun nonaktif langsung kehilangan akses dan sesinya dicabut.
-   [ ] Super Admin terakhir tidak dapat dinonaktifkan.
-   [ ] Perubahan role, permission, dan akun tercatat di audit log.

### Metode pembayaran (Phase 12–12B)

-   [ ] Metode baru dapat ditambahkan dari dashboard dan tersimpan nonaktif.
-   [ ] Detail wajib mengikuti tipe: VA tanpa nomor ditolak, QRIS tanpa gambar
    atau ID merchant ditolak.
-   [ ] Kunci metode duplikat ditolak; kunci yang masih dirujuk riwayat tidak
    dapat dipakai ulang.
-   [ ] Metode dengan detail belum lengkap tidak ditawarkan ke peserta, dan
    alasannya terlihat di layar admin.
-   [ ] Menonaktifkan metode untuk satu event tidak mengubah event lain.
-   [ ] "Ikuti Global" menghapus pengecualian dan mengembalikan status global.
-   [ ] Pesanan lama tetap menampilkan instruksi dari snapshot setelah katalog
    diubah.
-   [ ] Metode yang sudah dipakai pesanan tidak dapat dihapus; hanya dapat
    dinonaktifkan.
-   [ ] Hanya Super Admin dapat menandai detail sebagai data resmi, dan kontrol
    itu tidak tersedia bagi role lain.
-   [ ] Metode bertanda data contoh tidak pernah tampil di production.

### Email dan PDF e-ticket (Phase 13)

-   [ ] PDF memuat QR dengan token yang sama dengan yang divalidasi scanner.
-   [ ] Kirim ulang tiket tidak membuat tiket atau QR baru.
-   [ ] Kegagalan email tidak membatalkan tiket yang sudah sah.
-   [ ] Setiap percobaan pengiriman tercatat sebagai terkirim, gagal, atau
    dilewati.

### Pengingat WhatsApp (Phase 14)

-   [ ] Pengingat hanya dikirim untuk pesanan tanpa bukti yang belum lewat
    tenggat.
-   [ ] Satu tahap pengingat tidak dikirim dua kali.
-   [ ] Pengingat berhenti setelah bukti diunggah atau pesanan diputuskan.
-   [ ] Tautan pada pesan memakai token akses pesanan, bukan token QR.

## 14. Risiko dan Mitigasi

  -----------------------------------------------------------------------
  Risiko                              Mitigasi
  ----------------------------------- -----------------------------------
  Bukti palsu atau transfer tidak     Verifikasi admin terhadap transaksi
  masuk                               sesuai prosedur operasional

  Kuota tertahan terlalu lama         Tenggat 60 menit untuk pesanan
                                      tanpa bukti; aturan antrean
                                      verifikasi eksplisit

  Kuota terjual melebihi kapasitas    Transaksi database dan penguncian
                                      saat alokasi

  Bukti dikirim tepat di batas        Gunakan waktu server dan tangani
  tenggat                             kondisi bersamaan

  Pembayaran masuk setelah            Prosedur rekonsiliasi manual
  kedaluwarsa                         

  Salah keputusan admin               Tampilkan detail transaksi, simpan
                                      audit trail, dan alasan penolakan

  Tiket ganda                         Penerbitan idempoten dan constraint
                                      unik

  QR digunakan berulang               Validasi server dan pencatatan
                                      check-in

  Bukti bocor                         Penyimpanan privat dan kontrol
                                      akses

  Email gagal                         Pencatatan status dan mekanisme
                                      kirim ulang

  Instruksi VA tidak sesuai fasilitas Konfirmasi data resmi sebelum
                                      metode diaktifkan
  -----------------------------------------------------------------------

## 15. Pertanyaan Terbuka

Nomor pertanyaan **dipertahankan** karena kode dan `docs/OPERATIONS.md` sudah
merujuk nomornya (mis. "PRD section 15 pertanyaan 16"). Pertanyaan yang sudah
dijawab tidak dihapus, melainkan dipindahkan ke daftar "sudah terjawab" beserta
keputusannya.

Aturan menutup pertanyaan: ubah statusnya di bagian ini **dan** perbarui
bagian PRD yang terkait, konfigurasi, serta `docs/OPERATIONS.md` pada perubahan
yang sama. Menutup pertanyaan hanya di kode membuat dokumen ini berbohong.

### Sudah terjawab

-   **16. Metode pembayaran global atau per event?** Terjawab pada Phase 12B:
    **detail metode global**, **aktivasi per event**. Disimpan di
    `event_payment_methods`; tanpa baris override, event mengikuti status
    global. Kunci `scope` di `config/payments.php` dipertahankan hanya agar
    pertanyaan ini tetap terlacak dan tidak dibaca kode lagi.

### Terjawab sebagian (mekanisme ada, keputusan bisnis belum)

-   **1. Metode mana yang aktif saat peluncuran?** Katalog kini dikelola lewat
    dashboard, lengkap dengan aktif/nonaktif global dan per event. Daftar
    metode final dan mana yang aktif saat rilis tetap belum diputuskan.
-   **2. Detail rekening/VA/QR merchant apa yang dipakai?** Formulir dan
    validasi per tipe sudah tersedia; angka resminya belum ada. Metode bertanda
    data contoh tidak pernah ditampilkan di production sampai Super Admin
    mengisi detail resmi dan menghapus tanda tersebut.
-   **6. Bagaimana peserta mengakses status pesanan dengan aman?** Saat ini
    memakai token akses tak terduga di URL. Apakah itu cukup, atau perlu
    verifikasi tambahan (mis. OTP/email), belum diputuskan.
-   **7. Layanan email apa untuk e-ticket?** Pengiriman, pencatatan status, PDF,
    dan kirim ulang sudah ada; provider belum dipilih sehingga
    `TICKET_EMAIL_ENABLED=false` secara default.
-   **10. Format dan ukuran maksimal bukti pembayaran.** Dipakai asumsi kerja
    JPG/JPEG/PNG/WebP maksimum 4 MB di disk privat (PDF sengaja ditolak).
    Nilainya masih dapat berubah lewat konfigurasi, bukan tersebar di kode.

### Masih terbuka — prioritas tinggi

-   **1, 2** (lihat "terjawab sebagian"): daftar metode final dan detail resmi.
-   **3. Bolehkah peserta mengunggah bukti baru setelah ditolak?** Saat ini
    belum tersedia; `REJECTED` bersifat final sampai kebijakan ditetapkan.
-   **4. Jika boleh unggah ulang, apakah tenggat baru berlaku dan bagaimana
    kuota diperlakukan?** Belum diputuskan; belum ada kode yang mengasumsikan
    jawabannya.
-   **5. Bagaimana menangani transfer yang masuk setelah pesanan `EXPIRED`?**
    Belum diputuskan. Sistem tidak pernah mengubah `EXPIRED` menjadi `PAID`
    secara otomatis.
-   **6, 7** (lihat "terjawab sebagian"): perlu keputusan akhir.

### Masih terbuka — prioritas menengah

-   **8. Informasi event mana yang wajib** (tanggal, waktu, lokasi, deskripsi)?
-   **9. Format, ukuran maksimal, dan dimensi poster.**
-   **11. Bolehkah admin mengubah kuota setelah penjualan dimulai?**
-   **12. Apakah pembatalan/refund diperbolehkan?** Status `CANCELLED` sudah ada
    tetapi belum dipakai alur mana pun.
-   **13. Apakah ekspor CSV/Excel diperlukan?** Belum menjadi komitmen MVP.
-   **14. Bolehkah admin membatalkan atau mereset check-in?** Belum; sengaja
    tidak diimplementasikan.
-   **15. Bolehkah peserta membuat pesanan baru dengan email/WhatsApp sama?**

### Pertanyaan baru dari Phase 10–14

-   **17. Provider WhatsApp mana yang dipakai**, dan bagaimana kebijakan opt-out
    peserta? Saat ini hanya driver `log`.
-   **18. Apakah retry email/WhatsApp perlu otomatis lewat queue**, dan berapa
    batas percobaannya? Retry e-ticket masih manual; pengingat WhatsApp dibatasi
    `WHATSAPP_MAX_ATTEMPTS` dan tidak punya tombol kirim ulang.
-   **19. Di mana berkas produksi disimpan** — `POSTER_DISK`,
    `PAYMENT_PROOF_DISK`, `PAYMENT_QR_DISK` lokal atau object storage? Backup
    hanya benar bila disk-nya persisten (`docs/OPERATIONS.md` §2).
-   **20. Zona waktu operasional** yang mengikat tenggat 60 menit. `.env.example`
    memakai `Asia/Jakarta`, tetapi keputusan resminya belum ada.
-   **21. Apakah pemisahan tugas finance/operasional diperlukan?** Role dan
    permission sudah tersedia; pembagian perannya belum ditetapkan.

## 16. Urutan Implementasi yang Disarankan

1.  Fondasi Laravel, MySQL, environment, autentikasi admin, dan backup.
2.  Event management: CRUD, harga, kuota, poster, publikasi.
3.  Pemesanan: data peserta, referensi, snapshot harga, reservasi kuota.
4.  Pembayaran manual: metode, instruksi, upload bukti privat.
5.  Tenggat 60 menit, proses kedaluwarsa, dan penanganan race condition.
6.  Verifikasi admin, keputusan pembayaran, dan audit trail.
7.  E-ticket, token QR, halaman unduh, dan email.
8.  Scanner check-in dan pencegahan penggunaan ganda.
9.  Dashboard, laporan, pengujian alur kritis, keamanan, dan integritas
    data.

Urutan di atas sudah dijalankan. Pemetaannya ke fase di
`IMPLEMENTATION_PLAN.md`:

| Langkah | Fase | Status |
| --- | --- | --- |
| 1 | Phase 01–02 | Terimplementasi |
| 2 | Phase 03 | Terimplementasi |
| 3 | Phase 04 | Terimplementasi |
| 4 | Phase 05 | Terimplementasi |
| 5 | Phase 06 | Terimplementasi |
| 6 | Phase 07 | Terimplementasi |
| 7 | Phase 07 & 13 | Terimplementasi |
| 8 | Phase 08 | Terimplementasi |
| 9 | Phase 09 (runbook) & 15 (QA akhir) | Runbook terimplementasi; QA akhir belum dimulai |

Pekerjaan lanjutan yang tidak ada di urutan awal tetapi sudah dikerjakan:
Phase 10 (role & permission), Phase 11 (akun & undangan admin), Phase 12/12B
(metode pembayaran & audit log), Phase 13 (email & PDF), Phase 14 (pengingat
WhatsApp). Urutan itu dipilih karena hak akses harus stabil sebelum layar
administratif baru ditambahkan.

## 17. Dokumen Lanjutan

PRD ini hanya menetapkan **kebutuhan dan aturan bisnis**. Rincian teknis tidak
diulang di sini karena sudah punya rumahnya masing-masing:

| Pertanyaan | Dokumen |
| --- | --- |
| "Fitur ini sudah jadi atau belum, dan apa acceptance criteria-nya?" | `IMPLEMENTATION_PLAN.md` §1.1 dan bagian fasenya |
| "Bagaimana men-deploy dan merawatnya?" | `docs/OPERATIONS.md` §1–§6 |
| "Bagaimana menguji fase tertentu secara manual?" | `docs/OPERATIONS.md` §7–§11 |
| "Apa saja keterbatasan yang diketahui?" | `docs/OPERATIONS.md` §12 |
| "Bagaimana men-deploy ke Vercel?" | `docs/DEPLOY_VERCEL.md` |
| "Warna, tipografi, komponen?" | `styling.md` |
| "Aturan kerja agen/developer?" | `AGENTS.md` |
| "Route, controller, model, migration, policy yang sebenarnya?" | kode di `app/`, `routes/`, `database/migrations`, `database/seeders` |

Mulai dari kode bila dokumen dan kode berbeda: kode adalah kenyataan, dokumen
adalah niat. Perbedaan yang ditemukan harus dilaporkan dan diperbaiki di
dokumen pada perubahan yang sama — jangan dibiarkan menjadi dua sumber
kebenaran.

Jika `styling.md` tersedia, gunakan sebagai sumber kebenaran visual. PRD ini
tidak menetapkan palet warna, tipografi, atau detail visual yang belum
diberikan.

------------------------------------------------------------------------

**Akhir dokumen --- PRD Almira Tiket Event v3.0 (revisi 27 Oktober 2026)**
