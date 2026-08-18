# AZARED — Aplikasi Kasir / POS

Aplikasi kasir (POS) multi-modul: penjualan, pembelian, inventory, keuangan,
perpajakan, laporan, manajemen pengguna & role, hingga multi-toko — siap
dikembangkan lebih lanjut menjadi aplikasi kasir production.

Stack: **PHP 8.1+ (vanilla, tanpa framework)**, **MySQL 8** (atau MariaDB 10.6+),
HTML5, CSS3, vanilla JavaScript. Target deploy: **Vercel**, menggunakan
community runtime `vercel-php`.

---

## Daftar Isi

1. [Fitur / Modul](#1-fitur--modul)
2. [Struktur Folder](#2-struktur-folder)
3. [Requirement](#3-requirement)
4. [Instalasi & Menjalankan Lokal](#4-instalasi--menjalankan-lokal)
5. [Konfigurasi MySQL](#5-konfigurasi-mysql)
6. [Environment Variables](#6-environment-variables)
7. [Migration & Seed](#7-migration--seed)
8. [Login Default & Role](#8-login-default--role)
9. [Deployment ke Vercel](#9-deployment-ke-vercel)
10. [Routing](#10-routing)
11. [Multi Store](#11-multi-store)
12. [Keamanan](#12-keamanan)
13. [Troubleshooting](#13-troubleshooting)
14. [Backup & Restore](#14-backup--restore)
15. [Dokumen Terkait](#15-dokumen-terkait)

---

## 1. Fitur / Modul

| Modul | Route bersih | Keterangan |
|---|---|---|
| Login | `/login.php` | Rate-limited, session di MySQL |
| Dashboard | `/dashboard` | Berdiri sendiri: sidebar, topbar, statistik, grafik, transaksi terbaru, stok menipis, aktivitas terbaru, quick actions |
| User Management | `/users` | CRUD pengguna + akses toko per pengguna |
| Role | `/roles` | CRUD role kustom + matrix izin per role |
| Permission | `/permissions` | Katalog izin (read-only) + matrix role × izin |
| Kasir (POS) | `/pos` | Terpisah dari dashboard, transaksi realtime ke MySQL |
| Penjualan | `/sales` | Riwayat, detail, cetak struk (thermal & A4) |
| Retur Penjualan | `/sales/return-form.php` | - |
| Produk | `/products` | - |
| Kategori | `/categories/index.php` | - |
| Inventory | `/inventory` | Stok, penyesuaian, stock movement, stock transfer |
| Pembelian | `/purchases` | - |
| Retur Pembelian | `/purchases/return-form.php` | - |
| Customer | `/customers` | - |
| Supplier | `/suppliers` | - |
| Keuangan | `/finance` | Kas/bank, kategori biaya, pengeluaran |
| HPP | `/reports/hpp` | Rincian Harga Pokok Penjualan per produk |
| Laba Rugi | `/finance/profit-loss` | - |
| Cash Flow | `/finance/cash-flow` | - |
| Perpajakan | `/tax` | PPN, tarif, periode buku, faktur |
| Laporan | `/reports` | Hub yang menautkan semua laporan |
| Audit Log | `/audit` | Log setiap aksi sensitif |
| Pengaturan | `/settings` | Nama badan usaha, catatan struk, tautan ke Toko/Role/Pajak |
| Toko / Cabang | `/settings/stores.php` | Multi-store: tambah/edit toko, status aktif |

> Setiap route bersih di atas juga punya alias `.php` (mis. `/dashboard.php`,
> `/products/index.php`) untuk kompatibilitas mundur — keduanya memanggil file
> `api/*.php` yang sama persis. Lihat [§10 Routing](#10-routing).

---

## 2. Struktur Folder

```
azared/
├── api/                # SEMUA file PHP dinamis (entry point) — wajib di sini untuk Vercel
│   ├── index.php, login.php, logout.php, dashboard.php, audit.php
│   ├── users/ roles/ permissions/ stores/ settings/
│   ├── categories/ units/ products/ inventory/
│   ├── pos/ sales/ purchases/ customers/ suppliers/
│   ├── finance/ expenses/ expense-categories/
│   ├── tax/ reports/
│
├── config/
│   ├── config.php       # Loader env var + helper config()
│   ├── autoload.php     # Autoloader ringan (namespace App\)
│   ├── database.php     # Koneksi PDO singleton (App\Database), SSL-aware
│   └── bootstrap.php    # Wajib di-require di setiap entry point
│
├── src/
│   ├── Auth/            # AuthService, PdoSessionHandler (session di MySQL)
│   ├── Middleware/      # AuthMiddleware, PermissionMiddleware, CsrfMiddleware
│   ├── Models/          # 1 class per tabel utama, seluruhnya PDO prepared statement
│   ├── Controllers/     # Logika bisnis per modul, dipanggil dari api/*.php
│   └── Helpers/         # Csrf, Response, Validator, RateLimiter
│
├── views/                # Template HTML (di-include oleh Controller)
│   ├── layouts/          # main_top.php, main_bottom.php, sidebar.php, topbar.php
│   └── <modul>/          # index.php, form.php, show.php per modul
│
├── public/assets/
│   ├── css/style.css
│   └── js/               # app.js, ajax-forms.js, pos.js, purchases-form.js
│
├── database/
│   ├── schema.sql                    # Struktur tabel inti
│   ├── seed.sql                      # Role, permission, admin default (aman untuk production)
│   ├── migration_002_pos.sql         # POS, produk, penjualan, pembelian, stok
│   ├── migration_003_finance.sql     # Keuangan
│   ├── migration_004_tax.sql         # Perpajakan
│   ├── migration_005_hardening.sql   # Index & hardening
│   ├── migration_006_settings.sql    # app_settings, izin roles.manage/stores.manage
│   └── seed_dev.sql                  # Data DEVELOPMENT saja — jangan jalankan di production
│
├── docs/
│   ├── AUDIT_REPORT.md            # Riwayat audit tiap fase, termasuk keterbatasan yang diketahui
│   ├── MYSQL_SETUP.md             # Setup DB, migration, backup, restore (detail)
│   ├── PRODUCTION_CHECKLIST.md    # Checklist sebelum go-live
│   └── TESTING_CHECKLIST.md
│
├── vercel.json           # Konfigurasi routing & runtime PHP di Vercel
├── composer.json
├── .env.example           # Contoh environment variable — JANGAN commit .env asli
└── README.md
```

---

## 3. Requirement

- PHP 8.1+ dengan ekstensi `pdo_mysql`
- MySQL 8 atau MariaDB 10.6+
- Composer (opsional, project ini belum punya dependency wajib)
- Untuk deploy: akun Vercel + MySQL terkelola yang bisa diakses dari internet
  (PlanetScale, AWS RDS, Railway, DigitalOcean Managed MySQL, dll — **Vercel
  tidak menyediakan database**)

---

## 4. Instalasi & Menjalankan Lokal

```bash
# 1. Salin environment
cp .env.example .env
# lalu isi DB_HOST/DB_DATABASE/DB_USERNAME/DB_PASSWORD di .env

# 2. Buat database & jalankan migrasi (lihat detail di docs/MYSQL_SETUP.md)
mysql -u root -p -e "CREATE DATABASE azared CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p azared < database/schema.sql
mysql -u root -p azared < database/seed.sql
mysql -u root -p azared < database/migration_002_pos.sql
mysql -u root -p azared < database/migration_003_finance.sql
mysql -u root -p azared < database/migration_004_tax.sql
mysql -u root -p azared < database/migration_005_hardening.sql
mysql -u root -p azared < database/migration_006_settings.sql

# 3. (Opsional, untuk development) isi data contoh: produk, customer, supplier,
#    akun demo tiap role, toko kedua
mysql -u root -p azared < database/seed_dev.sql

# 4. Jalankan lewat router lokal (lihat dev-router.php di bawah)
php -S localhost:8000 dev-router.php
```

Karena struktur project mengikuti model 1-file-per-endpoint ala Vercel (bukan
1 aplikasi long-running ala Apache), untuk `php -S` lokal gunakan router kecil
yang memetakan URL bersih ke file di `api/`. Buat `dev-router.php` di root
project:

```php
<?php
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Static assets
if ($uri !== '/' && preg_match('#^/assets/#', $uri) && file_exists(__DIR__ . '/public' . $uri)) {
    return false; // let PHP's built-in server serve the file directly
}

// Clean top-level routes -> matching api/*.php (mirrors vercel.json)
$clean = [
    '/dashboard' => '/api/dashboard.php',
    '/pos' => '/api/pos/index.php',
    '/sales' => '/api/sales/index.php',
    '/purchases' => '/api/purchases/index.php',
    '/products' => '/api/products/index.php',
    '/inventory' => '/api/inventory/index.php',
    '/customers' => '/api/customers/index.php',
    '/suppliers' => '/api/suppliers/index.php',
    '/users' => '/api/users/index.php',
    '/reports' => '/api/reports/index.php',
    '/settings' => '/api/settings/index.php',
    '/roles' => '/api/roles/index.php',
    '/permissions' => '/api/permissions/index.php',
    '/finance' => '/api/finance/index.php',
    '/finance/profit-loss' => '/api/finance/profit-loss.php',
    '/finance/cash-flow' => '/api/finance/cash-flow.php',
    '/tax' => '/api/tax/index.php',
    '/tax/settings' => '/api/tax/settings.php',
    '/tax/output' => '/api/tax/output.php',
    '/tax/input' => '/api/tax/input.php',
    '/reports/sales' => '/api/reports/sales.php',
    '/reports/purchases' => '/api/reports/purchases.php',
    '/reports/inventory' => '/api/reports/inventory.php',
    '/reports/hpp' => '/api/reports/hpp.php',
    '/reports/tax' => '/api/reports/tax.php',
];
if (isset($clean[$uri])) { require __DIR__ . $clean[$uri]; return true; }

// Every other `/xxx/yyy.php` maps 1:1 to `api/xxx/yyy.php`
if (preg_match('#^/([a-z0-9_-]+/[a-z0-9_-]+\.php)$#i', $uri, $m) && file_exists(__DIR__ . '/api/' . $m[1])) {
    require __DIR__ . '/api/' . $m[1]; return true;
}
if (preg_match('#^/([a-z0-9_-]+\.php)$#i', $uri, $m) && file_exists(__DIR__ . '/api/' . $m[1])) {
    require __DIR__ . '/api/' . $m[1]; return true;
}
if ($uri === '/') { require __DIR__ . '/api/index.php'; return true; }

require __DIR__ . '/api/404.php';
```

Buka `http://localhost:8000/` di browser.

---

## 5. Konfigurasi MySQL

Ringkas — **detail lengkap ada di [`docs/MYSQL_SETUP.md`](docs/MYSQL_SETUP.md)**
(membuat database, user database dengan hak akses terbatas, menjalankan schema,
migration, seed, backup, restore).

---

## 6. Environment Variables

Salin dari `.env.example`. Minimal wajib diisi:

```
APP_ENV=production            # local | production
APP_URL=https://your-domain.example
APP_KEY=random_32_char_secret

DB_HOST=your-mysql-host.example.com
DB_PORT=3306
DB_DATABASE=azared
DB_USERNAME=azared_user
DB_PASSWORD=your_password
```

Lihat `.env.example` untuk daftar lengkap (termasuk `DB_SSL`, `SESSION_LIFETIME_MINUTES`,
`LOGIN_MAX_ATTEMPTS`, dll). **Tidak ada satu pun kredensial yang di-hardcode** di
kode aplikasi — semuanya dibaca lewat `config()` dari environment variables.

Di Vercel, isi variabel yang sama di **Project → Settings → Environment Variables**
(tidak ada file `.env` yang diupload — lihat [§9](#9-deployment-ke-vercel)).

---

## 7. Migration & Seed

Lihat [`docs/MYSQL_SETUP.md`](docs/MYSQL_SETUP.md) §2 untuk urutan lengkap dan
cara verifikasi. Ringkas:

```
schema.sql → seed.sql → migration_002 → migration_003 → migration_004
→ migration_005 → migration_006 → (opsional, dev only) seed_dev.sql
```

`seed.sql` aman dijalankan di production (hanya membuat role/permission/1 toko/
1 akun admin). `seed_dev.sql` **tidak boleh** dijalankan di production — berisi
akun demo dan data contoh (lihat komentar di kepala file tersebut).

---

## 8. Login Default & Role

- **Username:** `admin`
- **Password:** `Azared#2026!` — **ganti segera** setelah login pertama
  (`must_change_password` sudah aktif di akun ini).

Jika `seed_dev.sql` dijalankan, tersedia juga akun demo untuk tiap role
(`owner`, `manager`, `cashier`, `accountant`, `taxofficer`) dengan password yang
sama — **hanya untuk development**.

| Role | Cakupan akses utama |
|---|---|
| `admin` / `owner` | Seluruh izin, termasuk Role & Akses dan Toko/Cabang |
| `manager` | Operasional harian: produk, stok, penjualan, pembelian, laporan (view) |
| `cashier` | Dashboard, POS, produk/stok (view), penjualan |
| `accountant` | Dashboard, keuangan, laporan, HPP, Laba Rugi, Cash Flow |
| `tax_officer` | Dashboard, laporan pajak, laporan umum |

Role kustom tambahan bisa dibuat lewat `/roles`, dan izinnya diatur lewat matrix
izin per role (`/roles/permissions.php?id=`). Katalog seluruh izin yang tersedia
di sistem (read-only) ada di `/permissions`.

---

## 9. Deployment ke Vercel

**Vercel tidak mendukung PHP secara native.** Project ini memakai community
runtime [`vercel-php`](https://github.com/vercel-community/php)
(`vercel-php@0.9.0`) yang direkomendasikan resmi di halaman Vercel Community
Runtimes, mendukung PHP 7.4–8.5 dan ekstensi `pdo_mysql`.

### Keterbatasan nyata & solusinya di AZARED

| Keterbatasan | Solusi yang diterapkan |
|---|---|
| Filesystem serverless **tidak persisten** antar request. | Session PHP disimpan di tabel MySQL `sessions` via `PdoSessionHandler` (`src/Auth/PdoSessionHandler.php`), bukan di disk. |
| Vercel tidak menyediakan database. | MySQL wajib di-hosting terpisah (PlanetScale/RDS/Railway/DO), kredensial lewat Environment Variables Vercel — lihat `docs/MYSQL_SETUP.md`. |
| Setiap file `.php` di `api/` = 1 serverless function terpisah, bukan 1 aplikasi long-running. | `vercel.json` memetakan URL bersih (mis. `/dashboard`, `/products`) maupun alias `.php` ke function yang sesuai lewat `routes` — lihat [§10](#10-routing). |
| Cold start & batas durasi eksekusi. | `maxDuration: 30` diatur eksplisit di `vercel.json`; hindari query berat pada endpoint yang sering diakses. |
| Tidak ada `.env` yang bisa diupload. | Set manual di **Vercel Dashboard → Project → Settings → Environment Variables** dengan nama variabel identik dengan `.env.example`. |
| Koneksi MySQL dari luar sering mewajibkan TLS. | `config/database.php` mendukung `DB_SSL=true` + `PDO::MYSQL_ATTR_SSL_CA` (letakkan sertifikat CA provider di `config/certs/ca-cert.pem`). |
| Aset statis tidak boleh lewat PHP runtime. | `public/assets/*` disajikan langsung sebagai static file lewat `routes` (`/assets/(.*) → /public/assets/$1`), tanpa melalui PHP. |

### Langkah deploy

1. Push project ke GitHub/GitLab/Bitbucket.
2. Siapkan MySQL terkelola, jalankan seluruh migrasi (lihat §7).
3. Import project ke [vercel.com](https://vercel.com).
4. Isi **Environment Variables** di Vercel Dashboard sesuai `.env.example`
   (khususnya `DB_*`, `APP_KEY`, `APP_URL`, `APP_ENV=production`,
   `APP_DEBUG=false`).
5. Deploy. Vercel membaca `vercel.json`, membangun setiap file `api/**/*.php`
   sebagai function `vercel-php@0.9.0`, dan menyajikan `public/assets/*` sebagai
   file statis.
6. Login dengan akun admin default, **segera ganti password**.

---

## 10. Routing

`vercel.json` memetakan dua bentuk URL ke file `api/*.php` yang **sama persis**
(tidak ada logika terduplikasi):

- **Route bersih** (final, sesuai daftar modul): `/dashboard`, `/pos`, `/sales`,
  `/purchases`, `/products`, `/inventory`, `/customers`, `/suppliers`,
  `/finance`, `/tax`, `/reports`, `/users`, `/settings`, `/roles`, `/permissions`.
- **Alias `.php`** (kompatibilitas mundur): `/dashboard.php`,
  `/products/index.php`, dst. — dipertahankan karena sebagian tautan internal
  lama masih memakainya; keduanya valid dan setara.

Otorisasi selalu diperiksa **di server** lewat `PermissionMiddleware::require()`
di setiap file `api/*.php`, terlepas dari lewat route mana request datang —
tidak ada endpoint yang hanya dilindungi oleh UI/JavaScript.

---

## 11. Multi Store

Sudah berfungsi dengan benar:
- **Toko/Cabang** — kelola daftar toko lewat `/settings/stores.php` (tambah,
  edit, nonaktifkan; minimal satu toko harus tetap aktif).
- **User store access** — setiap pengguna diberi akses ke satu atau lebih toko
  lewat form Pengguna (`user_store_access`).
- **Transaksi per toko** — penjualan & pembelian mencatat `store_id`, dan
  seluruh laporan bisa difilter per toko.
- **Laporan per toko** — filter toko tersedia di Laporan Penjualan, Pembelian,
  Inventory, HPP, Laba Rugi, Cash Flow, dan Pajak.

⚠️ **Belum berfungsi dengan benar:** **stok** (`products.stock`) saat ini
adalah angka **global**, belum dipisah per toko — dua toko yang menjual produk
yang sama akan berbagi angka stok yang sama. Lihat
[`docs/AUDIT_REPORT.md`](docs/AUDIT_REPORT.md) bagian "Keterbatasan Arsitektur
yang Diketahui" untuk detail dan rekomendasi sebelum mengaktifkan lebih dari
satu toko aktif di production.

---

## 12. Keamanan

- Password: `password_hash()` bcrypt (cost 12) + `password_verify()` — tidak
  pernah plaintext.
- CSRF: setiap form `POST` menyertakan `Csrf::field()`, divalidasi otomatis
  oleh `CsrfMiddleware` di setiap endpoint.
- XSS: seluruh output ke HTML di-escape via `Response::e()`.
- SQL Injection: seluruh query memakai PDO prepared statement.
- CSP: `script-src 'self'` (tanpa `'unsafe-inline'`) — seluruh JavaScript
  berada di file eksternal (`public/assets/js/`), tidak ada inline
  `<script>`/`onclick=` di manapun di `views/` (diverifikasi di Phase 5 audit).
- Session: disimpan di MySQL (`PdoSessionHandler`), `session_regenerate_id()`
  setiap login sukses.
- Rate limiting login: `LOGIN_MAX_ATTEMPTS` + `LOGIN_LOCKOUT_MINUTES`, dicatat
  di `login_logs`.
- Authorization server-side: `PermissionMiddleware::require()` di setiap
  endpoint yang butuh otorisasi.
- Audit log: setiap aksi sensitif (create/update/delete pada data penting,
  perubahan role/izin, perubahan pengaturan) tercatat di `/audit`.

Checklist lengkap sebelum go-live: [`docs/PRODUCTION_CHECKLIST.md`](docs/PRODUCTION_CHECKLIST.md).

---

## 13. Troubleshooting

| Gejala | Kemungkinan penyebab & solusi |
|---|---|
| `500 Internal Server Error` di semua halaman | Cek `DB_*` di environment variables — koneksi MySQL gagal. Set `APP_DEBUG=true` **sementara di lokal saja** untuk melihat detail error (jangan pernah di production). |
| Login berhasil tapi langsung ter-logout / sesi tidak tersimpan | Pastikan migrasi `schema.sql` sudah membuat tabel `sessions` dan `PdoSessionHandler` aktif — cek `config/bootstrap.php`. |
| Tombol "Cetak" / auto-print struk tidak berfungsi | Pastikan `public/assets/js/app.js` termuat (cek console browser); fitur ini memakai atribut `data-azr-print`/`data-azr-autoprint`, bukan inline script, sejak Phase 5. |
| Form pembelian: baris item tidak bisa ditambah | Pastikan `public/assets/js/purchases-form.js` termuat — lihat Network tab browser untuk memastikan file ter-load (404 berarti belum ter-deploy). |
| Transaksi kasir (POS) gagal submit / error CSRF | Pastikan `<meta name="azr-csrf-token">` ada di `<head>` (cek View Source) — jika tidak ada, `views/layouts/main_top.php` kemungkinan belum ter-update. |
| Deploy Vercel gagal di step build PHP | Pastikan `vercel.json` valid JSON (`python3 -m json.tool vercel.json` atau `jq . vercel.json`) dan runtime `vercel-php@0.9.0` masih tersedia di [Vercel Community Runtimes](https://vercel.com/docs/functions/runtimes). |
| Koneksi MySQL ditolak dari Vercel | Pastikan provider MySQL mengizinkan koneksi dari luar (whitelist IP Vercel bisa berubah-ubah — banyak provider terkelola menyediakan opsi "allow all" + wajib TLS sebagai gantinya; set `DB_SSL=true`). |
| Halaman Pengaturan/Toko/Role 404 | Pastikan `database/migration_006_settings.sql` sudah dijalankan (tabel `app_settings` dan izin `roles.manage`/`stores.manage` dibuat di migrasi ini). |

---

## 14. Backup & Restore

Ringkas — **detail lengkap ada di [`docs/MYSQL_SETUP.md`](docs/MYSQL_SETUP.md) §3–4**.

```bash
# Backup
mysqldump -u azared_admin -p --single-transaction --routines --triggers \
  azared > azared-backup-$(date +%Y%m%d-%H%M%S).sql

# Restore
mysql -u azared_admin -p azared < azared-backup-20260818-020000.sql
```

---

## 15. Dokumen Terkait

- [`docs/AUDIT_REPORT.md`](docs/AUDIT_REPORT.md) — riwayat audit setiap fase
  pengembangan, termasuk bug yang ditemukan/diperbaiki dan keterbatasan
  arsitektur yang diketahui (baca sebelum mengaktifkan multi-store).
- [`docs/MYSQL_SETUP.md`](docs/MYSQL_SETUP.md) — setup database, migration,
  seed, backup, restore secara detail.
- [`docs/PRODUCTION_CHECKLIST.md`](docs/PRODUCTION_CHECKLIST.md) — checklist
  lengkap sebelum go-live.
- [`docs/TESTING_CHECKLIST.md`](docs/TESTING_CHECKLIST.md) — checklist
  pengujian manual per modul.
