<?php
/**
 * TV menü ekranı. Her ekranın kendi adresi vardır: ekran.php?k=EKRAN_ANAHTARI
 */

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$screen = Menu::screenByKey(get_str('k'));
if (!$screen) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    exit('<!doctype html><meta charset="utf-8"><title>404</title><body style="font:20px sans-serif;background:#17352b;color:#f4ecdc;display:grid;place-items:center;height:100vh;margin:0"><p>' . e(t('screen.not_found')) . '</p>');
}

$data = Menu::payload($screen);
$api  = base_url('api.php') . '?k=' . rawurlencode($screen['screen_key']);
$sc   = $data['screen'];
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e($sc['title']) ?> · Menü</title>
<link rel="icon" href="<?= e(base_url('assets/favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(asset('fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('screen.css')) ?>">
</head>
<body class="scr theme-<?= e($sc['theme']) ?> layout-<?= e($sc['layout']) ?>" data-api="<?= e($api) ?>" data-key="<?= e($screen['screen_key']) ?>">
<div class="m" data-menu>
    <header class="m-head">
        <img class="m-logo" alt="" hidden>
        <h1><?= e($sc['title']) ?></h1>
        <span class="m-clock"></span>
    </header>
    <main class="m-body">
        <div class="m-menu"><div class="m-cols"></div></div>
        <aside class="m-promo"></aside>
    </main>
    <footer class="m-foot"><span class="m-foot-text"></span><span class="m-pages"></span></footer>
</div>
<noscript><p style="padding:40px;font-size:24px"><?= e(t('screen.nojs')) ?></p></noscript>
<script id="initial" type="application/json"><?= json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= e(asset('screen.js')) ?>"></script>
</body>
</html>
