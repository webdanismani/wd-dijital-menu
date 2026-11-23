<?php

declare(strict_types=1);

require __DIR__ . '/Menu.php';
require __DIR__ . '/Upload.php';

/**
 * Dijital menüye özel tanımlar: tablolar, varsayılanlar, örnek menü, yönetim menüsü.
 */
final class Product
{
    public const DB_VERSION = 1;

    /** @return string[] */
    public static function schema(string $id, string $eng, string $p, bool $my): array
    {
        $dbl = $my ? 'DOUBLE' : 'REAL';
        return [
            "CREATE TABLE {$p}categories (
                id {$id},
                name VARCHAR(80) NOT NULL,
                note VARCHAR(160) NOT NULL DEFAULT '',
                icon VARCHAR(20) NOT NULL DEFAULT '',
                size_labels VARCHAR(80) NOT NULL DEFAULT '',
                is_active INT NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                created_at INT NOT NULL DEFAULT 0
            ){$eng}",
            "CREATE TABLE {$p}products (
                id {$id},
                category_id INT NOT NULL,
                name VARCHAR(120) NOT NULL,
                description VARCHAR(300) NOT NULL DEFAULT '',
                price {$dbl} NULL,
                price2 {$dbl} NULL,
                price3 {$dbl} NULL,
                old_price {$dbl} NULL,
                badges VARCHAR(120) NOT NULL DEFAULT '',
                image VARCHAR(120) NOT NULL DEFAULT '',
                is_active INT NOT NULL DEFAULT 1,
                is_soldout INT NOT NULL DEFAULT 0,
                sort_order INT NOT NULL DEFAULT 0,
                created_at INT NOT NULL DEFAULT 0,
                updated_at INT NOT NULL DEFAULT 0
            ){$eng}",
            "CREATE INDEX {$p}products_cat ON {$p}products (category_id, sort_order)",
            "CREATE TABLE {$p}campaigns (
                id {$id},
                title VARCHAR(120) NOT NULL,
                subtitle VARCHAR(240) NOT NULL DEFAULT '',
                price {$dbl} NULL,
                old_price {$dbl} NULL,
                image VARCHAR(120) NOT NULL DEFAULT '',
                color VARCHAR(16) NOT NULL DEFAULT 'accent',
                days VARCHAR(20) NOT NULL DEFAULT '1234567',
                time_from VARCHAR(5) NOT NULL DEFAULT '',
                time_to VARCHAR(5) NOT NULL DEFAULT '',
                date_from VARCHAR(10) NOT NULL DEFAULT '',
                date_to VARCHAR(10) NOT NULL DEFAULT '',
                is_active INT NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                created_at INT NOT NULL DEFAULT 0
            ){$eng}",
            "CREATE TABLE {$p}screens (
                id {$id},
                name VARCHAR(80) NOT NULL,
                screen_key VARCHAR(32) NOT NULL,
                layout VARCHAR(16) NOT NULL DEFAULT 'menu_promo',
                category_ids VARCHAR(500) NOT NULL DEFAULT '',
                columns INT NOT NULL DEFAULT 0,
                theme VARCHAR(16) NOT NULL DEFAULT 'bistro',
                show_images INT NOT NULL DEFAULT 1,
                show_desc INT NOT NULL DEFAULT 1,
                page_seconds INT NOT NULL DEFAULT 12,
                promo_seconds INT NOT NULL DEFAULT 8,
                title VARCHAR(80) NOT NULL DEFAULT '',
                footer VARCHAR(240) NOT NULL DEFAULT '',
                created_at INT NOT NULL DEFAULT 0
            ){$eng}",
            "CREATE UNIQUE INDEX {$p}screens_key ON {$p}screens (screen_key)",
        ];
    }

    public static function upgrade(int $from): void
    {
    }

    /** @return array<string,string> */
    public static function defaults(): array
    {
        return [
            'currency'       => '₺',
            'currency_pos'   => 'after',
            'price_decimals' => 'auto',
            'logo'           => '',
            'menu_version'   => '1',
            'demo'           => '1',
        ];
    }

    /** Örnek kafe menüsü. Panodaki tek düğmeyle silinebilir. */
    public static function seed(string $lang): void
    {
        $now = time();
        $cats = [
            ['Kahvaltı', 'Her gün 08:00 - 13:00', 'breakfast', '', [
                ['Serpme Kahvaltı', 'İki kişilik; peynir çeşitleri, zeytin, bal-kaymak, reçel, sahanda yumurta, sınırsız çay', 690, null, null, null, 'sef'],
                ['Menemen', 'Köy yumurtası, domates, biber; isteğe göre soğanlı', 220, null, null, null, 'vejetaryen'],
                ['Sucuklu Yumurta', 'Kasap sucuğu ile sahanda', 260, null, null, null, ''],
                ['Kaşarlı Tost', 'Köy ekmeğinde, yanında turşu', 180, null, null, null, ''],
                ['Avokadolu Ekşi Maya', 'Poşe yumurta, roka, pul biber', 290, null, null, null, 'yeni'],
            ]],
            ['Sıcak İçecekler', '', 'coffee', 'Küçük|Büyük', [
                ['Türk Kahvesi', 'Lokum ile', 120, null, null, null, ''],
                ['Çay', 'Demlik çay, ince belli', 35, 55, null, null, ''],
                ['Filtre Kahve', '', 130, 160, null, null, ''],
                ['Latte', 'Yulaf veya laktozsuz süt seçeneğiyle', 160, 190, null, null, ''],
                ['Salep', 'Tarçınlı', 150, null, null, null, ''],
                ['Sıcak Çikolata', '', 170, 200, null, 190, ''],
            ]],
            ['Soğuk İçecekler', '', 'drink', '', [
                ['Ev Yapımı Limonata', 'Taze nane ile', 140, null, null, null, ''],
                ['Ice Latte', '', 175, null, null, null, ''],
                ['Ayran', 'Köpüklü, bakır tasta', 60, null, null, null, ''],
                ['Taze Portakal Suyu', '', 150, null, null, null, ''],
                ['Soda', '', 45, null, null, null, ''],
            ]],
            ['Tatlılar', '', 'dessert', '', [
                ['Künefe', 'Hatay peyniri, Antep fıstığı', 260, null, null, null, 'sef'],
                ['San Sebastian', 'Akışkan çikolata sosu ile', 240, null, null, null, 'yeni'],
                ['Fırın Sütlaç', '', 150, null, null, null, ''],
                ['Fıstıklı Baklava', '4 dilim', 280, null, null, null, ''],
            ]],
            ['Ana Yemekler', '12:00 - 22:00', 'grill', '', [
                ['Izgara Köfte', 'Piyaz ve közlenmiş biber ile', 380, null, null, null, ''],
                ['Tavuk Şiş', 'Bulgur pilavı, mevsim salata', 340, null, null, null, ''],
                ['Mantı', 'Yoğurt, tereyağlı sos', 290, null, null, null, ''],
                ['Acılı Adana', 'Lavaş, sumaklı soğan', 420, null, null, null, 'acili'],
                ['Sebzeli Makarna', 'Mevsim sebzeleri, parmesan', 260, null, null, null, 'vejetaryen'],
            ]],
        ];
        $ci = 0;
        $catIds = [];
        foreach ($cats as [$name, $note, $icon, $sizes, $items]) {
            $cid = DB::insert('categories', ['name' => $name, 'note' => $note, 'icon' => $icon, 'size_labels' => $sizes, 'is_active' => 1, 'sort_order' => $ci += 10, 'created_at' => $now]);
            $catIds[] = $cid;
            $pi = 0;
            foreach ($items as [$pn, $desc, $p1, $p2, $p3, $old, $badges]) {
                DB::insert('products', [
                    'category_id' => $cid, 'name' => $pn, 'description' => $desc, 'price' => $p1, 'price2' => $p2, 'price3' => $p3,
                    'old_price' => $old, 'badges' => $badges, 'is_active' => 1, 'is_soldout' => $pn === 'Salep' ? 1 : 0,
                    'sort_order' => $pi += 10, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
        DB::insert('campaigns', ['title' => 'Kahvaltı + Sınırsız Çay', 'subtitle' => 'Hafta içi 08:00 - 11:00 arası serpme kahvaltıda', 'price' => 590, 'old_price' => 690, 'color' => 'accent', 'days' => '12345', 'is_active' => 1, 'sort_order' => 10, 'created_at' => $now]);
        DB::insert('campaigns', ['title' => 'Günün Tatlısı: Künefe', 'subtitle' => 'Yanında Türk kahvesi ile birlikte', 'price' => 330, 'old_price' => 380, 'color' => 'warm', 'days' => '1234567', 'is_active' => 1, 'sort_order' => 20, 'created_at' => $now]);
        DB::insert('campaigns', ['title' => 'İkinci Latte %50 indirimli', 'subtitle' => 'Her gün 15:00 - 18:00', 'color' => 'cool', 'days' => '1234567', 'is_active' => 1, 'sort_order' => 30, 'created_at' => $now]);

        DB::insert('screens', ['name' => 'Kasa üstü ekran', 'screen_key' => random_token(8), 'layout' => 'menu_promo', 'category_ids' => '', 'columns' => 0, 'theme' => 'bistro', 'show_images' => 1, 'show_desc' => 1, 'page_seconds' => 12, 'promo_seconds' => 8, 'footer' => 'Fiyatlarımıza KDV dahildir. Alerjen bilgisi için lütfen sorunuz.', 'created_at' => $now]);
    }

    /** @return array<string,array{0:string,1:string,2:string}> */
    public static function nav(): array
    {
        return [
            'dashboard'  => ['nav.dashboard', 'grid', base_url('admin/')],
            'products'   => ['nav.products', 'bowl', base_url('admin/?p=products')],
            'categories' => ['nav.categories', 'list', base_url('admin/?p=categories')],
            'prices'     => ['nav.prices', 'percent', base_url('admin/?p=prices')],
            'campaigns'  => ['nav.campaigns', 'spark', base_url('admin/?p=campaigns')],
            'screens'    => ['nav.screens', 'tv', base_url('admin/?p=screens')],
            'settings'   => ['nav.settings', 'gear', base_url('admin/?p=settings')],
        ];
    }

    /** @return string[] */
    public static function pages(): array
    {
        return ['dashboard', 'products', 'categories', 'prices', 'campaigns', 'screens', 'settings', 'action'];
    }

    public static function logo(int $size = 34): string
    {
        return '<svg class="logo" width="' . $size . '" height="' . $size . '" viewBox="0 0 32 32" aria-hidden="true"><rect width="32" height="32" rx="9" fill="currentColor"/>'
            . '<path d="M9 9.5h14M9 14.5h9M9 19.5h14M9 24h7" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/><circle cx="23" cy="14.5" r="1.6" fill="#f0b23e"/><circle cx="20" cy="24" r="1.6" fill="#f0b23e"/></svg>';
    }

    public static function sideNote(): string
    {
        $n = (int) DB::val('SELECT COUNT(*) FROM {products} WHERE is_soldout = 1 AND is_active = 1');
        if (!$n) {
            return '';
        }
        return '<a class="cron-warn" href="' . e(base_url('admin/?p=products&f=soldout')) . '">' . View::icon('alert', 15) . '<span>' . e(t('side.soldout', ['n' => $n])) . '</span></a>';
    }

    /** Menüde bir şey değişince ekranlar yeni veriyi çeker. */
    public static function touch(): void
    {
        DB::setSetting('menu_version', (int) DB::setting('menu_version', 1) + 1);
    }

    public static function screenUrl(array $screen): string
    {
        return absolute_url('ekran.php?k=' . $screen['screen_key']);
    }

    /** @return array<string,array{0:string,1:string}> */
    public static function themes(): array
    {
        return [
            'bistro' => ['theme.bistro', '#17352b'],
            'gece'   => ['theme.gece', '#1d1b1a'],
            'beyaz'  => ['theme.beyaz', '#fbfaf7'],
        ];
    }

    /** @return array<string,string> */
    public static function badges(): array
    {
        return [
            'yeni'       => t('badge.yeni'),
            'sef'        => t('badge.sef'),
            'acili'      => t('badge.acili'),
            'vejetaryen' => t('badge.vejetaryen'),
            'vegan'      => t('badge.vegan'),
            'glutensiz'  => t('badge.glutensiz'),
        ];
    }

    /** @return string[] */
    public static function catIcons(): array
    {
        return ['', 'breakfast', 'coffee', 'tea', 'drink', 'dessert', 'grill', 'burger', 'pizza', 'salad', 'soup', 'fish', 'pasta'];
    }
}
