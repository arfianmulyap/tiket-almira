# Deploy ke Vercel (testing internal)

Dokumen ini khusus untuk **deployment internal**, bukan produksi. Baca bagian
"Batasan yang diterima" sebelum memberikan URL ke siapa pun di luar tim.

## Batasan yang diterima

Vercel tidak menyediakan runtime PHP resmi dan filesystem-nya read-only
kecuali `/tmp`, yang isinya dibuang setiap saat. Konsekuensi yang **tidak
bisa dihindari dengan konfigurasi**:

| Hal | Status di Vercel |
|---|---|
| Runtime PHP | Komunitas (`vercel-php`), bukan resmi. Risiko ditanggung sendiri. |
| Upload poster & bukti bayar | Berfungsi **hanya jika** object storage dikonfigurasi (langkah 2). Tanpa itu, file hilang. |
| Scheduler per menit | Tidak ada di plan Hobby. Cron Hobby = 1x/hari, presisi ±59 menit. |
| Metode pembayaran | Data contoh. `PAYMENTS_ALLOW_DUMMY=true` membukanya untuk testing. |
| Batas ukuran upload | Vercel menolak request body > 4.5 MB dengan 413, sedangkan limit aplikasi 4 MB. |
| Antrian (queue) | `QUEUE_CONNECTION=database` tidak diproses: tidak ada worker. Gunakan `sync`. |

Kuota tiket tetap akurat walau sweep jarang jalan: `Order::scopeHoldingQuota`
berhenti menghitung order yang sudah lewat batas waktu, jadi sweep yang
tertunda hanya menyisakan status yang belum diperbarui, bukan overselling.

## 1. Database

Vercel tidak menyediakan MySQL. Siapkan database terkelola yang bisa diakses
publik, misalnya PlanetScale, Aiven, atau TiDB Cloud (MySQL), lalu catat
host, port, nama database, user, dan password.

Jalankan migrasi dari mesin lokal yang menunjuk ke database tersebut, karena
fungsi serverless tidak dipakai untuk migrasi:

```sh
php artisan migrate --force
php artisan db:seed --class=AdminUserSeeder
```

Pastikan `ADMIN_PASSWORD` di `.env` lokal sudah diganti dari `123` sebelum
seeder dijalankan — password itu akan menjadi kredensial admin yang nyata.

### TLS: tidak bisa diselesaikan hanya dengan environment variable

Ketiga provider di atas mewajibkan koneksi terenkripsi, dan beberapa menolak
koneksi yang tidak memverifikasi sertifikat. `config/database.php` menyediakan
satu kaitan untuk ini:

```php
'options' => extension_loaded('pdo_mysql') ? array_filter([
    Mysql::ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
]) : [],
```

Masalahnya `ATTR_SSL_CA` menerima **path ke file**, bukan isi sertifikat.
Environment variable di dashboard Vercel hanya bisa menyimpan string, jadi
menempelkan isi PEM ke `MYSQL_ATTR_SSL_CA` tidak akan bekerja — PDO akan
memperlakukannya sebagai nama file dan gagal membukanya.

Perhatikan juga `array_filter()`: kalau `MYSQL_ATTR_SSL_CA` tidak diset,
entri itu dibuang dan PDO tersambung **tanpa verifikasi sertifikat**. Itu
bukan kegagalan yang terlihat, jadi jangan simpulkan "TLS aman" hanya karena
koneksi berhasil.

Dua jalan yang tersedia:

1. **Commit CA bundle provider ke repo** (misal `storage/certs/ca.pem` yang
   di-whitelist di `.vercelignore`), lalu set
   `MYSQL_ATTR_SSL_CA=/var/task/user/storage/certs/ca.pem`. Path absolut di
   dalam function perlu dikonfirmasi dulu dari log deploy — jangan diasumsikan.
2. **Pakai provider yang verifikasinya cukup dengan CA sistem.** Container
   lokal punya `/etc/ssl/certs/ca-certificates.crt` (terverifikasi,
   182 KB), tapi **saya belum memverifikasi file itu ada di runtime
   `vercel-php`**. Perlu dicek sebelum diandalkan.

Belum ada yang saya bisa uji dari sini tanpa kredensial database sungguhan.
Tes paling murah: setelah DB siap, jalankan `php artisan migrate --force`
dari lokal. Kalau itu jalan tapi aplikasi di Vercel tetap gagal konek,
penyebabnya hampir pasti CA path di atas, bukan kredensial.

## 2. Object storage (wajib agar upload berfungsi)

Buat **dua bucket** S3-compatible. Cloudflare R2 punya free tier dan tanpa
biaya egress.

| Bucket | Isi | Akses |
|---|---|---|
| publik | Poster event | Public read |
| privat | Bukti pembayaran | **Tanpa public read** |

Pemisahan ini penting: bukti pembayaran adalah data pribadi, dan
`docs/OPERATIONS.md` mencatat file tersebut tidak dapat dibuat ulang. Dengan
dua bucket, aturan itu dijaga oleh kebijakan bucket, bukan hanya oleh config.

Untuk R2: `AWS_DEFAULT_REGION=auto`, `AWS_ENDPOINT` =
`https://<account-id>.r2.cloudflarestorage.com`, dan `AWS_URL` = domain publik
bucket poster. Tanpa `AWS_URL` yang benar, URL poster tidak akan resolve.

## 3. Environment variables di dashboard Vercel

Project Settings → Environment Variables. Jangan commit `.env`.

```
APP_NAME="Almira Tiket Event"
APP_ENV=production
APP_KEY=                   # hasil `php artisan key:generate --show`
APP_DEBUG=false
APP_URL=https://<project>.vercel.app
APP_TIMEZONE=Asia/Jakarta
APP_MAINTENANCE_DRIVER=file

LOG_CHANNEL=stderr         # wajib: filesystem read-only, log ke file akan gagal
LOG_STACK=stderr
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=...
DB_PORT=3306
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...
MYSQL_ATTR_SSL_CA=        # path ke file CA, BUKAN isi PEM — lihat §1
DB_TIMEZONE=+07:00        # offset numerik, bukan 'Asia/Jakarta'

SESSION_DRIVER=database    # jangan `file`
CACHE_STORE=database       # jangan `file`
QUEUE_CONNECTION=sync      # tidak ada worker di serverless

FILESYSTEM_DISK=s3_private
POSTER_DISK=s3
PAYMENT_PROOF_DISK=s3_private

AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=auto
AWS_BUCKET=<bucket-poster>
AWS_PRIVATE_BUCKET=<bucket-bukti-bayar>
AWS_URL=https://<domain-publik-bucket-poster>
AWS_ENDPOINT=https://<account-id>.r2.cloudflarestorage.com

CRON_SECRET=               # hasil `php artisan key:generate --show`
PAYMENTS_ALLOW_DUMMY=true  # testing internal saja

MAIL_MAILER=log
TICKET_EMAIL_ENABLED=false
```

`APP_DEBUG=false` tidak bisa ditawar: dengan `true`, stack trace akan
menampilkan kredensial database dan kunci object storage ke siapa pun yang
memicu error.

### `LOG_CHANNEL=stderr` wajib — gejalanya halaman putih 500

Ini bukan sekadar saran kebersihan. Filesystem di Vercel read-only, jadi
channel `single`/`stack` mencoba menulis `storage/logs/laravel.log` dan gagal.
Masalahnya logger dipanggil **oleh exception handler**, sehingga handler ikut
melempar exception saat sedang melaporkan error — tidak ada yang dirender dan
tidak ada yang tercatat. Hasilnya **HTTP 500 dengan halaman kosong** dan
function log yang juga kosong, sehingga error aslinya tidak terlihat.

Direproduksi dengan membuat `storage/logs` read-only:

```
LOG_CHANNEL=stack  -> UnexpectedValueException: The stream or file
                      ".../logs/laravel.log" could not be opened in append
                      mode: Failed to open stream: Permission denied
LOG_CHANNEL=stderr -> aman, tidak melempar
```

Selain itu `api/index.php` memindahkan **seluruh** storage ke `/tmp` lewat
`LARAVEL_STORAGE_PATH`, sehingga `storage_path()`, `view.compiled`,
`session.files`, dan `cache.stores.file.path` semuanya mengarah ke lokasi yang
bisa ditulis. Memindahkan `VIEW_COMPILED_PATH` saja **tidak cukup** — log,
cache, dan session tetap menunjuk direktori read-only.

## 4. Build settings

Ketiga nilai ini **sudah dikunci di `vercel.json`**, jadi normalnya tidak perlu
diubah di dashboard:

```json
"framework": null,
"buildCommand": "npm install --ignore-scripts --no-audit --no-fund && npm run build",
"outputDirectory": "public"
```

Jika dashboard sudah pernah menyimpan preset hasil deteksi otomatis, nilai
dashboard **menang** atas `vercel.json`. Buka Project Settings → Build &
Development Settings dan pastikan:

- Framework Preset: **Other**
- Build Command: biarkan memakai `vercel.json` (jangan isi `composer ...`)
- Output Directory: `public`
- Install Command: biarkan default

### Kenapa ketiganya wajib eksplisit

Repo ini punya `vite.config.js` dan `package.json`, sehingga Vercel mendeteksi
project sebagai **Vite** dan menjalankan static build miliknya sendiri, lalu
mencari folder `dist`. Hasilnya:

```
[STATIC_BUILD_NO_OUT_DIR] No Output Directory named "dist" found
```

Kunci `functions` **tidak** mematikan deteksi framework — hanya `framework: null`
yang mematikannya. `outputDirectory: "public"` memberi tahu Vercel di mana aset
statis berada (`public/build`, `public/css`, `favicon.ico`), karena Vite di
Laravel menulis ke `public/build`, bukan `dist`.

### Urutan `routes` itu kritis

Efek samping `outputDirectory: "public"`: **seluruh** isi `public/` menjadi
static output, termasuk `public/index.php`. Hanya `api/*.php` yang terdaftar
sebagai function, sehingga `public/index.php` diperlakukan sebagai file biasa.
Gejalanya browser **mengunduh blob** berisi source code PHP, bukan menampilkan
website:

```php
<?php
use Illuminate\Foundation\Application;
...
$app->handleRequest(Request::capture());
```

Ini kebocoran source code, bukan cuma tampilan rusak.

Penyebabnya request `/` jatuh ke `handle: filesystem`, lalu directory-index
Vercel menemukan `public/index.php` dan menyajikannya mentah. Route yang
diletakkan **sebelum** `handle: filesystem` menang atas pencarian file statis,
jadi `/` dan `/index.php` harus dialihkan eksplisit ke lambda:

```json
"routes": [
    { "src": "/", "dest": "/api/index.php" },
    { "src": "/index\\.php", "dest": "/api/index.php" },
    { "src": "/\\.htaccess", "status": 404 },
    { "handle": "filesystem" },
    { "src": "/(.*)", "dest": "/api/index.php" }
]
```

Catch-all di akhir **tidak cukup**, karena `handle: filesystem` dievaluasi lebih
dulu dan sudah menemukan file tersebut.

`public/.htaccess` juga ikut ter-deploy dan Vercel **mengabaikan** aturan Apache
di dalamnya (tidak ada mod_rewrite di sini — fungsinya digantikan `routes`).
File itu diblokir 404 agar konfigurasi internal tidak terbaca publik.

### Kenapa `buildCommand` bukan `composer run vercel`

Build container Vercel (tempat `buildCommand` dieksekusi) hanya punya
**Node.js**, tidak punya PHP maupun Composer. Memakai `composer run vercel`
menghasilkan:

```
sh: line 1: composer: command not found
Error: [BUILD_UTILS_SPAWN_127] Command "composer run vercel" exited with 127
```

PHP dan Composer baru tersedia di dalam build step runtime `vercel-php`, yang
jalan terpisah dan **otomatis menjalankan `composer install`** untuk `vendor/`.
Jadi pembagian tugasnya:

| Tahap | Dijalankan oleh | Tugas |
| --- | --- | --- |
| `buildCommand` | build container (Node) | `npm install` + `npm run build` → `public/build` |
| runtime `vercel-php` | build step function | `composer install` → `vendor/` |

Karena itu script composer `vercel` **dihapus** dari `composer.json`. Selain
tidak terpakai, aset yang dibuat di dalam build step runtime tidak bisa diakses
browser — per FAQ runtime: *"Files created during `composer run vercel` script
can be used (require/include) in your PHP lambdas, but can't be accessed from
browser (like assets)."* Aset Vite wajib dibuat di `buildCommand` agar masuk
`outputDirectory` dan tersaji statis.

Dipakai `npm install`, **bukan `npm ci`**, karena repo ini tidak memiliki
`package-lock.json` dan `npm ci` gagal keras tanpa lockfile. Jika suatu saat
lockfile di-commit, ganti ke `npm ci` agar build deterministik.

Blade **tidak** di-cache saat build: `api/index.php` mengarahkan
`VIEW_COMPILED_PATH` ke `/tmp`, jadi cache hasil build tidak akan terbaca.
View dikompilasi saat request pertama tiap instance.

## 5. Deploy

Repo sudah ada di GitHub, jadi cukup Import Project di dashboard Vercel.
Jika ingin dari terminal tanpa instalasi global:

```sh
npx vercel@latest --prod
```

## 6. Scheduler

`vercel.json` memuat cron harian (`0 1 * * *`) karena ekspresi yang lebih
sering **menggagalkan deployment** di plan Hobby.

Untuk sweep per menit tanpa upgrade ke Pro, pakai cron eksternal gratis
(misalnya cron-job.org) yang memanggil:

```
https://<project>.vercel.app/cron/expire-overdue-orders?token=<CRON_SECRET>
```

Endpoint mengembalikan `{"expired": n}`, aman dipanggil berulang dan
bersamaan, dan mengembalikan 404 jika `CRON_SECRET` tidak diset.

Vercel Cron sendiri mengirim `Authorization: Bearer $CRON_SECRET` otomatis,
sehingga path yang sama bekerja untuk keduanya.

## 7. Verifikasi setelah deploy

1. `/up` → 200
2. `/` → daftar event tampil, gambar poster termuat (URL mengarah ke bucket)
3. Login admin → buat event + upload poster → poster tampil setelah reload
4. Buat order sebagai peserta → upload bukti bayar
5. Admin → buka bukti bayar → gambar termuat, bukan 404
6. `curl "https://<project>.vercel.app/cron/expire-overdue-orders?token=<CRON_SECRET>"`
   → `{"expired":0}`
7. Panggil endpoint cron tanpa token → 404

Langkah 3–5 paling mungkin gagal. Jika poster atau bukti bayar 404,
penyebabnya hampir selalu kredensial bucket atau `AWS_URL`, bukan kode.

## 8. Sebelum dipakai peserta sungguhan

- `PAYMENTS_ALLOW_DUMMY=false` dan selesaikan Phase 05C dengan data rekening
  resmi. Saat ini halaman menampilkan nomor rekening contoh.
- Ganti `ADMIN_PASSWORD` dan rotasi `APP_KEY` jika pernah ter-commit.
- Pertimbangkan ulang platform: host dengan proses PHP persisten
  menghilangkan seluruh daftar batasan di bagian atas.
