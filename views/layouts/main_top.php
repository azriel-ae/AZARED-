<?php
/**
 * Include at the TOP of every authenticated page, then close with
 * views/layouts/main_bottom.php. Expects optional:
 *   $pageTitle   (string)
 *   $activeMenu  (string) matches sidebar.php keys
 *   $breadcrumb  (array<int, array{label:string, url?:string}>)
 */
use App\Helpers\Csrf;
use App\Helpers\Response;

$pageTitle = $pageTitle ?? 'AZARED';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= Response::e($pageTitle) ?> · AZARED</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <!--
      CSRF token/name exposed via <meta>, never an inline <script>, so the
      app works under the strict `script-src 'self'` CSP set in
      config/bootstrap.php (no 'unsafe-inline'). External JS (app.js,
      ajax-forms.js, pos.js) reads these tags directly.
    -->
    <meta name="azr-csrf-token" content="<?= Response::e(Csrf::token()) ?>">
    <meta name="azr-csrf-name" content="<?= Response::e((string) config('session.csrf_token_name', 'azared_csrf_token')) ?>">
</head>
<body>
<div class="azr-app">
    <?php require __DIR__ . '/sidebar.php'; ?>
    <div class="azr-main">
        <?php require __DIR__ . '/topbar.php'; ?>
        <main class="azr-content">
