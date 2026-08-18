<?php
use App\Helpers\Response;

$fullName = (string) ($_SESSION['full_name'] ?? '');
$initials = '';
foreach (explode(' ', trim($fullName)) as $part) {
    if ($part !== '') { $initials .= strtoupper($part[0]); }
    if (mb_strlen($initials) >= 2) break;
}
$roles = $_SESSION['roles'] ?? [];
?>
<header class="azr-topbar">
    <div style="display:flex;align-items:center;gap:12px;">
        <button class="azr-menu-toggle" data-azr-menu-toggle aria-label="Toggle menu">&#9776;</button>
        <?php if (!empty($breadcrumb)): ?>
        <nav class="azr-breadcrumb" style="margin:0;">
            <?php foreach ($breadcrumb as $i => $item): ?>
                <?php if ($i > 0): ?> / <?php endif; ?>
                <?php if (!empty($item['url']) && $i < count($breadcrumb) - 1): ?>
                    <a href="<?= Response::e($item['url']) ?>"><?= Response::e($item['label']) ?></a>
                <?php else: ?>
                    <span class="current"><?= Response::e($item['label']) ?></span>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
        <?php endif; ?>
    </div>
    <div class="azr-topbar-user">
        <div style="text-align:right;line-height:1.2;">
            <div style="font-weight:700;font-size:0.86rem;"><?= Response::e($fullName) ?></div>
            <div style="font-size:0.74rem;color:var(--azr-gray-600);"><?= Response::e(implode(', ', $roles)) ?></div>
        </div>
        <div class="azr-avatar"><?= Response::e($initials ?: '?') ?></div>
        <form action="/logout.php" method="post" style="margin:0;">
            <?= \App\Helpers\Csrf::field() ?>
            <button type="submit" class="azr-btn azr-btn-outline azr-btn-sm">Keluar</button>
        </form>
    </div>
</header>
