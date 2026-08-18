<?php
use App\Helpers\Response;

$pageTitle = 'Audit Log';
$activeMenu = 'audit';
$breadcrumb = [['label' => 'Dashboard', 'url' => '/dashboard.php'], ['label' => 'Audit Log']];
$page = max(1, (int) ($_GET['page'] ?? 1));

require __DIR__ . '/../layouts/main_top.php';
?>

<div class="azr-alert azr-alert-info" style="margin-bottom:18px;">
    Catatan seluruh perubahan sensitif di sistem: pengguna, role, pengaturan pajak, tarif pajak, dan data transaksi pajak. Data ini bersifat <strong>append-only</strong> (tidak pernah diubah/dihapus dari aplikasi).
</div>

<div class="azr-filter-bar">
    <form method="get" action="/audit" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;flex:1;">
        <div class="azr-form-group">
            <label class="azr-label">Aksi</label>
            <input class="azr-input" type="text" name="action" placeholder="mis. user.update" value="<?= Response::e($_GET['action'] ?? '') ?>">
        </div>
        <div class="azr-form-group">
            <label class="azr-label">Jenis Entitas</label>
            <select class="azr-select" name="entity_type">
                <option value="">Semua</option>
                <?php foreach ($entityTypes as $et): ?>
                    <option value="<?= Response::e($et) ?>" <?= ($_GET['entity_type'] ?? '') === $et ? 'selected' : '' ?>><?= Response::e($et) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="azr-form-group">
            <label class="azr-label">Pengguna</label>
            <select class="azr-select" name="user_id">
                <option value="">Semua</option>
                <?php foreach ($users as $u): ?>
                    <option value="<?= (int) $u['id'] ?>" <?= (int) ($_GET['user_id'] ?? 0) === (int) $u['id'] ? 'selected' : '' ?>><?= Response::e($u['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="azr-form-group">
            <label class="azr-label">Dari Tanggal</label>
            <input class="azr-input" type="date" name="date_from" value="<?= Response::e($_GET['date_from'] ?? '') ?>">
        </div>
        <div class="azr-form-group">
            <label class="azr-label">Sampai Tanggal</label>
            <input class="azr-input" type="date" name="date_to" value="<?= Response::e($_GET['date_to'] ?? '') ?>">
        </div>
        <button type="submit" class="azr-btn azr-btn-primary">Filter</button>
        <a href="/audit" class="azr-btn azr-btn-outline">Reset</a>
    </form>
</div>

<div class="azr-card">
    <div class="azr-card-header">
        <h2 class="azr-card-title">Riwayat Aktivitas <span style="color:var(--azr-gray-600);font-weight:400;">(<?= (int) $total ?>)</span></h2>
    </div>
    <div class="azr-table-wrap">
        <table class="azr-table">
            <thead><tr><th>Waktu</th><th>Pengguna</th><th>Aksi</th><th>Entitas</th><th>ID</th><th>IP</th><th>Detail</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td style="white-space:nowrap;"><?= Response::e($log['created_at']) ?></td>
                    <td><?= Response::e($log['actor_name'] ?: 'Sistem') ?></td>
                    <td><span class="azr-badge azr-badge-info"><?= Response::e($log['action']) ?></span></td>
                    <td><?= Response::e($log['entity_type']) ?></td>
                    <td><?= $log['entity_id'] !== null ? (int) $log['entity_id'] : '-' ?></td>
                    <td><?= Response::e($log['ip_address'] ?: '-') ?></td>
                    <td>
                        <?php if ($log['old_values'] || $log['new_values']): ?>
                        <button type="button" class="azr-btn-link" data-azr-modal-open="auditDetail<?= (int) $log['id'] ?>">Lihat</button>
                        <?php else: ?>
                        -
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if ($log['old_values'] || $log['new_values']): ?>
                <div class="azr-modal-backdrop" id="auditDetail<?= (int) $log['id'] ?>">
                    <div class="azr-modal">
                        <h3 class="azr-modal-title">Detail Perubahan - <?= Response::e($log['action']) ?></h3>
                        <?php if ($log['old_values']): ?>
                        <p class="azr-label">Sebelum</p>
                        <pre style="background:var(--azr-gray-100);padding:10px;border-radius:8px;font-size:0.78rem;overflow-x:auto;"><?= Response::e(json_encode(json_decode($log['old_values'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                        <?php endif; ?>
                        <?php if ($log['new_values']): ?>
                        <p class="azr-label">Sesudah</p>
                        <pre style="background:var(--azr-gray-100);padding:10px;border-radius:8px;font-size:0.78rem;overflow-x:auto;"><?= Response::e(json_encode(json_decode($log['new_values'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                        <?php endif; ?>
                        <div class="azr-modal-actions">
                            <button type="button" class="azr-btn azr-btn-outline" data-azr-modal-close>Tutup</button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php if (empty($logs)): ?>
                <tr><td colspan="7" style="text-align:center;color:var(--azr-gray-600);">Tidak ada aktivitas yang cocok dengan filter.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="azr-pagination">
        <?php $qs = $_GET; for ($i = 1; $i <= $totalPages; $i++): $qs['page'] = $i; ?>
            <a href="/audit?<?= Response::e(http_build_query($qs)) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../layouts/main_bottom.php'; ?>
