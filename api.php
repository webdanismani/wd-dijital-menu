<?php
/**
 * Ekranın 30 saniyede bir sorduğu veri. Menü değişmediyse yalnızca {"same":true} döner.
 */

declare(strict_types=1);

define('APP_JSON', true);
require __DIR__ . '/app/bootstrap.php';

$screen = Menu::screenByKey(get_str('k'));
if (!$screen) {
    json_out(['ok' => false, 'error' => 'not_found'], 404);
}

if (get_str('v') !== '' && get_str('v') === Menu::version($screen)) {
    json_out(['ok' => true, 'same' => true, 'now' => time()]);
}
json_out(Menu::payload($screen));
