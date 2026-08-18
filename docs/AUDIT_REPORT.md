# AZARED — Security, QA & Performance Audit Report

**Scope:** full codebase review across PHP, SQL, JavaScript, CSS, routing,
authentication, authorization, POS, inventory, sales, purchases, finance,
tax, reports, and dashboard modules (Prompts 1 through 3A).

**Method:** manual line-by-line review of every controller, model,
middleware, and route file, cross-referenced against the actual
permission grants in the database migrations. Findings below are only
things that were actually located in the code — this is not a generic
checklist.

**Result:** no fixes required removing or disabling any existing
AZARED feature. All changes are additive or corrective.

---

## 1. Findings & Fixes

### 🔴 Critical

#### 1.1 Stale permission cache / zombie sessions
**Where:** `AuthService`, `AuthMiddleware`
**Problem:** Roles and permissions were only ever loaded into
`$_SESSION` at login time. If an admin later changed a user's role,
revoked a permission, or **deactivated the account entirely**, none
of that took effect until the affected user's session naturally
expired (up to the configured session lifetime). A deactivated
employee could keep using the system.
**Fix:** `AuthService::refreshIfNeeded()` re-fetches `status`,
`roles`, and `permissions` from the database and overwrites the
session cache. Called from `AuthMiddleware::handle()` on every
authenticated request, memoized once per request (one extra query,
not one per permission check).
**Files:** `src/Auth/AuthService.php`, `src/Middleware/AuthMiddleware.php`

#### 1.2 IDOR on POS held carts
**Where:** `HeldCart::resume()`, `HeldCart::discard()`, `PosController`
**Problem:** Both methods took only an `id` with no ownership check.
Any authenticated cashier could resume or permanently delete another
store's held cart by incrementing the URL parameter
(`/pos/resume.php?id=17`).
**Fix:** Both methods now require the caller's store id and verify
`cart.store_id` matches before acting; `PosController` passes
`self::primaryStoreId()` (server-derived, never client input).
**Files:** `src/Models/HeldCart.php`, `src/Controllers/PosController.php`

#### 1.3 Role/store-access editing was impossible + no self-escalation guard
**Where:** `UserController`
**Problem:** `User::updateRole()` and `User::updateStoreAccess()`
existed in the model layer but were never called by any controller —
there was no route to edit an existing user's role or store
assignments at all. This also meant the prompt's explicit requirement
("user tidak dapat mengubah role sendiri / memberikan permission
sendiri / mengakses toko yang tidak diberikan") had no code path to
enforce it against.
**Fix:** Added `UserController::editForm()` / `update()` +
`views/users/edit_form.php`. Guards:
- `guardAgainstEscalation()` (existing, reused): a non-Admin actor can
  never modify an Admin account.
- Promoting a target to the Admin role requires the **actor** to
  already hold the Admin role.
- **Hard self-escalation lock**: if the target user is the same as
  the logged-in actor, `role_id`, `store_ids`, and `status` are
  silently pinned back to their current server-side values before
  validation runs — regardless of what the submitted form contains.
**Files:** `src/Controllers/UserController.php`,
`views/users/edit_form.php`, `api/users/edit.php`,
`api/users/update.php`, `vercel.json`

#### 1.4 Invoice-number race condition
**Where:** `InvoiceNumber::next()`
**Problem:** Used `SELECT ... FOR UPDATE` against a per-day counter
row. In MySQL/InnoDB, a `SELECT ... FOR UPDATE` against a row that
**doesn't exist yet** does not lock anything — it's a gap, not a row,
under the default isolation level in the way this query was written.
Two concurrent "first sale of the day" transactions could both
observe "no row yet" and both attempt to `INSERT` the same primary
key, with the loser throwing an uncaught duplicate-key exception.
**Fix:** Rewritten as a single atomic statement using MySQL's
`INSERT ... ON DUPLICATE KEY UPDATE last_number = LAST_INSERT_ID(last_number + 1)`
idiom — no explicit locking needed, no race window, no separate
INSERT-then-UPDATE.
**Files:** `src/Models/InvoiceNumber.php`

---

### 🟡 Medium

#### 2.1 No global error handler → no 500 page, no server-side log
**Problem:** Any uncaught `Throwable` had no handler at all. With
`display_errors=0` in production this meant a blank page and no
record anywhere of what happened.
**Fix:** New `App\Helpers\ErrorHandler` registers
`set_error_handler` (promotes warnings to exceptions, respecting the
`@`-operator), `set_exception_handler`, and
`register_shutdown_function` (catches fatal errors that bypass the
exception handler entirely). All three log full detail via
`error_log()` and respond with the generic `views/errors/500.php`
page (or a generic JSON envelope for AJAX/`Accept: application/json`
requests) — never a stack trace, file path, or query to the client.
**Files:** `src/Helpers/ErrorHandler.php`, `config/bootstrap.php`

#### 2.2 Missing 419 / 429 / 500 error pages
**Problem:** Only `403.php` and `404.php` existed. CSRF failures
(`419`) rendered a raw `die()` string; there was no rate-limit page
(`429`) and no server-error page (`500`).
**Fix:** Added all three, styled consistently with the existing
403/404 pages. `CsrfMiddleware::verifyRequestOrFail()` now renders
`419.php` for page requests and a JSON envelope for AJAX requests
(`ajax-forms.js` / `pos.js` already send `X-Requested-With`).
Login lockout now responds `429`; bad credentials respond `401`.
**Files:** `views/errors/419.php`, `429.php`, `500.php`,
`api/419.php`, `api/429.php`, `api/500.php`, `src/Helpers/Csrf.php`,
`src/Controllers/AuthController.php`, `src/Auth/AuthService.php`,
`vercel.json`

#### 2.3 `audit.view` permission existed with no page behind it
**Problem:** Seeded since Phase 1 (`database/seed.sql`), but
`/audit` was never built — every `AuditLog::record()` call across
Phase 2/3/4 (user changes, tax rate changes, tax setting changes,
tax-invoice edits, expense changes) was write-only. Nobody could
actually review the trail. The current prompt explicitly names
`/audit` as an expected protected route, confirming this gap.
**Fix:** Built `AuditLogController` + `/audit` with filters (action,
entity type, user, date range), pagination, and an old/new-value
diff viewer. Granted to Admin/Owner (already implicit) and explicitly
to Accountant.
**Files:** `src/Controllers/AuditLogController.php`,
`src/Models/AuditLog.php` (added `paginate()`, `entityTypes()`),
`views/audit/index.php`, `api/audit.php`, `vercel.json`,
`views/layouts/sidebar.php`, `database/migration_005_hardening.sql`

#### 2.4 N+1 query on `/tax/settings`
**Problem:** `Tax::rateHistory($id)` was called once per row inside
a `foreach` over every tax type — one query per tax type instead of
one query total.
**Fix:** New `Tax::rateHistoryBatch(array $taxIds)` fetches every
tax's rate history in a single `IN (...)` query and groups the
results in PHP; `TaxController::settings()` now calls this once.
**Files:** `src/Models/Tax.php`, `src/Controllers/TaxController.php`,
`views/tax/settings.php`

#### 2.5 Missing composite indexes for Phase 2–4 report queries
**Problem:** `sales`/`purchases`/`stock_movements` had single-column
indexes on `status`/`created_at`/`product_id` separately, but the
actual report queries filter on **both together**
(`WHERE status IN (...) AND created_at BETWEEN ...`). MySQL can only
efficiently use one such index per table scan without a composite.
**Fix:** `migration_005_hardening.sql` adds:
- `sales(status, created_at)`
- `purchases(status, purchase_date)`
- `stock_movements(product_id, created_at)` — used by
  `Product::inventoryReport()`'s per-product stock-in/out subqueries
- `login_logs(username_attempted, created_at)` — had no index at all

#### 2.6 HTML-attribute escaping on pagination/export links
**Problem:** ~18 occurrences across report/list views built
`href="...?<?= http_build_query($_GET) ?>"` without escaping. Not
exploitable as XSS (`http_build_query` URL-encodes every value, so
`<`, `"`, `'` all become percent-escapes before reaching the HTML),
but the raw `&` separator between query parameters is not valid
inside an HTML attribute per spec.
**Fix:** Batch-wrapped every occurrence with `Response::e(...)`.
**Files:** 13 view files (customers, expenses, finance, products,
purchases, reports/*, sales, suppliers, tax/*)

#### 2.7 Modals could overflow unusably on short mobile viewports
**Problem:** `.azr-modal` had no `max-height`, and
`.azr-modal-backdrop` had no padding — a modal taller than the
viewport (e.g. the tax rate-history table, or the product form on a
landscape phone) had no way to scroll to reach its own Save button,
and touched the screen edges on very narrow devices.
**Fix:** Added `max-height: 90vh; overflow-y: auto` to `.azr-modal`
and `padding: 16px` to `.azr-modal-backdrop`.
**Files:** `public/assets/css/style.css`

---

### 🟢 Verified — reviewed and found already correct

- **SQL injection:** every model uses PDO prepared statements with
  bound parameters. Grepped every dynamic `$sql .=` builder across
  the codebase (`Category`, `Unit`, `ExpenseCategory`, `CashAccount`,
  `Tax`, and every report `buildWhere()`) — all appended fragments
  are hardcoded literals gated by booleans, never string-interpolated
  user input. Confirmed no model file touches `$_GET`/`$_POST`
  directly (clean controller → bound-parameter boundary).
- **Password handling:** `password_hash()` / `password_verify()` used
  correctly; no plaintext password ever logged or stored.
- **CSRF:** token comparison uses `hash_equals` (timing-safe); every
  state-changing `api/*.php` router calls `CsrfMiddleware::verify()`.
- **Session security:** DB-backed session handler (correct for
  serverless/Vercel — no filesystem session reliance), session ID
  regenerated on login, session data does not include the password
  hash.
- **Authorization enforcement point:** confirmed the prompt's own
  worked example — under the existing role grants, `cashier` has
  none of `users.view`, `tax.*`, `finance.*`, or `audit.view`, so
  `/users`, `/tax/settings`, `/audit`, `/finance` are already
  correctly blocked server-side (`PermissionMiddleware::require()`
  on every route, not just hidden in the UI).
- **Store-scoping on POS:** `store_id` for checkout and held carts is
  always derived server-side from `User::storeAccess()`
  (`primaryStoreId()`), never accepted from client input — closes
  the class of bug where a manipulated request could write a sale
  under a different store than the cashier is assigned to.
- **Production error/credential leakage:** `config/config.php` gates
  `display_errors` off unless `APP_DEBUG` is explicitly true;
  `Database::connect()` catches `PDOException` and returns a generic
  message, never the DSN/password/query.

---

## 2. Known limitation (documented, not fixed this pass)

**Login rate limiting is per-account, not per-IP.** `RateLimiter`
locks an *account* after N failed attempts (via `users.locked_until`),
which correctly stops a targeted attack on one username, but does not
stop an attacker who tries a handful of passwords against **many
different** usernames from one source. Recommend adding an
IP-address-based secondary limiter if this system will be
internet-facing without another layer (e.g. a WAF) in front of it.
Not implemented here because a correct implementation needs to
account for Vercel's proxy headers (`X-Forwarded-For` trust) to avoid
being trivially bypassed by a spoofed header, which is a bigger,
environment-specific decision better made with deployment details in
hand.

---

## 3. Files changed in this pass

**New files:**
- `src/Helpers/ErrorHandler.php`
- `src/Controllers/AuditLogController.php`
- `views/audit/index.php`, `views/users/edit_form.php`
- `views/errors/419.php`, `429.php`, `500.php`
- `api/audit.php`, `api/419.php`, `api/429.php`, `api/500.php`
- `api/users/edit.php`, `api/users/update.php`
- `database/migration_005_hardening.sql`
- `docs/AUDIT_REPORT.md` (this file), `docs/TESTING_CHECKLIST.md`

**Modified files:**
- `src/Auth/AuthService.php` — `refreshIfNeeded()`, 429 signal on lockout
- `src/Middleware/AuthMiddleware.php` — calls `refreshIfNeeded()`
- `src/Models/HeldCart.php` — store-scoped `resume()`/`discard()`
- `src/Controllers/PosController.php` — passes store id to HeldCart
- `src/Models/InvoiceNumber.php` — atomic counter
- `src/Models/User.php` — `roles()` now selects `id`; `emailExists()` accepts `$exceptId`
- `src/Models/AuditLog.php` — `paginate()`, `entityTypes()`
- `src/Models/Tax.php` — `rateHistoryBatch()`
- `src/Controllers/TaxController.php`, `views/tax/settings.php` — batch rate history
- `src/Controllers/UserController.php` — `editForm()`, `update()`
- `src/Helpers/Csrf.php` — proper 419 response
- `src/Controllers/AuthController.php` — 401/429 status codes
- `config/bootstrap.php` — registers `ErrorHandler`
- `views/layouts/sidebar.php` — Audit Log link
- `views/users/index.php` — Edit button, working confirm() on toggle-status
- `public/assets/css/style.css` — modal max-height/padding
- 13 view files — `Response::e()` around `http_build_query()`
- `database/migration_005_hardening.sql` — composite indexes + `audit.view` grant

**Database migration to run (in order, if not already applied):**
```
schema.sql → seed.sql → migration_002_pos.sql → migration_003_finance.sql
→ migration_004_tax.sql → migration_005_hardening.sql
```
`migration_005_hardening.sql` is purely additive (indexes + one
permission grant) and safe to run against a live database with
existing data.

---

# PHASE 5 — Finalisasi & Deployment Audit

Audit ini memeriksa seluruh 22 modul yang diminta, kebersihan routing, kesiapan
multi-store, environment, konfigurasi Vercel, dokumentasi MySQL, seed
development, dan checklist production.

## 1. Status Modul (22 modul yang diminta)

| # | Modul | Status sebelum Phase 5 | Aksi Phase 5 |
|---|---|---|---|
| 1 | Login | ✅ Lengkap | - |
| 2 | Dashboard | ✅ Lengkap, berdiri sendiri di `/dashboard` | Redirect login diarahkan ke `/dashboard` (bukan `/dashboard.php`) |
| 3 | User Management | ✅ Lengkap | Route bersih `/users` ditambahkan |
| 4 | Role | 🔴 Tidak ada UI (hanya tabel DB + dipakai saat assign ke user) | **Dibangun baru:** `RoleController`, `/roles`, matrix izin per role |
| 5 | Permission | 🔴 Tidak ada UI | **Dibangun baru:** `PermissionController`, `/permissions` (katalog read-only + matrix role×izin) |
| 6 | Kasir (POS) | ✅ Lengkap, terpisah dari dashboard | Route bersih `/pos`; bug CSRF (lihat §3) diperbaiki |
| 7 | Penjualan | ✅ Lengkap | Route bersih `/sales` |
| 8 | Retur Penjualan | ✅ Lengkap (`/sales/return-form.php`) | - |
| 9 | Produk | ✅ Lengkap | Route bersih `/products` |
| 10 | Kategori | ✅ Lengkap | - |
| 11 | Inventory | ✅ Lengkap | Route bersih `/inventory` |
| 12 | Pembelian | ✅ Lengkap | Route bersih `/purchases`; bug dynamic-row JS (lihat §3) |
| 13 | Retur Pembelian | ✅ Lengkap (`/purchases/return-form.php`) | - |
| 14 | Customer | ✅ Lengkap | Route bersih `/customers` |
| 15 | Supplier | ✅ Lengkap | Route bersih `/suppliers` |
| 16 | Keuangan | ✅ Lengkap (`/finance`) | - |
| 17 | HPP | 🟡 Hanya angka agregat di Laba Rugi | **Dibangun baru:** `/reports/hpp` — rincian per produk (qty, rata-rata HPP, laba kotor) |
| 18 | Laba Rugi | ✅ Lengkap (`/finance/profit-loss`) | - |
| 19 | Cash Flow | ✅ Lengkap (`/finance/cash-flow`) | - |
| 20 | Perpajakan | ✅ Lengkap (`/tax`) | - |
| 21 | Laporan | 🟡 Ada per jenis, tidak ada halaman induk | **Dibangun baru:** `/reports` — hub yang menautkan semua jenis laporan |
| 22 | Audit Log | ✅ Lengkap (`/audit`) | - |

**Tambahan di luar 22 modul (diperlukan untuk kelengkapan Multi Store & Pengaturan):**
- **Toko/Cabang** — sebelumnya tabel `stores` ada tapi **tidak ada UI sama sekali** untuk
  menambah toko kedua; hanya bisa lewat DB manual. Dibangun `StoreController` +
  `/settings/stores.php`.
- **Pengaturan (Settings)** — sidebar punya link mati (`href="#"`, label "Pengaturan Toko")
  yang tidak terhubung ke apa pun. Dibangun `SettingsController` + `/settings`, dengan
  key/value `app_settings` yang benar-benar dipakai (nama badan usaha & catatan kaki struk
  tampil di invoice/struk; catatan stok menipis tampil di dashboard) — bukan pengaturan
  kosmetik yang tidak berefek.

## 2. Routing Final

Route bersih (tanpa akhiran `.php`) ditambahkan di `vercel.json` persis sesuai daftar yang
diminta: `/dashboard /pos /sales /purchases /products /inventory /customers /suppliers
/finance /tax /reports /users /settings`, ditambah `/roles` dan `/permissions`.

Route `.php` lama (`/dashboard.php`, `/products/index.php`, dst.) **tetap dipertahankan**
sebagai alias — keduanya mengarah ke file `api/*.php` yang sama persis, tidak ada logika
yang diduplikasi. Ini keputusan sadar: mengganti seluruh ~150+ tautan internal di semua
view ke bentuk bersih adalah pekerjaan besar berisiko tinggi salah-ganti di luar cakupan
finalisasi ini. **Yang sudah diarahkan ke route bersih:** redirect login, sidebar (navigasi
utama), dan seluruh halaman/modul baru Phase 5. Tautan aksi (create/store/update/destroy)
sengaja tetap `.php` mengikuti pola yang sudah konsisten di seluruh aplikasi.

Otorisasi tetap aktif di server: setiap route baru diperiksa lewat `PermissionMiddleware`
sebelum controller dipanggil, sama seperti seluruh endpoint lain.

## 3. Bug Nyata yang Ditemukan & Diperbaiki (bukan kosmetik)

`config/bootstrap.php` mengirim header CSP `script-src 'self'` (tanpa `'unsafe-inline'`) —
kebijakan yang benar secara keamanan. Namun **8 file view** masih memakai `<script>` inline
atau atribut `onclick=`/`onsubmit=` inline, yang **diblokir oleh browser modern apa pun yang
menegakkan CSP tersebut**, membuat beberapa fitur diam-diam rusak di production:

- `views/pos/index.php` — CSRF token POS di-set lewat inline `<script>` → **transaksi kasir
  bisa gagal submit** karena `pos.js` membaca `window.AZR_CSRF` yang tidak pernah ter-set.
- `views/products/form.php` — autofill tarif pajak saat pilih jenis pajak tidak jalan.
- `views/purchases/form.php` — baris item/pembayaran dinamis pada form pembelian tidak
  bisa ditambah.
- `views/sales/show.php` (×2), `views/finance/_period_filter.php`, dan 6 halaman laporan/
  pajak — auto-print struk & tombol "Cetak" tidak berfungsi.
- `views/users/index.php` — dialog konfirmasi sebelum ubah status pengguna tidak muncul.

**Perbaikan:** seluruh inline script/handler dipindah ke `public/assets/js/app.js` (delegated,
presence-guarded) atau file eksternal baru (`purchases-form.js`), CSRF token dipindah ke
`<meta>` tag yang dibaca `pos.js`/`ajax-forms.js` langsung. **CSP tidak dilonggarkan** —
tidak ada `'unsafe-inline'` ditambahkan; perbaikan mengikuti kebijakan yang sudah benar,
bukan melemahkannya. Terverifikasi: `grep` untuk `<script>` inline dan `onclick=`/`onsubmit=`
di seluruh `views/` sekarang nihil.

## 4. Keterbatasan Arsitektur yang Diketahui (belum diperbaiki — butuh keputusan produk)

**Stok saat ini bersifat GLOBAL per produk, bukan per toko.** Kolom `products.stock` adalah
satu angka tunggal; `stock_movements` dan `stock_transfers` memang mencatat `store_id`
(riwayat pergerakan/transfer), tapi **tidak ada tabel `product_store_stock` terpisah** —
sehingga dua toko yang menjual produk yang sama akan mengurangi angka stok global yang sama.
Transaksi, laporan, dan hak akses per toko (`user_store_access`) sudah benar diimplementasikan
dan difilter dengan `store_id` di query — hanya **level stok** yang belum benar-benar
terpisah per toko.

Memperbaiki ini dengan aman memerlukan migrasi skema (tabel stok per toko) dan perubahan
logika di setiap titik yang membaca/menulis `products.stock` (POS checkout, pembelian,
retur, penyesuaian stok, transfer) — sebuah perubahan yang menyentuh banyak file transaksi
sekaligus dan berisiko tinggi jika dikerjakan tergesa di luar sesi audit ini. **Rekomendasi:**
jangan aktifkan lebih dari satu toko di production sampai perbaikan ini dikerjakan sebagai
pekerjaan tersendiri dengan pengujian migrasi stok yang memadai, atau operasikan sebagai
single-store untuk saat ini.

## 5. File Baru (Phase 5)

**Model:** `src/Models/Permission.php`, `src/Models/AppSetting.php`; `src/Models/Store.php`
dan `src/Models/Role.php` diperluas dengan CRUD penuh.
**Controller:** `RoleController`, `PermissionController`, `StoreController`,
`SettingsController`; `ReportController` dan `DashboardController` diperluas.
**API:** `api/roles/*`, `api/permissions/index.php`, `api/stores/*`, `api/settings/*`,
`api/reports/{hpp,hpp-export,index}.php`.
**View:** `views/roles/{index,permissions}.php`, `views/permissions/index.php`,
`views/settings/{index,stores}.php`, `views/reports/{hpp,index}.php`.
**Database:** `database/migration_006_settings.sql` (tabel `app_settings`, izin
`roles.manage`/`stores.manage`), `database/seed_dev.sql` (data pengembangan).
**JS:** `public/assets/js/purchases-form.js`.
**Config:** `vercel.json` (route bersih), `.env.example` (sudah lengkap, tidak diubah).

## 6. Urutan Migrasi Final

```
schema.sql → seed.sql → migration_002_pos.sql → migration_003_finance.sql
→ migration_004_tax.sql → migration_005_hardening.sql → migration_006_settings.sql
→ (opsional, dev only) seed_dev.sql
```
`migration_006_settings.sql` murni aditif (tabel baru + izin baru) — aman dijalankan di
database production yang sudah berisi data.
