# AZARED — Production Checklist

Centang setiap butir sebelum mengarahkan traffic nyata (pelanggan/kasir toko)
ke deployment production.

## Database

- [ ] MySQL dijalankan di provider terkelola yang bisa diakses dari Vercel
      (PlanetScale / AWS RDS / Railway / DigitalOcean Managed MySQL, dll) —
      bukan di laptop/server lokal.
- [ ] User database aplikasi **bukan** `root`, hak akses dibatasi ke `SELECT,
      INSERT, UPDATE, DELETE` saja (lihat `docs/MYSQL_SETUP.md` §1).
- [ ] `schema.sql` → `seed.sql` → `migration_002` s.d. `migration_006` sudah
      dijalankan berurutan dan diverifikasi (lihat `docs/MYSQL_SETUP.md` §2).
- [ ] `seed_dev.sql` **TIDAK** dijalankan di database ini.
- [ ] Password akun `admin` default (`Azared#2026!`) sudah diganti segera
      setelah deploy pertama (kolom `must_change_password` sudah memaksa ini
      di login pertama).
- [ ] Backup otomatis terjadwal aktif (lihat `docs/MYSQL_SETUP.md` §3), dan
      sudah **diuji restore** minimal sekali di lingkungan staging.
- [ ] `DB_SSL=true` diaktifkan jika provider mewajibkan TLS (umum), sertifikat
      CA sudah terpasang di `config/certs/ca-cert.pem`.

## Environment Variables (Vercel Dashboard → Settings → Environment Variables)

- [ ] `APP_ENV=production`, `APP_DEBUG=false` (jangan pernah `true` di
      production — akan membocorkan stack trace ke pengguna).
- [ ] `APP_URL` diisi domain production sesungguhnya (bukan `*.vercel.app`
      default jika Anda memakai domain kustom).
- [ ] `APP_KEY` diisi string acak unik ≥32 karakter, **berbeda** dari nilai
      contoh di `.env.example`.
- [ ] `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` sesuai
      kredensial production (bukan kredensial lokal/staging).
- [ ] Tidak ada satu pun kredensial yang di-commit ke `.env` di repository git.
      Jalankan `git log --all -- .env` untuk memastikan `.env` asli tidak
      pernah ter-commit; `.env.example` boleh, `.env` tidak boleh.

## HTTPS

- [ ] Domain production diakses hanya lewat HTTPS (Vercel menyediakan TLS
      otomatis untuk domain `*.vercel.app` dan domain kustom yang terverifikasi
      — tidak perlu konfigurasi tambahan, tapi pastikan tidak ada tautan
      internal yang memaksa `http://`).

## Keamanan Aplikasi (sudah diimplementasikan — verifikasi masih aktif)

- [ ] CSP `script-src 'self'` di `config/bootstrap.php` masih aktif dan tidak
      ada `'unsafe-inline'` yang ditambahkan (lihat Phase 5 audit — semua
      inline script sudah dipindah ke file eksternal justru agar CSP ketat
      ini bisa dipertahankan).
- [ ] `CsrfMiddleware::verify()` dipanggil di setiap endpoint `POST`.
- [ ] `PermissionMiddleware::require()` dipanggil di setiap endpoint yang
      butuh otorisasi — **tidak ada** keputusan akses yang hanya mengandalkan
      UI (tombol disembunyikan bukan proteksi; server selalu memeriksa ulang).
- [ ] Password disimpan dengan `password_hash()` (bcrypt) — tidak pernah
      plaintext, tidak pernah hashing custom.
- [ ] Session disimpan di MySQL via `PdoSessionHandler` (wajib untuk
      lingkungan serverless — lihat README §6), bukan filesystem lokal.
- [ ] Rate limiting login (`LOGIN_MAX_ATTEMPTS`, `LOGIN_LOCKOUT_MINUTES`) aktif
      dan diuji.

## Authorization

- [ ] Role bawaan sistem (admin/owner/manager/cashier/accountant/tax_officer)
      sudah direview — pastikan hanya orang yang tepat diberi role `admin`
      atau `owner`.
- [ ] Untuk role kustom tambahan (lewat modul Role & Akses baru), izin yang
      diberikan sudah diverifikasi lewat halaman `/permissions` (katalog
      read-only) sebelum role tersebut dipakai pengguna sungguhan.
- [ ] Setiap akun pengguna production sudah diberi akses toko (`user_store_access`)
      yang sesuai — jangan beri akses ke semua toko secara default.

## Tax Configuration (Perpajakan)

- [ ] Tarif PPN/pajak lain di `/tax/settings` sudah sesuai regulasi yang
      berlaku saat go-live (jangan pakai data contoh dari `seed_dev.sql`).
- [ ] Periode pajak (`tax_periods`) sudah dikonfigurasi sesuai siklus
      pembukuan bisnis (bulanan/tahunan).
- [ ] NPWP toko (di modul Toko/Cabang, `/settings/stores.php`) sudah diisi
      dengan NPWP sungguhan, bukan data contoh.

## Store Configuration (Multi Store)

- [ ] **Baca `docs/AUDIT_REPORT.md` bagian "Keterbatasan Arsitektur yang
      Diketahui" sebelum mengaktifkan lebih dari satu toko aktif** — stok
      produk saat ini global, belum dipisah per toko.
- [ ] Jika hanya beroperasi single-store: nonaktifkan/jangan buat toko kedua
      sampai perbaikan stok per-toko dikerjakan.
- [ ] Data toko (nama, alamat, NPWP) di `/settings/stores.php` sudah data
      sungguhan, bukan data contoh dari `seed_dev.sql`.

## Error Logging

- [ ] `APP_DEBUG=false` memastikan pengguna akhir tidak melihat stack trace
      (halaman error generik `500.php` yang ditampilkan alih-alih).
- [ ] Pastikan ada mekanisme log error sisi server yang Anda pantau (log
      Vercel Functions di dashboard, atau integrasi eksternal seperti Sentry
      jika dibutuhkan — belum terpasang bawaan di project ini).

## Data Real (bukan dummy)

- [ ] Seluruh transaksi (penjualan, pembelian, retur) tersimpan di MySQL —
      **tidak ada** mode "demo"/in-memory yang mem-bypass database di kode
      aplikasi manapun (`src/Models/*` seluruhnya menulis lewat PDO ke MySQL).
- [ ] Seluruh laporan (Laporan Penjualan/Pembelian/Inventory/HPP/Laba Rugi/
      Cash Flow/Pajak) mengambil data langsung dari query MySQL real-time —
      tidak ada angka yang di-hardcode di view.
- [ ] Kategori, produk, pelanggan, dan supplier dari `seed_dev.sql` **dihapus
      atau diganti** dengan data bisnis sungguhan sebelum go-live, jika
      `seed_dev.sql` sempat dijalankan di database yang akan dipakai production.
