# Deploy Docker ke VPS (produksi)

Dokumen ini menggantikan `docs/DEPLOY_VERCEL.md` untuk server sendiri.
Target: satu VPS dengan `tiket.almira.my.id` → `45.77.172.9`.

Stack ada di `compose.prod.yaml` dan **terpisah dari `compose.yaml`**, yang
merupakan Sail untuk development (Mailpit, Selenium, MySQL terbuka). Jangan
jalankan `compose.yaml` di server.

## Arsitektur

| Service | Image | Peran |
|---|---|---|
| `web` | `caddy:2-alpine` | HTTPS otomatis + reverse ke `app:9000` |
| `app` | `docker/prod/php.Dockerfile` (PHP 8.4-FPM) | Menjalankan Laravel |
| `mysql` | `mysql:8.4` | Database, volume `mysql_data` |
| `scheduler` | image `app` | `php artisan schedule:work` tiap menit |
| `assets` | `node:22-alpine` | Hanya saat deploy: `npm ci && npm run build` |

Kode aplikasi **di-bind-mount** ke `/app` di `app`, `web`, dan `scheduler`,
bukan di-`COPY` ke dalam image. Konsekuensinya:

- `storage/` tetap ada di disk host → backup cukup `tar`, sesuai
  `docs/OPERATIONS.md`.
- Perubahan kode tidak butuh rebuild image; cukup `docker/prod/deploy.sh`.
- Image hanya berisi interpreter + ekstensi, jadi rebuild cepat.

`web` hanya membaca (mount `:ro`), `app` dan `scheduler` menulis.

## 1. Prasyarat server

- Ubuntu 22.04/24.04, minimal 2 GB RAM (build Vite + MySQL + PHP-FPM).
- Port **80** dan **443** terbuka di firewall Vultr *dan* `ufw`.
- DNS `tiket.almira.my.id` A record → `45.77.172.9` (sudah aktif).
- Akses root/SSH.

```sh
# Docker Engine + compose plugin
curl -fsSL https://get.docker.com | sh
docker --version && docker compose version

# Firewall
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw allow 443/udp
ufw --force enable
```

## 2. Ambil kode

Repo GitHub-nya privat, jadi pakai **deploy key** (read-only):

```sh
mkdir -p /opt && cd /opt
ssh-keygen -t ed25519 -C "tiket-almira-deploy" -f /root/.ssh/github_deploy -N ""
cat /root/.ssh/github_deploy.pub
```

Tempel public key itu di GitHub → repo → **Settings → Deploy keys → Add deploy
key** (biarkan "Allow write access" tidak dicentang). Lalu:

```sh
cat >> /root/.ssh/config <<'EOF'
Host github.com
    IdentityFile /root/.ssh/github_deploy
    IdentitiesOnly yes
EOF

git clone -b production git@github.com:arfianmulyap/tiket-almira.git /opt/tiket-almira
cd /opt/tiket-almira
```

## 3. `.env` produksi

`compose.prod.yaml` membaca `.env` untuk interpolasi `${...}`, dan Laravel
membaca file yang sama lewat bind mount. Buat di server:

```dotenv
APP_NAME="Almira Tiket Event"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tiket.almira.my.id
APP_TIMEZONE=Asia/Jakarta
APP_KEY=

# Dipakai Caddy (bukan dibaca Laravel).
APP_DOMAIN=tiket.almira.my.id
ACME_EMAIL=alamat-email-anda@example.com

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=warning
LOG_DAILY_DAYS=14

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=tiket_almira
DB_USERNAME=tiket_almira
DB_PASSWORD=<password-kuat>
DB_ROOT_PASSWORD=<password-root-berbeda>

SESSION_DRIVER=database
SESSION_ENCRYPT=true
CACHE_STORE=database
QUEUE_CONNECTION=database

FILESYSTEM_DISK=local
POSTER_DISK=public
PAYMENT_PROOF_DISK=local

# Akun admin pertama. Ganti sebelum menjalankan AdminUserSeeder.
ADMIN_NAME="Admin Almira"
ADMIN_EMAIL=admin@almira.my.id
ADMIN_PASSWORD=<password-kuat>

# Wajib: endpoint /cron/expire-overdue-orders, cadangan bila scheduler mati.
CRON_SECRET=

# Belum ada data rekening resmi → biarkan false.
PAYMENTS_ALLOW_DUMMY=false

MAIL_MAILER=log
TICKET_EMAIL_ENABLED=false
WHATSAPP_ENABLED=false
WHATSAPP_DRIVER=log
```

Bangkitkan nilai rahasia:

```sh
cd /opt/tiket-almira
docker run --rm -v "$PWD:/app" -w /app php:8.4-cli-alpine \
  php -r 'echo "base64:".base64_encode(random_bytes(32)), PHP_EOL;'   # APP_KEY
openssl rand -base64 24                                              # DB_PASSWORD
openssl rand -base64 24                                              # DB_ROOT_PASSWORD
openssl rand -hex 32                                                 # CRON_SECRET
```

`DB_PASSWORD` dan `DB_ROOT_PASSWORD` hanya dipakai saat volume `mysql_data`
pertama kali dibuat. Mengubahnya belakangan tidak mengubah password user di
dalam MySQL — lakukan `ALTER USER` secara eksplisit.

## 4. Kepemilikan file

php-fpm worker berjalan sebagai `www-data` (uid **82** di Alpine), sedangkan
`scheduler` dijalankan dengan `user: "82:82"`. Samakan kepemilikan checkout
supaya keduanya bisa menulis `storage/` dan `bootstrap/cache`:

```sh
chown -R 82:82 /opt/tiket-almira
chmod 600 /opt/tiket-almira/.env
git config --global --add safe.directory /opt/tiket-almira
```

Root tetap bisa `git pull`; uid 82 yang memiliki file agar tidak ada log/cache
milik root yang membuat `www-data` gagal menulis.

## 5. Deploy pertama

```sh
cd /opt/tiket-almira
docker compose -f compose.prod.yaml build app

# vendor/ dan aset
docker compose -f compose.prod.yaml run --rm -u 82:82 \
  --env HOME=/tmp --env COMPOSER_HOME=/tmp/composer app \
  composer install --no-dev --optimize-autoloader --no-interaction

docker compose -f compose.prod.yaml --profile build run --rm -u 82:82 \
  --env HOME=/tmp assets

docker compose -f compose.prod.yaml up -d
docker compose -f compose.prod.yaml exec -T app php artisan migrate --force

# Katalog permission + metode pembayaran (idempotent)
docker compose -f compose.prod.yaml exec -T app php artisan db:seed --class=RolePermissionSeeder --force
docker compose -f compose.prod.yaml exec -T app php artisan db:seed --class=PaymentMethodSeeder --force

# Akun admin pertama — hanya sekali, memakai ADMIN_* dari .env
docker compose -f compose.prod.yaml exec -T app php artisan db:seed --class=AdminUserSeeder --force

docker compose -f compose.prod.yaml exec -T app php artisan storage:link
docker compose -f compose.prod.yaml exec -T app php artisan config:cache
docker compose -f compose.prod.yaml exec -T app php artisan route:cache
docker compose -f compose.prod.yaml exec -T app php artisan view:cache
```

Deploy berikutnya cukup:

```sh
/opt/tiket-almira/docker/prod/deploy.sh
```

Sertifikat Let's Encrypt diminta otomatis oleh Caddy pada request HTTPS
pertama setelah container `web` hidup. Tidak ada cron renewal: Caddy
memperpanjang sendiri dan menyimpannya di volume `caddy_data`.

## 6. Verifikasi

1. `curl -I https://tiket.almira.my.id/up` → `200`
2. `https://tiket.almira.my.id` → daftar event tampil
3. Login admin → buat event + upload poster → poster tampil setelah reload
4. Buat order → upload bukti bayar → admin membuka bukti → gambar termuat
5. `curl "https://tiket.almira.my.id/cron/expire-overdue-orders?token=<CRON_SECRET>"`
   → `{"expired":0}`
6. `docker compose -f compose.prod.yaml ps` → `app`, `web`, `mysql`,
   `scheduler` semua `running`/`healthy`
7. `docker compose -f compose.prod.yaml exec app php artisan schedule:list`

## 7. Backup

Database:

```sh
docker compose -f compose.prod.yaml exec -T mysql \
  sh -c 'exec mysqldump --single-transaction --routines --triggers \
    -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' \
  > backup-$(date +%F-%H%M).sql
```

File upload (poster + bukti bayar, keduanya di host):

```sh
tar -czf storage-$(date +%F-%H%M).tar.gz \
  storage/app/private storage/app/public
```

Restore:

```sh
docker compose -f compose.prod.yaml exec -T mysql \
  sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' < backup-FILE.sql
tar -xzf storage-FILE.tar.gz
docker compose -f compose.prod.yaml exec -T app php artisan config:clear
```

Jangan jalankan `migrate:fresh`, `migrate:refresh`, atau `db:wipe` di produksi.

## 8. Operasional

```sh
docker compose -f compose.prod.yaml logs -f app
docker compose -f compose.prod.yaml logs -f scheduler
docker compose -f compose.prod.yaml exec app php artisan tinker
docker compose -f compose.prod.yaml restart app scheduler
```

Log aplikasi ada di `storage/logs/laravel-YYYY-MM-DD.log` di host (channel
`daily`, retensi 14 hari), jadi bisa dibaca tanpa masuk container.

## 9. Yang belum dikonfigurasi

Ini keputusan bisnis, bukan kelalaian teknis:

- **Metode pembayaran** masih data contoh dan tidak tampil di production
  (`PAYMENTS_ALLOW_DUMMY=false`). Isi rekening/VA/QRIS resmi lewat panel admin
  sebelum dipakai peserta.
- **Email** (`TICKET_EMAIL_ENABLED=false`, `MAIL_MAILER=log`): e-ticket tidak
  dikirim sampai kredensial SMTP nyata diisi. Halaman status peserta tetap
  menampilkan tiket.
- **WhatsApp** hanya driver `log`. Pesan ditulis ke log, tidak dikirim.
- **Queue worker** tidak dijalankan (sesuai MVP); semua pengiriman sinkron.
- **Backup otomatis** belum dijadwalkan; perintah di §7 masih manual.

## 10. Troubleshooting

| Gejala | Penyebab umum |
|---|---|
| Caddy gagal dapat sertifikat | Port 80 tertutup (ufw/Vultr firewall) atau DNS belum mengarah ke IP ini |
| `502` dari Caddy | Container `app` mati, atau `app:9000` tidak resolve — cek `docker compose ps` |
| `500` dengan halaman kosong | `storage/logs` tidak bisa ditulis uid 82, atau `APP_KEY` kosong |
| Upload poster hilang setelah restart | `POSTER_DISK` bukan `public`, atau folder `storage/app/public` tidak ada |
| Pesanan tidak pernah `EXPIRED` | Container `scheduler` tidak jalan / `schedule:list` kosong |
| `SQLSTATE[HY000] [2002]` | `DB_HOST` bukan `mysql`, atau MySQL belum `healthy` |
