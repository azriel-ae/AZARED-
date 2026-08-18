<?php
use App\Helpers\Csrf;
use App\Helpers\Response;

$pageTitle = 'Periode Pajak';
$activeMenu = 'tax';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => '/dashboard.php'],
    ['label' => 'Perpajakan', 'url' => '/tax'],
    ['label' => 'Periode'],
];

require __DIR__ . '/../layouts/main_top.php';
?>

<div class="azr-alert azr-alert-info" style="margin-bottom:18px;">
    Menutup sebuah periode akan mengunci nomor &amp; status faktur pajak untuk transaksi pada rentang tanggal tersebut agar tidak berubah setelah dilaporkan.
</div>

<div class="azr-card">
    <div class="azr-card-header">
        <h2 class="azr-card-title">Periode Pajak</h2>
        <div style="display:flex;gap:8px;">
            <a href="/tax" class="azr-btn azr-btn-outline">&larr; Kembali</a>
            <button type="button" class="azr-btn azr-btn-primary" data-azr-modal-open="periodCreateModal">+ Buat Periode</button>
        </div>
    </div>

    <div class="azr-table-wrap">
        <table class="azr-table">
            <thead><tr><th>Nama Periode</th><th>Tipe</th><th>Rentang Tanggal</th><th>Status</th><th>Ditutup Oleh</th><th style="text-align:right;">Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($periods as $p): ?>
                <tr>
                    <td><?= Response::e($p['name']) ?></td>
                    <td><?= $p['period_type'] === 'monthly' ? 'Bulanan' : 'Tahunan' ?></td>
                    <td><?= Response::e($p['start_date']) ?> s/d <?= Response::e($p['end_date']) ?></td>
                    <td>
                        <span class="azr-badge <?= $p['status'] === 'open' ? 'azr-badge-active' : 'azr-badge-inactive' ?>">
                            <?= $p['status'] === 'open' ? 'Terbuka' : 'Ditutup' ?>
                        </span>
                    </td>
                    <td><?= Response::e($p['closed_by_name'] ?: '-') ?></td>
                    <td style="text-align:right;">
                        <?php if ($p['status'] === 'open'): ?>
                        <button type="button" class="azr-btn azr-btn-outline azr-btn-sm"
                                data-azr-ajax-action="/tax/close-period.php?id=<?= (int) $p['id'] ?>"
                                data-azr-confirm="Tutup periode '<?= Response::e($p['name']) ?>'? Data faktur pada rentang ini akan terkunci.">Tutup Periode</button>
                        <?php else: ?>
                        <button type="button" class="azr-btn azr-btn-outline azr-btn-sm"
                                data-azr-ajax-action="/tax/reopen-period.php?id=<?= (int) $p['id'] ?>"
                                data-azr-confirm="Buka kembali periode '<?= Response::e($p['name']) ?>'?">Buka Kembali</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($periods)): ?>
                <tr><td colspan="6" style="text-align:center;color:var(--azr-gray-600);">Belum ada periode pajak.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="azr-modal-backdrop" id="periodCreateModal">
    <div class="azr-modal">
        <h3 class="azr-modal-title">Buat Periode Pajak</h3>
        <form action="/tax/store-period.php" method="post" data-azr-ajax>
            <?= Csrf::field() ?>
            <div class="azr-form-group">
                <label class="azr-label">Nama Periode</label>
                <input class="azr-input" type="text" name="name" required maxlength="100" placeholder="mis. Agustus 2026">
                <p class="azr-error-text" data-azr-error="name"></p>
            </div>
            <div class="azr-form-group">
                <label class="azr-label">Tipe Periode</label>
                <select class="azr-select" name="period_type">
                    <option value="monthly">Bulanan</option>
                    <option value="yearly">Tahunan</option>
                </select>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                <div class="azr-form-group">
                    <label class="azr-label">Tanggal Mulai</label>
                    <input class="azr-input" type="date" name="start_date" required>
                </div>
                <div class="azr-form-group">
                    <label class="azr-label">Tanggal Akhir</label>
                    <input class="azr-input" type="date" name="end_date" required>
                </div>
            </div>
            <div class="azr-modal-actions">
                <button type="button" class="azr-btn azr-btn-outline" data-azr-modal-close>Batal</button>
                <button type="submit" class="azr-btn azr-btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../layouts/main_bottom.php'; ?>
