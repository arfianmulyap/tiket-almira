# Operations Runbook — Almira Tiket Event

Dokumen operasional untuk Phase 09. Mencakup backup/restore, konfigurasi
sebelum rilis, dan pemeriksaan rutin.

Catatan: nilai konkret seperti host database, kredensial, dan lokasi
penyimpanan backup **belum ditetapkan bisnis**. Dokumen ini menetapkan
prosedur dan perintahnya, bukan mengarang target infrastruktur.

---

## 1. Scheduler (wajib berjalan)

Kedaluwarsa pesanan 60 menit dijalankan oleh `orders:expire-overdue`
(`routes/console.php`, setiap menit, `withoutOverlapping`). Jika scheduler
mati, pesanan yang melewati tenggat tidak ditandai `EXPIRED`.

Kuota tidak akan salah hitung — `Order::scopeHoldingQuota` juga memeriksa
`expires_at`, sehingga slot tetap dianggap bebas — tetapi daftar pesanan dan
status akan tertinggal dari kenyataan.

Pada server, satu cron entry sudah cukup:

```
* * * * * cd /path-ke-aplikasi && php artisan schedule:run >> /dev/null 2>&1
```

Dashboard admin menampilkan peringatan **"Pesanan melewati tenggat belum
diproses"** jika ada pesanan `PENDING_PAYMENT` yang sudah lewat tenggat. Angka
yang terus bertambah adalah indikasi scheduler tidak berjalan.

Sejak Phase 14, `whatsapp:send-payment-reminders` juga berjalan setiap menit
(`routes/console.php`). Jika scheduler mati, pengingat menit ke-30 dan ke-50
tidak dikirim; status pesanan dan tenggat tetap benar karena pengingat hanya
membaca status, bukan mengubahnya. Tidak ada pengingat yang dikirim ulang
setelah scheduler hidup kembali bila tenggatnya sudah lewat.

Verifikasi:

```sh
php artisan schedule:list
```

---

## 2. Backup

### Yang harus di-backup

1. **Database MySQL** — seluruh data operasional.
2. **`storage/app/private`** — bukti pembayaran. Tidak dapat dibuat ulang.
3. **`storage/app/public`** — poster event.
4. **`.env`** — simpan terpisah dan terenkripsi, jangan di repository.

Bukti pembayaran dan poster tidak ada di database. Backup database saja
**tidak cukup** untuk pemulihan penuh.

### Database

```sh
mysqldump --single-transaction --routines --triggers \
  -h "$DB_HOST" -u "$DB_USERNAME" -p "$DB_DATABASE" \
  > backup-$(date +%F-%H%M).sql
```

`--single-transaction` menjaga konsistensi antar tabel tanpa mengunci tulisan,
yang penting karena order, payment, ticket, dan check_ins saling terkait.

### File upload

```sh
tar -czf storage-$(date +%F-%H%M).tar.gz storage/app/private storage/app/public
```

### Restore

```sh
mysql -h "$DB_HOST" -u "$DB_USERNAME" -p "$DB_DATABASE" < backup-FILE.sql
tar -xzf storage-FILE.tar.gz
php artisan config:clear
```

**Jangan pernah** menjalankan `migrate:fresh`, `migrate:refresh`, atau
`db:wipe` pada database produksi.

### Belum ditetapkan

- Frekuensi backup dan retensi.
- Lokasi penyimpanan off-site.
- Siapa yang menguji restore secara berkala.

Backup yang belum pernah diuji restore belum bisa disebut backup.

---

## 3. Checklist konfigurasi sebelum rilis

Phase 09 mensyaratkan konfigurasi produksi tidak memakai credential
development.

| Key | Development | Produksi |
| --- | --- | --- |
| `APP_ENV` | `local` | `production` |
| `APP_DEBUG` | `true` | **`false`** |
| `APP_KEY` | apa saja | hasil `php artisan key:generate` |
| `APP_URL` | `http://localhost` | domain + HTTPS |
| `LOG_LEVEL` | `debug` | `warning` atau `error` |
| `DB_PASSWORD` | boleh kosong | password kuat |
| `ADMIN_PASSWORD` | lemah (mis. `123`) | **wajib diganti** |
| `SESSION_ENCRYPT` | `false` | pertimbangkan `true` |
| `TICKET_EMAIL_ENABLED` | `false` | `true` hanya jika `MAIL_*` nyata |
| `WHATSAPP_ENABLED` | `true` dengan driver `log` | `false` sampai adapter provider nyata terpasang |
| `WHATSAPP_DRIVER` | `log` | nama driver adapter produksi |
| `WHATSAPP_MAX_ATTEMPTS` | `3` | `3` atau sesuai kebijakan operasional |

### Peringatan: kredensial admin development

`ADMIN_EMAIL` / `ADMIN_PASSWORD` di `.env` lokal sengaja memakai password
lemah untuk kemudahan pengembangan. `LoginRequest` tidak memberlakukan
panjang minimum, sehingga password sependek `123` diterima.

Sebelum rilis:

1. Set `ADMIN_PASSWORD` ke password kuat.
2. `php artisan config:clear`
3. `php artisan db:seed --class=AdminUserSeeder --force`

Seeder memakai `firstOrNew(['email' => ...])` sehingga akun yang sama
diperbarui, tidak membuat duplikat.

Rate limiting pada login tetap aktif, namun tidak menolong jika password
hanya tiga digit.

### Perintah sebelum rilis

```sh
php artisan key:generate      # hanya sekali, saat setup awal
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link
```

Setelah mengubah `.env`, jalankan `php artisan config:clear` — nilai yang
sudah ter-cache tidak akan membaca `.env` lagi.

---

## 4. Pemeriksaan keamanan

- [ ] `APP_DEBUG=false`. Jika `true`, stack trace akan menampilkan isi `.env`.
- [ ] Bukti pembayaran hanya dapat diakses melalui
      `admin.orders.payment-proof` (disk privat, tanpa URL publik).
- [ ] `storage/app/private` tidak dapat diakses langsung dari web.
- [ ] Semua route admin berada di belakang middleware `auth` + `admin`.
- [ ] Token QR tidak pernah muncul di URL — check-in memakai POST body.
- [ ] HTTPS aktif; tanpa itu token akses pesanan terkirim sebagai teks biasa.

Halaman status pesanan dan tiket peserta dilindungi token tak terduga di URL,
bukan login, karena MVP tidak punya akun peserta. Konsekuensinya: siapa pun
yang memegang URL dapat melihat halaman itu. Mekanisme akses yang lebih kuat
masih **open decision** (PRD section 15 pertanyaan 6).

---

## 5. Logging

Default `stack` → `single` ke `storage/logs/laravel.log`.

Untuk produksi, pertimbangkan `LOG_STACK=daily` dengan `LOG_DAILY_DAYS` agar
log tidak tumbuh tanpa batas.

Audit tindakan penting (approve/reject, penerbitan tiket, check-in) disimpan
di tabel `audit_logs`, bukan di file log, dan dapat dilihat pada halaman
detail pesanan.

Kegagalan pengiriman email e-ticket dicatat sebagai log dan **tidak**
membatalkan tiket yang sudah sah (PRD FR-09).

---

## 6. Pemeriksaan rutin

Harian:
- Antrean verifikasi tidak menumpuk (dashboard).
- Peringatan "Pesanan melewati tenggat belum diproses" tidak muncul.

Sebelum hari acara:
- Scanner check-in diuji pada perangkat yang akan dipakai petugas.
- `BarcodeDetector` tidak tersedia di desktop Safari/Firefox — siapkan
  perangkat Android/Chrome, atau gunakan form token manual.
- Backup terbaru tersedia dan sudah diuji restore.

---

## 7. Role & permission (Phase 10)

### Saat deploy

`migrate` sudah cukup untuk struktur: migrasi
`2026_10_26_000000_create_roles_and_permissions_tables` membuat tabel `roles`,
`permissions`, `permission_role`, menambah `users.role_id`, membuat role
**Super Admin**, dan memindahkan setiap admin lama (`is_admin = 1`) ke role
tersebut sehingga tidak ada yang kehilangan akses.

Katalog permission diisi oleh seeder (idempotent, aman dijalankan berulang):

```sh
php artisan migrate --force
php artisan db:seed --class=RolePermissionSeeder --force
```

Seeder memakai `firstOrCreate`, jadi label yang sudah diubah admin lewat layar
Permission tidak tertimpa.

### Aturan yang berlaku

- Setiap admin memiliki **satu** role (`users.role_id`, foreign key).
- Akun `is_admin = 1` tanpa role ditolak middleware `admin` dengan pesan
  "Akun admin Anda belum memiliki role".
- Role Super Admin bersifat permanen: tidak dapat diubah, tidak dapat dihapus,
  dan selalu memiliki seluruh permission (termasuk permission baru).
- Role yang masih dipakai akun admin tidak dapat dihapus. Permission yang masih
  diberikan ke role juga tidak dapat dihapus.
- Perubahan permission berlaku pada **request berikutnya** — tidak perlu login
  ulang, dan tidak ada cache permission.
- Setiap perubahan role/permission dicatat di `audit_logs`
  (`role.*`, `permission.*`). Layar untuk membacanya tersedia di `/admin/audit-logs` (Phase 12).

### Menugaskan role ke akun baru

Sejak Phase 11, role dipilih saat admin diundang di layar **Akun Admin → Undang
Admin**; tidak ada jalur lain yang menerima `role_id` dari form. Untuk
memperbaiki akun lama yang belum memiliki role, gunakan tinker:

```php
$user = App\Models\User::where('email', 'admin2@example.test')->first();
$user->is_admin = true;
$user->assignRole(App\Models\Role::where('name', 'Admin Operasional')->first());
$user->save();
```

---

## 8. Akun admin & undangan (Phase 11)

### Saat deploy

```sh
php artisan migrate --force
php artisan db:seed --class=RolePermissionSeeder --force
```

Migrasi `2026_10_26_000001_add_admin_account_status_and_invitations` menambah
`users.status`, `users.deactivated_at`, membuat tabel `admin_invitations`, dan
membuat `users.password` nullable. Semua akun yang sudah ada otomatis berstatus
`active`, jadi tidak ada admin yang kehilangan akses.

Seeder katalog perlu dijalankan agar permission baru (`admin-accounts.view`,
`admin-accounts.manage`) tersedia dan dapat diberikan ke role selain Super Admin.

### Alur

1. Super Admin membuka **Akun Admin → Undang Admin**, mengisi nama, email, role.
2. Sistem membuat akun berstatus **Menunggu Undangan** tanpa password, lalu
   mengirim email berisi tautan sekali pakai yang berlaku 24 jam.
3. Penerima membuka tautan, membuat password sendiri, akun menjadi **Aktif**,
   lalu login seperti biasa.
4. **Nonaktifkan** menghapus baris `sessions` milik akun tersebut dan menolak
   request berikutnya lewat middleware `admin`. **Aktifkan** memulihkan akses
   tanpa undangan baru karena password masih tersimpan.
5. **Kirim ulang undangan** membuat token baru dan mencabut tautan lama, sehingga
   hanya ada satu tautan aktif per akun.

### Email undangan

Undangan memakai konfigurasi `MAIL_*` yang sama dengan e-ticket, tanpa flag
tambahan. Di lokal, Mailpit menerima email pada <http://localhost:8025>.

Jika pengiriman gagal, akun tetap dibuat, kegagalan dicatat di
`storage/logs/laravel.log` (cari "Gagal mengirim undangan admin"), dan sejak
Phase 13 statusnya tampil sebagai badge **Gagal dikirim** pada kolom Undangan
(tersimpan di tabel `email_deliveries`). Perbaiki konfigurasi email lalu gunakan
**Kirim ulang undangan**. Sebelum go-live, pastikan `MAIL_MAILER`, `MAIL_HOST`,
`MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, dan `MAIL_FROM_ADDRESS` produksi
sudah benar — tanpa itu admin baru tidak akan pernah menerima tautannya.

### Aturan yang berlaku

- Akun `invited` tidak memiliki password dan tidak dapat login sebelum undangan
  diterima.
- Akun `inactive` ditolak saat login dan langsung di-logout bila masih memegang
  sesi.
- Super Admin terakhir tidak dapat dinonaktifkan, dan admin tidak dapat
  menonaktifkan akunnya sendiri.
- Hanya Super Admin yang dapat menugaskan role Super Admin atau mengirim ulang
  undangan akun Super Admin.
- Profil sendiri (`/admin/profile`) tidak memerlukan permission akun. Mengubah
  password mewajibkan password saat ini dan mengakhiri sesi di perangkat lain.
- Setiap perubahan akun dicatat di `audit_logs` (`admin.*`). Layar pembacanya
  tersedia di `/admin/audit-logs` (Phase 12).

---

## 9. Metode pembayaran & audit log (Phase 12, dikembangkan Phase 12B)

### Saat deploy

```sh
php artisan migrate --force
php artisan db:seed --class=PaymentMethodSeeder --force
php artisan db:seed --class=RolePermissionSeeder --force
```

Migrasi Phase 12:

- `2026_10_26_000002_create_payment_methods_table` membuat tabel
  `payment_methods`.
- `2026_10_26_000003_add_payment_instructions_snapshot_to_orders_table` menambah
  `orders.payment_instructions_snapshot` dan mengisi snapshot pesanan lama dengan
  katalog yang aktif saat migrasi dijalankan; setelah itu instruksi pesanan
  tersebut tidak berubah lagi.

Migrasi Phase 12B:

- `2026_10_27_000000_add_type_and_qr_image_to_payment_methods_table` menambah
  `payment_methods.type` (bawaan `bank_transfer`) dan
  `payment_methods.qr_image_path`. Baris lama tidak berubah; hanya tipe
  bawaannya yang dipakai sampai diubah.
- `2026_10_27_000001_create_event_payment_methods_table` membuat tabel override
  aktivasi per event. Tanpa baris di tabel ini, sebuah event mengikuti status
  global—itulah sebabnya instalasi yang tidak pernah menyentuh layar per event
  berperilaku persis seperti Phase 12.
- `2026_10_27_000002_rename_payment_method_permissions` mengganti nama kunci
  permission `payment-methods.view` → `payment_methods.view` dan
  `payment-methods.manage` → `payment_methods.toggle` **berdasarkan id**, jadi
  role yang sudah diberi akses tidak kehilangan aksesnya saat deploy.

`PaymentMethodSeeder` menyalin `config/payments.php` ke tabel dan aman
dijalankan berulang: metode yang sudah ada tidak ditimpa, sehingga perubahan
yang dibuat lewat panel tidak hilang saat deploy berikutnya.

`RolePermissionSeeder` perlu dijalankan agar permission baru tersedia untuk
diberikan ke role selain Super Admin:

| Permission | Arti |
| --- | --- |
| `payment_methods.view` | Melihat katalog metode dan status aktivasi |
| `payment_methods.create` | Menambah metode baru |
| `payment_methods.update` | Mengubah nama, tipe, detail, dan langkah pembayaran |
| `payment_methods.toggle` | Mengaktifkan / menonaktifkan, global maupun per event |
| `payment_methods.delete` | Menghapus metode yang tidak lagi dipakai |

Super Admin selalu memiliki semuanya; role lain hanya mendapat yang diberikan
lewat layar **Hak Akses → Role**. Menandai detail sebagai data resmi (menghapus
centang “data contoh”) bukan permission yang bisa diberikan: itu hanya untuk
Super Admin.

### Mengatur metode

1. Buka **Pengaturan → Metode Pembayaran**. Halaman ini memerlukan permission
   `payment_methods.view`.
2. Setiap baris menampilkan nama, kunci, penyedia, **tipe**, status
   **konfigurasi** (lengkap atau daftar detail yang masih kurang), status
   **global**, dan—bila satu event dipilih—status untuk event tersebut.
3. **Tambah Metode** memerlukan `payment_methods.create`. Kunci metode hanya
   diisi saat pembuatan dan tidak dapat diubah setelahnya, karena dipakai
   `payments.method` dan snapshot pesanan. Kunci yang masih dirujuk riwayat
   pembayaran ditolak agar riwayat tidak berpindah ke metode baru.
4. **Ubah Detail** memerlukan `payment_methods.update`: nama, tipe, detail
   penerima, langkah pembayaran, dan gambar QR. Simpan perubahan meminta
   konfirmasi karena mengubah ke mana uang peserta dikirim. Metode baru selalu
   dimulai dalam keadaan nonaktif.
5. **Aktifkan / Nonaktifkan** memerlukan `payment_methods.toggle`. Menonaktifkan
   tidak menghapus: metode hilang dari pilihan pesanan baru, tetapi pesanan lama
   tetap dapat menyelesaikan pembayaran dengan metode tersebut.
6. **Hapus Metode** memerlukan `payment_methods.delete` dan hanya tersedia bila
   metode belum pernah dipakai pembayaran maupun tersimpan pada snapshot
   pesanan. Bila sudah dipakai, halaman ubah detail menjelaskan bahwa
   satu-satunya tindakan aman adalah menonaktifkannya.
7. Metode dengan penanda **Data contoh** (`is_dummy`) hanya tampil di environment
   `local` dan `testing`. Di production metode tersebut tidak pernah ditawarkan
   sampai detail resmi diisi dan centang “Tandai sebagai data contoh” dihapus
   oleh Super Admin.

### Tipe pembayaran dan kelengkapan detail

`payment_methods.type` menentukan detail mana yang wajib diisi:

| Tipe | Detail wajib | Gambar QR |
| --- | --- | --- |
| Transfer Bank | Bank, Nomor Rekening, Atas Nama | Tidak |
| Virtual Account | Bank Penerbit, Nomor VA, Atas Nama | Tidak |
| E-Wallet | Penyedia, Nomor Tujuan, Atas Nama | Tidak |
| QRIS | Merchant | Ya, atau ID Merchant / NMID |

Kolom detail pada form dibangun dari tipe yang dipilih, sehingga baris tersimpan
selalu memakai label yang dikenal aplikasi. Baris lama dari Phase 12 yang
labelnya tidak dikenal tetap disimpan, ditampilkan sebagai “Detail tambahan dari
data lama”, dan tetap muncul pada instruksi pembayaran.

Metode yang belum lengkap boleh disimpan sebagai draf, tetapi **tidak pernah
ditawarkan kepada peserta**: peserta hanya melihat metode yang aktif, lengkap,
dan tidak dimatikan untuk event yang dipesan.

### Gambar QR merchant (QRIS)

Gambar diunggah pada form ubah detail dan disimpan pada disk publik
(`PAYMENT_QR_DISK`, bawaan mengikuti `POSTER_DISK`) karena peserta harus
memuatnya untuk memindai. Batas format dan ukuran ada di
`config/payments.php` (`qr_image`). Mengganti gambar tidak mengubah URL yang
sudah tersimpan pada snapshot pesanan lama; mengosongkan kolom ID Merchant
sekaligus menghapus gambar akan ditolak.

### Aktivasi per event

Buka **Event → Metode Pembayaran** (`/admin/events/{event}/payment-methods`) atau
pilih satu event pada layar katalog metode. Layar ini memerlukan
`payment_methods.view` untuk melihat dan `payment_methods.toggle` untuk mengubah.

- **Aktifkan / Nonaktifkan di Event Ini** menulis satu baris override untuk event
  tersebut saja. Event lain tidak terpengaruh.
- **Ikuti Global** menghapus override sehingga event kembali mengikuti status
  global; tanpa tombol ini sebuah override tidak dapat dibatalkan.
- Tabel menampilkan status global, status khusus event ini, dan kolom
  **Ditawarkan** yang merupakan hasil gabungan keduanya plus kelengkapan detail.
- Menonaktifkan metode untuk sebuah event meminta konfirmasi, karena peserta
  event tersebut langsung kehilangan pilihan itu pada pesanan baru.

### Snapshot instruksi pesanan

Instruksi yang dilihat peserta disimpan pada
`orders.payment_instructions_snapshot` saat pesanan dibuat. Mengubah nama
metode, nomor rekening, atau menonaktifkan metode **tidak** mengubah instruksi
pesanan yang sudah ada. Untuk memberikan instruksi baru, peserta membuat
pesanan baru; data historis tidak ditulis ulang.

### Audit log

`/admin/audit-logs` hanya dapat dibuka role Super Admin (tidak ada permission
yang bisa diberikan). Halaman ini read-only: filter berdasarkan tindakan, nama
pelaku, dan rentang tanggal, lalu buka **Detail** untuk melihat metadata
mentah. Tidak ada route membuat, mengubah, atau menghapus catatan.

Yang tercatat: verifikasi pembayaran (`payment.*`), penerbitan tiket dan
check-in (`ticket.*`), kedaluwarsa pesanan (`order.expired`), perubahan akses
(`role.*`, `permission.*`), perubahan akun admin (`admin.*`), serta perubahan
konfigurasi metode pembayaran: `payment.method_updated`,
`payment.method_activated`, `payment.method_deactivated`, dan—sejak Phase 12B—
`payment.method_created`, `payment.method_deleted`,
`payment.method_event_toggled` (berisi `event_id` dan `event_name`), serta
`payment.method_event_inherited`. Catatan audit tidak pernah menyimpan password,
token akses, atau OTP; yang tersimpan adalah detail metode beserta aktornya.

### Menambah metode baru

Sejak Phase 12B metode baru ditambahkan lewat formulir **Tambah Metode**; entri
di `config/payments.php` + `PaymentMethodSeeder` tetap tersedia untuk metode yang
harus ada di setiap instalasi dan untuk instalasi yang tabelnya masih kosong.
Kunci metode (`key`) hanya diisi saat pembuatan karena dipakai `payments.method`
dan snapshot pesanan.

### QA manual (Phase 12B)

| # | Langkah | Hasil yang diharapkan |
| --- | --- | --- |
| 1 | Login sebagai Super Admin, buka **Pengaturan → Metode Pembayaran → Tambah Metode**, isi nama, kunci `qa_bank`, tipe Transfer Bank, lengkapi Bank/Nomor Rekening/Atas Nama, 1 langkah, lalu simpan | Metode tersimpan, muncul di daftar dalam keadaan **Nonaktif**, konfigurasi **Lengkap**, dan tercatat `payment.method_created` di audit log |
| 2 | Ulangi dengan kunci `qa_bank` | Ditolak dengan pesan “Kunci metode sudah dipakai metode lain.” |
| 3 | Tambah metode tipe QRIS tanpa gambar dan tanpa ID Merchant | Ditolak dengan pesan “Unggah gambar QR merchant atau isi ID Merchant / NMID.” |
| 4 | Ubah tipe metode ke Virtual Account tanpa mengisi Nomor VA, lalu simpan | Ditolak dengan pesan “Nomor VA wajib diisi untuk tipe Virtual Account.” |
| 5 | Aktifkan metode yang detailnya belum lengkap | Muncul peringatan bahwa metode belum ditawarkan dan detail apa yang kurang |
| 6 | Buat dua event (A dan B), buka **Event A → Metode Pembayaran**, nonaktifkan satu metode di sana | Baris menjadi **Dinonaktifkan khusus** untuk Event A; halaman Event B tetap **Mengikuti global** |
| 7 | Buka halaman pesanan event A dan event B | Metode yang dinonaktifkan hanya hilang pada event A |
| 8 | Klik **Ikuti Global** pada baris tersebut | Override hilang, status kembali mengikuti global, tercatat `payment.method_event_inherited` |
| 9 | Buat pesanan, catat nomor rekening pada instruksi, lalu ubah nomor rekening di katalog | Halaman status pesanan lama tetap menampilkan nomor lama; pesanan baru menampilkan nomor baru |
| 10 | Coba hapus metode yang sudah dipakai pesanan | Ditolak dengan pesan agar metode dinonaktifkan saja; tombol hapus tidak muncul di halaman ubah detail |
| 11 | Hapus metode yang belum pernah dipakai | Terhapus, tercatat `payment.method_deleted`, dan baris override per event-nya ikut hilang |
| 12 | Login sebagai admin non-Super Admin tanpa permission metode, buka `/admin/payment-methods/create` dan kirim `POST` langsung | Ditolak 403 pada keduanya, bukan hanya disembunyikan di UI |
| 13 | Sebagai admin dengan `payment_methods.update` tetapi bukan Super Admin, buka Ubah Detail sebuah metode yang sudah berstatus **Data resmi** | Kontrol “Tandai sebagai data contoh” tidak ditampilkan, hanya status bacaan; menyimpan perubahan lain tidak mengubah status data tersebut |
| 14 | Sebagai admin dengan `payment_methods.toggle`, jalankan alur pembayaran peserta sampai bukti diunggah | Tidak ada regresi: metode, instruksi, dan unggah bukti tetap berjalan seperti sebelumnya |
| 15 | Buka `/admin/audit-logs` sebagai Super Admin, lalu sebagai admin biasa | Super Admin melihat seluruh aksi di atas; admin biasa ditolak 403 |

---

## 10. Email transaksional & PDF e-ticket (Phase 13)

### Saat deploy

```sh
php artisan migrate --force
php artisan db:seed --class=RolePermissionSeeder --force
php artisan view:clear
```

Migrasi `2026_10_26_000004_create_email_deliveries_table` membuat tabel
`email_deliveries`, yaitu jejak setiap percobaan pengiriman email transaksional.
Seeder katalog menambahkan permission `tickets.resend`; role selain Super Admin
harus diberi permission ini lewat layar Role agar tombol **Kirim Ulang Tiket**
muncul.

### Konfigurasi email

Email e-ticket hanya dikirim bila `TICKET_EMAIL_ENABLED=true`. Default-nya
`false`, karena layanan email produksi belum ditetapkan (PRD section 15
pertanyaan 7). Selama masih `false`, setiap percobaan tetap tercatat dengan
status **Dilewati**, dan peserta dapat membuka tiketnya dari halaman status.

Di lokal, `MAIL_HOST=mailpit` dan `MAIL_PORT=1025` menampung semua email pada
<http://localhost:8025>, termasuk lampiran PDF-nya.

### Tiga status pengiriman

| Status | Arti | Tindakan |
| --- | --- | --- |
| Terkirim | Transport menerima email | Tidak ada |
| Gagal dikirim | Transport menolak; pesan error disimpan di baris itu | Perbaiki konfigurasi email, lalu **Kirim Ulang Tiket** |
| Dilewati | `TICKET_EMAIL_ENABLED=false` | Aktifkan email bila memang sudah siap |

### Mengirim ulang e-ticket

1. Buka **Pesanan** lalu detail pesanan yang pembayarannya sudah disetujui.
2. Kartu **Email E-Ticket** menampilkan penerima, status percobaan terakhir,
   pesan error bila ada, dan lima percobaan terakhir.
3. Klik **Kirim Ulang Tiket**. Tindakan ini memakai tiket dan QR yang sama:
   tidak ada tiket atau token baru, sehingga email lama dan PDF lama tetap sah.
4. Setiap kirim ulang menambah satu baris `email_deliveries` dan satu entri audit
   `ticket.email_resent`.

Tombol ini memerlukan permission `tickets.resend`. Super Admin memilikinya
otomatis; role lain harus diberikan lewat layar Role.

### PDF e-ticket

- Dirender per permintaan dari database oleh `TicketPdfRenderer`, tidak disimpan
  di disk, sehingga tidak ada salinan usang yang menyimpang dari status tiket.
- Peserta mengunduhnya dari halaman tiket (**Unduh PDF**) atau dari lampiran
  email. Keduanya memakai token akses pesanan yang sama.
- QR di dalam PDF berisi `tickets.qr_token` — nilai yang sama yang divalidasi
  scanner, tanpa data pribadi di dalamnya.
- PDF memakai font base-14 Helvetica dengan encoding Windows-1252. Huruf
  beraksen ditransliterasi; karakter di luar set tersebut (mis. emoji) tidak
  muncul di PDF.

### Undangan admin

Status pengiriman undangan kini memakai tabel yang sama dan tampil sebagai badge
pada kolom **Undangan** di menu Akun Admin. Undangan yang dikirim sebelum Phase
13 tidak memiliki baris jejak karena tidak ada data historis yang dapat diisi
ulang.

### Retry otomatis

Belum ada. Kirim ulang masih manual, dan meskipun `QUEUE_CONNECTION` sudah
`database`, queue worker tidak dijalankan pada MVP. Semua percobaan tercatat,
jadi kebijakan retry otomatis dapat ditambahkan tanpa kehilangan data.

---

## 11. Pengingat WhatsApp (Phase 14)

### Saat deploy

```sh
php artisan migrate --force
php artisan config:cache
php artisan view:cache
php artisan schedule:list
```

Migrasi `2026_10_26_000005_create_whatsapp_deliveries_table` membuat tabel
`whatsapp_deliveries`, yaitu satu baris per tahap pengingat per pesanan. Tidak
ada permission baru: pengingat dikirim otomatis oleh scheduler, bukan oleh
tindakan admin.

### Konfigurasi

| Variabel | Default | Arti |
| --- | --- | --- |
| `WHATSAPP_ENABLED` | `false` | Mengaktifkan scheduler pengingat |
| `WHATSAPP_DRIVER` | `log` | Driver gateway yang dipakai |
| `WHATSAPP_MAX_ATTEMPTS` | `3` | Batas percobaan per tahap pengingat |

Gateway provider belum ditetapkan bisnis, jadi Phase 14 hanya mengirim driver
`log`. Driver itu **tidak menghubungi provider mana pun**; pesan ditulis ke
`storage/logs/laravel.log` dan statusnya tercatat `SENT` dengan nama driver
`log`. Gunakan hanya untuk QA lokal/staging. Pesan berisi tautan status pesanan
yang bersifat rahasia, sehingga file log tersebut harus diperlakukan sebagai
data sensitif.

Menambahkan provider nyata berarti membuat satu class yang mengimplementasikan
`App\Services\WhatsApp\WhatsAppGateway`, lalu mendaftarkannya di
`config/whatsapp.php` pada `drivers`. `WhatsAppReminderService` tidak perlu
diubah.

### Kapan pengingat dikirim

`whatsapp:send-payment-reminders` berjalan setiap menit dan hanya membuat
pengingat untuk pesanan yang:

- masih `PENDING_PAYMENT`;
- belum memiliki baris `payments` (bukti belum diunggah);
- belum melewati `expires_at`;
- sudah berumur 30 menit (tahap pertama) atau 50 menit (tahap kedua).

Jika scheduler sempat mati, hanya tahap yang masih relevan yang dikirim. Pesanan
yang baru terlihat pada menit ke-55 hanya menerima pengingat menit ke-50; baris
menit ke-30 yang belum terkirim ditutup sebagai `SKIPPED`, bukan dikirim
terlambat. Pesanan yang sudah lewat tenggat tidak pernah mendapat pengingat.

Pengingat berhenti dengan sendirinya begitu bukti diunggah, pembayaran
diputuskan, atau pesanan kedaluwarsa. Tidak ada pembatalan manual yang
diperlukan.

### Status dan pencegahan duplikasi

| Status | Arti | Tindakan |
| --- | --- | --- |
| Menunggu | Sudah diantrekan, belum ada hasil | Tunggu scheduler berikutnya |
| Terkirim | Gateway menerima pesan; `driver` dan `sent_at` terisi | Tidak ada |
| Gagal dikirim | Gateway menolak; pesan error tersimpan | Periksa provider; retry otomatis berjalan selama pesanan masih relevan |
| Dilewati | Sengaja tidak dikirim karena bukti sudah ada, tenggat lewat, status berubah, atau tahap sudah digantikan tahap berikutnya | Tidak ada |

Unique index `(order_id, kind)` adalah pengaman duplikasi: satu pesanan hanya
bisa memiliki satu baris untuk setiap tahap, bahkan jika dua proses scheduler
berjalan bersamaan. Klaim percobaan dilakukan dengan `UPDATE ... WHERE
attempts = ...` sebelum gateway dipanggil, sehingga dua proses tidak dapat
mengirim baris yang sama.

Retry bersifat otomatis dan terbatas: maksimal `WHATSAPP_MAX_ATTEMPTS` percobaan
per tahap, dan hanya selama pesanan masih menunggu pembayaran serta belum lewat
tenggat. Tidak ada tombol kirim ulang manual untuk pengingat. Riwayat dan status
terlihat pada kartu **Pengingat WhatsApp** di detail pesanan admin.

### Tautan aman dan notifikasi pembayaran berhasil

Pesan memakai `orders/{access_token}` — token rahasia halaman status pesanan —
dan tidak pernah memuat `tickets.qr_token`. Token QR tetap hanya untuk scanner
check-in.

Notifikasi pembayaran berhasil dan informasi tiket tetap dikirim melalui email
(Phase 13), bukan WhatsApp. Phase 14 tidak menambahkan pesan WhatsApp untuk
pesanan yang sudah dibayar.

### QA manual

1. Set `WHATSAPP_ENABLED=true`, `WHATSAPP_DRIVER=log`, lalu jalankan
   `php artisan config:clear`.
2. Buat satu pesanan baru, lalu majukan waktu pembuatannya agar melewati menit
   ke-30:
   `php artisan tinker --execute="App\Models\Order::find(ID)->forceFill(['created_at' => now()->subMinutes(31)])->save();"`
3. Jalankan `php artisan whatsapp:send-payment-reminders`. Output harus
   menampilkan satu pengingat baru dan satu terkirim; log memuat pesan beserta
   tautan status.
4. Buka detail pesanan di admin. Kartu **Pengingat WhatsApp** menampilkan badge
   **Terkirim**, `driver log`, satu percobaan, dan waktu kirim.
5. Jalankan command lagi. Tidak boleh ada percobaan atau baris baru.
6. Unggah bukti pembayaran untuk pesanan itu, lalu jalankan command lagi.
   Pengingat berikutnya harus berhenti; tidak ada pesan baru.
7. Untuk menguji tahap menit ke-50, buat pesanan lain dan set `created_at` ke 51
   menit lalu. Hanya tahap menit ke-50 yang boleh muncul.

---

## 12. Keterbatasan yang diketahui

- Reset/pembatalan check-in belum tersedia (PRD section 15 pertanyaan 14).
  `TicketPolicy::resetCheckIn()` sengaja mengembalikan `false`.
- Pencarian tiket manual di gerbang belum tersedia; hanya entry token.
- Unggah ulang bukti setelah penolakan belum tersedia (pertanyaan 3 & 4).
- Ekspor CSV/Excel belum menjadi komitmen MVP (pertanyaan 13).
- Metode pembayaran aktif dan detail rekening/VA/QRIS belum dikonfirmasi
  (pertanyaan 1, 2, 16). Sejak Phase 12 katalog dikelola lewat tabel
  `payment_methods`, tetapi metode bertanda data contoh tetap tidak
  ditampilkan di production sampai detail resmi diisi.
- Baris `payment_methods` yang dibuat sebelum Phase 12B memakai tipe bawaan
  `bank_transfer` sampai seorang admin memilih tipe yang benar pada form ubah
  detail. Detail yang sudah ada tetap tampil; hanya validasi per tipe yang
  mengikuti tipe tersebut.
- Baris detail lama yang labelnya tidak dikenal aplikasi tidak dapat diubah dari
  form; baris itu ditampilkan sebagai "Detail tambahan dari data lama" dan tetap
  disimpan serta tetap tampil pada instruksi pembayaran.
- Snapshot pesanan menyimpan URL gambar QR saat pesanan dibuat. Bila berkas
  gambar kelak dihapus atau dipindahkan, pesanan lama akan menampilkan gambar
  yang gagal dimuat; instruksi teksnya tetap utuh.
- Menghapus metode tetap tidak mungkin selama pembayaran atau snapshot pesanan
  masih merujuknya; tidak ada pengarsipan selain menonaktifkannya. Ini disengaja
  agar riwayat tidak pernah kehilangan tujuannya.
- Aktivasi per event hanya memengaruhi pesanan baru. Pesanan yang sudah dibuat
  tetap memakai snapshot instruksinya, termasuk metode yang kemudian dimatikan
  untuk event tersebut.
- Tidak ada pengaturan detail rekening per event (di luar lingkup Phase 12B).
  Detail tetap global; yang per event hanya aktif/nonaktif.
- Test otomatis: sesuai Testing Policy, Phase 12B tidak menambahkan automated
  test. Verifikasi dilakukan lewat migrasi, `route:list`, kompilasi Blade, Pint,
  dan QA manual di atas.
- Retry email masih manual: belum ada queue worker, jadwal retry, atau batas
  jumlah percobaan (Phase 13).
- PDF e-ticket memakai font base-14 dengan encoding Windows-1252, jadi karakter
  non-Latin atau emoji tidak tampil. Template PDF berbasis HTML/CSS belum ada dan
  memerlukan dependency baru bila kelak diminta.
- Undangan admin yang dikirim sebelum Phase 13 tidak memiliki riwayat
  pengiriman.
- WhatsApp baru memiliki driver `log`; belum ada adapter provider nyata,
  kredensial, atau kontrak payload provider (Phase 14). Pesan dan tautan status
  hanya tercatat di log.
- Pengingat WhatsApp dikirim sinkron dari scheduler dan bergantung pada
  scheduler yang berjalan. Queue worker belum dijalankan pada MVP.
- Teks pesan pengingat masih ditulis di `WhatsAppReminderService`; persetujuan
  redaksi resmi dari bisnis belum ada. Kebijakan opt-out peserta juga belum
  ditetapkan.
- Status pengiriman ditulis setelah gateway menerima pesan. Jika proses mati
  tepat di antara panggilan gateway dan penyimpanan status, percobaan berikutnya
  dapat mengirim pesan yang sama lagi; unique row mencegah duplikasi antrean,
  bukan jaminan at-most-once saat crash.
- Pemeriksaan "masih relevan" dilakukan sebelum pesan diserahkan ke gateway.
  Bukti yang diunggah saat gateway sedang memproses pesan dapat membuat satu
  pengingat yang sedang berjalan tetap terkirim; pengingat berikutnya berhenti.
- Mengaktifkan `WHATSAPP_ENABLED` pada instalasi yang sudah berjalan akan
  mengantrekan pesanan lama yang masih memenuhi syarat pada run pertama, bukan
  hanya pesanan baru.
<!-- test commit -->