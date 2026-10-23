# Product Requirements Document (PRD)

## Almira Tiket Event

**Versi:** 2.0\
**Status:** Draft untuk validasi bisnis dan implementasi\
**Tanggal:** 21 Oktober 2026\
**Platform:** Web application\
**Stack:** Laravel + MySQL + Laravel Blade

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
tiket, melakukan check-in, serta melihat ringkasan operasional.

MVP mengasumsikan satu peran admin. Pemisahan admin dan finance belum
ditetapkan.

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

### Di luar cakupan atau belum dikonfirmasi

Payment gateway, VA unik per pesanan, pembelian beberapa tiket
sekaligus, refund otomatis, voucher/diskon, pajak/biaya layanan,
multi-role, WhatsApp API, integrasi akuntansi, dan transfer tiket.

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
-   Email dikirim jika layanan email sudah dikonfigurasi.
-   Kegagalan email tidak membatalkan tiket yang sudah sah; perlu
    mekanisme pencatatan dan pengiriman ulang.

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
    timestamps.

### `payments`

-   `id`, `order_id`, `method`, `amount`, `proof_path`, `status`,
    `submitted_at`, `reviewed_by`, `reviewed_at`, `rejection_reason`,
    timestamps.

### `tickets`

-   `id`, `order_id`, `qr_token`, `status`, `issued_at`, timestamps.

### `check_ins`

-   `id`, `ticket_id`, `checked_in_by`, `checked_in_at`, timestamps.

### `payment_methods`

-   `id`, `name`, `instructions`, detail penerima yang diperlukan,
    `is_active`, relasi ke event bila metode berbeda per event,
    timestamps.

Tabel audit log atau entitas lain dapat ditambahkan saat desain
implementasi. Data rekening/VA dan QR merchant diisi setelah informasi
resmi tersedia.

## 13. Kriteria Penerimaan MVP

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

### Prioritas tinggi

1.  Metode pembayaran mana yang benar-benar aktif saat peluncuran?
2.  Detail rekening, nomor VA yang tersedia, dan QR merchant apa yang
    digunakan?
3.  Jika pembayaran ditolak, apakah peserta boleh mengunggah bukti baru?
4.  Jika boleh unggah ulang, apakah tenggat baru berlaku dan bagaimana
    kuota diperlakukan?
5.  Bagaimana menangani transfer yang masuk setelah pesanan `EXPIRED`?
6.  Bagaimana peserta mengakses status pesanan dengan aman?
7.  Layanan email apa yang digunakan untuk mengirim e-ticket?

### Prioritas menengah

8.  Informasi event apa yang wajib: tanggal, waktu, lokasi, deskripsi,
    dan lainnya?
9.  Apa format, ukuran maksimal, dan dimensi poster?
10. Apa format dan ukuran maksimal bukti pembayaran?
11. Bolehkah admin mengubah kuota setelah penjualan dimulai?
12. Apakah pembatalan/refund diperbolehkan?
13. Apakah ekspor CSV/Excel diperlukan?
14. Bolehkah admin membatalkan atau mereset check-in?
15. Bolehkah peserta membuat pesanan baru dengan email/WhatsApp yang
    sama?
16. Apakah metode pembayaran ditentukan global atau per event?

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

## 17. Dokumen Lanjutan

PRD ini menjelaskan kebutuhan produk dan aturan bisnis. Dokumen
implementasi selanjutnya perlu merinci routes, middleware, controllers,
models, migrations, relasi, indeks, validasi, otorisasi, transaksi
database, scheduler, penyimpanan file, email, QR, UI states, responsive
behavior, dan strategi testing.

Jika `styling.md` tersedia, gunakan sebagai sumber kebenaran visual. PRD
ini tidak menetapkan palet warna, tipografi, atau detail visual yang
belum diberikan.

------------------------------------------------------------------------

**Akhir dokumen --- PRD Almira Tiket Event v2.0**
