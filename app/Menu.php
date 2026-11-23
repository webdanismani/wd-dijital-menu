<?php

declare(strict_types=1);

/**
 * Menü verisi ve fiyat biçimi.
 */
final class Menu
{
    /** 120 ₺, 1.250 ₺, 72,50 ₺ */
    public static function price(?float $v): string
    {
        if ($v === null) {
            return '';
        }
        $mode = (string) DB::setting('price_decimals', 'auto');
        $dec  = $mode === '2' ? 2 : ($mode === '0' ? 0 : (abs($v - round($v)) < 0.005 ? 0 : 2));
        $num  = number_format($v, $dec, ',', '.');
        $sym  = (string) DB::setting('currency', '₺');
        if ($sym === '') {
            return $num;
        }
        return DB::setting('currency_pos', 'after') === 'before' ? $sym . $num : $num . "\u{202F}" . $sym;
    }

    public static function imageUrl(string $file): string
    {
        return $file === '' ? '' : base_url('uploads/' . rawurlencode($file));
    }

    /** @return int[] */
    public static function ids(string $csv): array
    {
        return array_values(array_filter(array_map('intval', explode(',', $csv))));
    }

    /** @return array<string,mixed>|null */
    public static function screenByKey(string $key): ?array
    {
        if ($key === '' || !preg_match('/^[a-f0-9]{8,32}$/', $key)) {
            return null;
        }
        return DB::row('SELECT * FROM {screens} WHERE screen_key = ?', [$key]);
    }

    /**
     * Bir ekranın tüm verisi.
     *
     * @param array<string,mixed> $screen
     * @return array<string,mixed>
     */
    public static function payload(array $screen): array
    {
        $only = self::ids((string) $screen['category_ids']);
        $cats = DB::all('SELECT * FROM {categories} WHERE is_active = 1 ORDER BY sort_order, id');
        if ($only) {
            // Ekran için seçilen sırayla
            $byId = [];
            foreach ($cats as $c) {
                $byId[(int) $c['id']] = $c;
            }
            $cats = array_values(array_filter(array_map(fn ($id) => $byId[$id] ?? null, $only)));
        }
        $products = [];
        foreach (DB::all('SELECT * FROM {products} WHERE is_active = 1 ORDER BY sort_order, id') as $p) {
            $products[(int) $p['category_id']][] = $p;
        }
        $showImg = (int) $screen['show_images'] === 1;
        $outCats = [];
        foreach ($cats as $c) {
            $items = [];
            foreach ($products[(int) $c['id']] ?? [] as $p) {
                $prices = array_values(array_filter([
                    $p['price'] !== null ? self::price((float) $p['price']) : null,
                    $p['price2'] !== null ? self::price((float) $p['price2']) : null,
                    $p['price3'] !== null ? self::price((float) $p['price3']) : null,
                ], fn ($x) => $x !== null));
                $items[] = [
                    'id'      => (int) $p['id'],
                    'name'    => $p['name'],
                    'desc'    => (int) $screen['show_desc'] === 1 ? $p['description'] : '',
                    'prices'  => $prices,
                    'old'     => $p['old_price'] !== null ? self::price((float) $p['old_price']) : '',
                    'badges'  => array_values(array_filter(explode(',', (string) $p['badges']))),
                    'image'   => $showImg ? self::imageUrl((string) $p['image']) : '',
                    'soldout' => (int) $p['is_soldout'] === 1,
                ];
            }
            if (!$items) {
                continue;
            }
            $outCats[] = [
                'id'    => (int) $c['id'],
                'name'  => $c['name'],
                'note'  => $c['note'],
                'icon'  => $c['icon'],
                'sizes' => array_values(array_filter(array_map('trim', explode('|', (string) $c['size_labels'])), 'strlen')),
                'items' => $items,
            ];
        }

        $promos = [];
        if ($screen['layout'] !== 'menu') {
            foreach (DB::all('SELECT * FROM {campaigns} WHERE is_active = 1 ORDER BY sort_order, id') as $k) {
                $promos[] = [
                    'id'       => (int) $k['id'],
                    'title'    => $k['title'],
                    'subtitle' => $k['subtitle'],
                    'price'    => $k['price'] !== null ? self::price((float) $k['price']) : '',
                    'old'      => $k['old_price'] !== null ? self::price((float) $k['old_price']) : '',
                    'image'    => self::imageUrl((string) $k['image']),
                    'color'    => $k['color'],
                    'days'     => $k['days'],
                    'from'     => $k['time_from'],
                    'to'       => $k['time_to'],
                    'dfrom'    => $k['date_from'],
                    'dto'      => $k['date_to'],
                ];
            }
        }

        return [
            'ok'        => true,
            'version'   => self::version($screen),
            'now'       => time(),
            'tz_offset' => (int) date('Z'),
            'screen'    => [
                'title'   => $screen['title'] !== '' ? $screen['title'] : (string) DB::setting('site_title', ''),
                'logo'    => self::imageUrl((string) DB::setting('logo', '')),
                'layout'  => $screen['layout'],
                'columns' => (int) $screen['columns'],
                'theme'   => $screen['theme'],
                'page'    => max(5, (int) $screen['page_seconds']),
                'promo'   => max(4, (int) $screen['promo_seconds']),
                'footer'  => $screen['footer'],
            ],
            'categories' => $outCats,
            'promos'     => $promos,
        ];
    }

    /** Ekranın gördüğü sürüm: menü değişince veya ekran ayarı değişince artar. */
    public static function version(array $screen): string
    {
        return DB::setting('menu_version', '1') . '.' . substr(md5(json_encode($screen) ?: ''), 0, 8);
    }
}
