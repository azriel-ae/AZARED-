<?php
use App\Auth\AuthService;
use App\Helpers\Response;

$pageTitle = 'Detail Pengeluaran';
$activeMenu = 'expenses';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => '/dashboard.php'],
    ['label' => 'Pengeluaran', 'url' => '/expenses/index.php'],
    ['label' => $expense['expense_no']],
];
$payLabel = ['cash' => 'Tunai', 'transfer' => 'Transfer', 'debit' => 'Kartu Debit', 'credit' => 'Kartu Kredit', 'ewallet' => 'E-Wallet', 'qris' => 'QRIS', 'other' => 'Lainnya'];

require __DIR__ . '/../layouts/main_top.php';
?>

<div class="azr-card" style="max-width:640px;">
    <div class="azr-card-header">
        <h2 class="azr-card-title"><?= Response::e($expense['expense_no']) ?></h2>
        <div style="display:flex;gap:8px;">
            <?php if (AuthService::hasPermission('expenses.edit')): ?>
            <a href="/expenses/edit.php?id=<?= (int) $expense['id'] ?>" class="azr-btn azr-btn-outline azr-btn-sm">Edit</a>
            <?php endif; ?>
            <a href="/expenses/index.php" class="azr-btn azr-btn-outline azr-btn-sm">&larr; Kembali</a>
        </div>
    </div>
    <table class="azr-table">
        <tr><td style="color:var(--azr-gray-600);">Kategori</td><td><span class="azr-badge azr-badge-info"><?= Response::e($expense['category_name']) ?></span></td></tr>
        <tr><td style="color:var(--azr-gray-600);">Deskripsi</td><td><?= Response::e($expense['description']) ?></td></tr>
        <tr><td style="color:var(--azr-gray-600);">Jumlah</td><td style="font-weight:700;">Rp <?= number_format((float) $expense['amount'], 0, ',', '.') ?></td></tr>
        <tr><td style="color:var(--azr-gray-600);">Tanggal</td><td><?= Response::e($expense['expense_date']) ?></td></tr>
        <tr><td style="color:var(--azr-gray-600);">Metode Pembayaran</td><td><?= $payLabel[$expense['payment_method']] ?? Response::e($expense['payment_method']) ?></td></tr>
        <tr><td style="color:var(--azr-gray-600);">Dicatat Oleh</td><td><?= Response::e($expense['user_name'] ?: '-') ?></td></tr>
        <tr><td style="color:var(--azr-gray-600);">Dibuat</td><td><?= Response::e($expense['created_at']) ?></td></tr>
        <tr><td style="color:var(--azr-gray-600);">Catatan</td><td><?= nl2br(Response::e($expense['notes'] ?: '-')) ?></td></tr>
    </table>
</div>

<?php require __DIR__ . '/../layouts/main_bottom.php'; ?>
