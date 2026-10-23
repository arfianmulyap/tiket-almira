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

## 7. Keterbatasan yang diketahui

- Reset/pembatalan check-in belum tersedia (PRD section 15 pertanyaan 14).
  `TicketPolicy::resetCheckIn()` sengaja mengembalikan `false`.
- Pencarian tiket manual di gerbang belum tersedia; hanya entry token.
- Unggah ulang bukti setelah penolakan belum tersedia (pertanyaan 3 & 4).
- Ekspor CSV/Excel belum menjadi komitmen MVP (pertanyaan 13).
- Metode pembayaran aktif dan detail rekening/VA/QRIS belum dikonfirmasi
  (pertanyaan 1, 2, 16) — Phase 05C masih `BLOCKED`.
<!-- test commit -->