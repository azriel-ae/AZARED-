# AZARED — Panduan MySQL (Setup, Migration, Seed, Backup, Restore)

Dokumen ini melengkapi `README.md` dengan detail teknis khusus database.
Berlaku untuk MySQL 8.x atau MariaDB 10.6+.

---

## 1. Membuat Database & User Database

Jangan pernah menjalankan aplikasi dengan user `root`. Buat database dan user
khusus dengan hak akses terbatas hanya ke database AZARED:

```sql
CREATE DATABASE azared CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER 'azared_user'@'%' IDENTIFIED BY 'GANTI_DENGAN_PASSWORD_KUAT';
GRANT SELECT, INSERT, UPDATE, DELETE ON azared.* TO 'azared_user'@'%';
FLUSH PRIVILEGES;
```

Catatan:
- Ganti `'%'` dengan host spesifik (mis. IP aplikasi) jika MySQL diakses dari luar,
  agar tidak terbuka ke seluruh internet.
- User aplikasi **tidak** diberi `CREATE`/`DROP`/`ALTER` — jalankan migrasi skema
  memakai user admin terpisah, lalu aplikasi berjalan dengan `azared_user` yang
  haknya dibatasi (defense-in-depth: kalau ada bug SQL injection pun, dampaknya
  tidak bisa mengubah struktur tabel).
- Isi `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` di `.env`
  (lokal) atau Environment Variables Vercel (production) sesuai kredensial ini.
  **Jangan pernah hardcode kredensial di kode.**

---

## 2. Menjalankan Schema & Migration

Urutan **wajib** dijalankan berurutan (setiap file bergantung pada tabel dari file
sebelumnya):

```bash
mysql -u azared_admin -p azared < database/schema.sql
mysql -u azared_admin -p azared < database/seed.sql
mysql -u azared_admin -p azared < database/migration_002_pos.sql
mysql -u azared_admin -p azared < database/migration_003_finance.sql
mysql -u azared_admin -p azared < database/migration_004_tax.sql
mysql -u azared_admin -p azared < database/migration_005_hardening.sql
mysql -u azared_admin -p azared < database/migration_006_settings.sql
```

Setiap `migration_*.sql` ditulis **idempoten dan aditif**:
- `CREATE TABLE IF NOT EXISTS` — aman dijalankan ulang.
- `INSERT ... ON DUPLICATE KEY UPDATE` — aman dijalankan ulang, tidak menduplikasi baris.
- Tidak ada `DROP TABLE`/`DROP COLUMN` di manapun — aman dijalankan terhadap
  database production yang sudah berisi data transaksi.

Setelah semua migrasi di atas, **opsional** untuk development lokal:

```bash
mysql -u azared_admin -p azared < database/seed_dev.sql
```

> ⚠️ **Jangan pernah menjalankan `seed_dev.sql` di database production.** Berisi
> akun demo dengan password yang didokumentasikan publik dan data produk/pelanggan
> fiktif. Lihat komentar di kepala file tersebut.

### Cara memverifikasi migrasi berhasil

```sql
SHOW TABLES;                              -- harus ada 25+ tabel
SELECT COUNT(*) FROM permissions;         -- harus > 30 baris setelah migration_006
SELECT COUNT(*) FROM roles WHERE is_system = 1;  -- harus 6 (admin/owner/manager/cashier/accountant/tax_officer)
SELECT username FROM users;               -- minimal ada 'admin'
```

---

## 3. Backup

### Backup logis (rekomendasi — portable, mudah di-restore sebagian)

```bash
mysqldump -u azared_admin -p \
  --single-transaction --routines --triggers \
  azared > azared-backup-$(date +%Y%m%d-%H%M%S).sql
```

- `--single-transaction` — snapshot konsisten tanpa mengunci tabel InnoDB (tidak
  mengganggu transaksi kasir yang sedang berjalan).
- Simpan hasil backup di luar server aplikasi (S3, Google Cloud Storage, atau
  storage terenkripsi lain), **bukan** hanya di disk lokal server yang sama.
- Jadwalkan otomatis (cron/systemd timer di server MySQL Anda, atau fitur backup
  otomatis bawaan provider terkelola seperti PlanetScale/RDS/Railway) — minimal
  harian untuk data transaksi aktif.

### Backup fisik (untuk database besar / minim downtime saat restore)

Gunakan `mysqlpump`, `mydumper`, atau snapshot volume dari provider terkelola Anda
(mis. PlanetScale branching, RDS automated snapshots) jika ukuran database sudah
besar dan `mysqldump` terasa lambat.

---

## 4. Restore

```bash
mysql -u azared_admin -p azared < azared-backup-20260818-020000.sql
```

Untuk restore ke database yang **belum ada**:

```bash
mysql -u azared_admin -p -e "CREATE DATABASE azared CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u azared_admin -p azared < azared-backup-20260818-020000.sql
```

**Sebelum restore ke production**, selalu:
1. Ambil backup dari kondisi saat ini terlebih dahulu (jaga-jaga).
2. Uji restore di database staging/lokal dulu, verifikasi data & jalankan aplikasi.
3. Jadwalkan restore production di luar jam sibuk toko.

---

## 5. Koneksi via Environment Variables

`config/database.php` **selalu** membaca kredensial dari environment
(`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_SSL`) lewat
`config()` — tidak ada kredensial yang ditulis langsung di kode di manapun dalam
project ini. Jika MySQL Anda mewajibkan TLS (umum di provider terkelola), set
`DB_SSL=true` dan letakkan sertifikat CA provider Anda di `config/certs/ca-cert.pem`.

---

## 6. Multi-Store — Catatan Penting

Transaksi, laporan, dan hak akses pengguna sudah difilter per `store_id` dengan
benar. **Namun level stok (`products.stock`) saat ini masih global, belum
dipisah per toko** — lihat `docs/AUDIT_REPORT.md` bagian "Keterbatasan Arsitektur
yang Diketahui" sebelum mengaktifkan lebih dari satu toko aktif di production.
