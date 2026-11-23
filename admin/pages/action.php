<?php

declare(strict_types=1);

if (!is_post()) {
    redirect(base_url('admin/'));
}
csrf_check();

switch (post_str('do')) {
    case 'restock':
        DB::update('products', ['is_soldout' => 0, 'updated_at' => time()], 'id = ?', [post_int('id')]);
        Product::touch();
        flash(t('dash.restocked'));
        break;

    case 'demo_keep':
        DB::setSetting('demo', '0');
        break;

    case 'demo_clear':
        // Örnek menü: ürünler, kategoriler ve kampanyalar silinir; ekranlar kalır.
        DB::transaction(function () {
            DB::q('DELETE FROM {products}');
            DB::q('DELETE FROM {categories}');
            DB::q('DELETE FROM {campaigns}');
            DB::q("UPDATE {screens} SET category_ids = ''");
        });
        DB::setSetting('demo', '0');
        Product::touch();
        flash(t('dash.demo_cleared'));
        break;
}

redirect(base_url('admin/'));
