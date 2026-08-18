<?php
use App\Helpers\Csrf;
use App\Helpers\Response;

$error = $error ?? null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login · AZARED</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="azr-auth-wrap">
    <div class="azr-auth-card">
        <div class="azr-brand" style="justify-content:center;">
            <div class="azr-brand-mark">AZ</div>
            <span>AZARED</span>
        </div>
        <p class="azr-auth-subtitle">Aplikasi Kasir &amp; Manajemen Toko</p>

        <?php if ($error): ?>
        <div class="azr-alert azr-alert-error"><?= Response::e($error) ?></div>
        <?php endif; ?>

        <form action="/login.php" method="post" novalidate>
            <?= Csrf::field() ?>
            <div class="azr-form-group">
                <label class="azr-label" for="username">Username</label>
                <input class="azr-input" type="text" id="username" name="username" autocomplete="username" required autofocus>
            </div>
            <div class="azr-form-group">
                <label class="azr-label" for="password">Password</label>
                <input class="azr-input" type="password" id="password" name="password" autocomplete="current-password" required>
            </div>
            <button type="submit" class="azr-btn azr-btn-primary" style="width:100%;justify-content:center;">Masuk</button>
        </form>
    </div>
</div>
</body>
</html>
