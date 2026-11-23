<?php

declare(strict_types=1);

$cats   = DB::all('SELECT * FROM {categories} ORDER BY sort_order, id');
$catMap = [];
foreach ($cats as $c) {
    $catMap[(int) $c['id']] = $c;
}
$cf     = get_int('c');
$filter = get_str('f');
$self   = base_url('admin/?p=products' . ($cf ? '&c=' . $cf : '') . ($filter ? '&f=' . $filter : ''));
$badges = Product::badges();

$num = function (string $k): ?float {
    $v = $_POST[$k] ?? '';
    return is_string($v) && trim($v) !== '' ? parse_num($v) : null;
};

if (is_post()) {
    csrf_check();
    $action = post_str('action');

    if ($move = post_str('move')) {
        [$id, $dir] = array_pad(explode(':', $move), 2, '');
        $p = DB::row('SELECT * FROM {products} WHERE id = ?', [(int) $id]);
        if ($p) {
            $ids = array_map('intval', array_column(DB::all('SELECT id FROM {products} WHERE category_id = ? ORDER BY sort_order, id', [$p['category_id']]), 'id'));
            $i = array_search((int) $id, $ids, true);
            $j = $dir === 'up' ? $i - 1 : $i + 1;
            if ($i !== false && isset($ids[$j])) {
                [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
                foreach ($ids as $n => $pid) {
                    DB::update('products', ['sort_order' => ($n + 1) * 10], 'id = ?', [$pid]);
                }
                Product::touch();
            }
        }
        redirect($self);
    }

    if ($action === 'quick') {
        $rows = array_map('intval', (array) ($_POST['rows'] ?? []));
        $data = (array) ($_POST['q'] ?? []);
        DB::transaction(function () use ($rows, $data) {
            foreach ($rows as $id) {
                $r = (array) ($data[$id] ?? []);
                $pr = fn (string $k) => isset($r[$k]) && is_string($r[$k]) && trim($r[$k]) !== '' ? parse_num($r[$k]) : null;
                DB::update('products', [
                    'price'      => $pr('price'),
                    'price2'     => $pr('price2'),
                    'price3'     => $pr('price3'),
                    'is_active'  => !empty($r['active']) ? 1 : 0,
                    'is_soldout' => !empty($r['soldout']) ? 1 : 0,
                    'updated_at' => time(),
                ], 'id = ?', [$id]);
            }
        });
        Product::touch();
        flash(t('ui.saved'));
        redirect($self);
    }

    if ($action === 'save') {
        $id   = post_int('id');
        $old  = $id ? DB::row('SELECT * FROM {products} WHERE id = ?', [$id]) : null;
        $cat  = post_int('category_id');
        $name = mb_substr(post_str('name'), 0, 120);
        if ($name === '' || !isset($catMap[$cat])) {
            flash(t('product.err_required'), 'error');
            redirect(base_url('admin/?p=products&' . ($id ? 'edit=' . $id : 'new=1')));
        }
        $up = Upload::save('image');
        if (!$up['ok']) {
            flash($up['error'], 'error');
        }
        $image = $old['image'] ?? '';
        if ($up['file'] !== '' || post_bool('remove_image')) {
            $prev = $image;
            $image = $up['file'];
        }
        $chosen = array_values(array_intersect(array_keys($badges), (array) ($_POST['badges'] ?? [])));
        $data = [
            'category_id' => $cat,
            'name'        => $name,
            'description' => mb_substr(post_str('description'), 0, 300),
            'price'       => $num('price'),
            'price2'      => $num('price2'),
            'price3'      => $num('price3'),
            'old_price'   => $num('old_price'),
            'badges'      => implode(',', $chosen),
            'image'       => $image,
            'is_active'   => post_bool('is_active'),
            'is_soldout'  => post_bool('is_soldout'),
            'updated_at'  => time(),
        ];
        if ($old) {
            if ((int) $old['category_id'] !== $cat) {
                $data['sort_order'] = (int) DB::val('SELECT COALESCE(MAX(sort_order), 0) FROM {products} WHERE category_id = ?', [$cat]) + 10;
            }
            DB::update('products', $data, 'id = ?', [$id]);
        } else {
            $data['sort_order'] = (int) DB::val('SELECT COALESCE(MAX(sort_order), 0) FROM {products} WHERE category_id = ?', [$cat]) + 10;
            $data['created_at'] = time();
            $id = DB::insert('products', $data);
        }
        if (isset($prev) && $prev !== '') {
            Upload::delete($prev);
        }
        Product::touch();
        flash(t('ui.saved'));
        redirect(post_str('next') === 'new' ? base_url('admin/?p=products&new=1&c=' . $cat) : base_url('admin/?p=products&c=' . $cat));
    }

    if ($action === 'delete') {
        $p = DB::row('SELECT * FROM {products} WHERE id = ?', [post_int('id')]);
        if ($p) {
            DB::q('DELETE FROM {products} WHERE id = ?', [(int) $p['id']]);
            Upload::delete((string) $p['image']);
            Product::touch();
            flash(t('ui.deleted'));
        }
        redirect($self);
    }
}

/* ---------- Ürün formu ---------- */
$editId = get_int('edit');
if ($editId || get_str('new') === '1') {
    $p = $editId ? DB::row('SELECT * FROM {products} WHERE id = ?', [$editId]) : null;
    if ($editId && !$p) {
        flash(t('err.not_found'), 'error');
        redirect(base_url('admin/?p=products'));
    }
    if (!$cats) {
        flash(t('product.need_category'), 'error');
        redirect(base_url('admin/?p=categories'));
    }
    $p = $p ?: ['id' => 0, 'category_id' => $cf ?: (int) $cats[0]['id'], 'name' => '', 'description' => '', 'price' => null, 'price2' => null, 'price3' => null, 'old_price' => null, 'badges' => '', 'image' => '', 'is_active' => 1, 'is_soldout' => 0];
    $fmt = fn ($v) => $v === null ? '' : number_format((float) $v, abs((float) $v - round((float) $v)) < 0.005 ? 0 : 2, ',', '.');
    $sel = explode(',', (string) $p['badges']);
    $sizes = [];
    foreach ($cats as $c) {
        $sizes[(int) $c['id']] = array_values(array_filter(array_map('trim', explode('|', (string) $c['size_labels'])), 'strlen'));
    }

    View::adminStart($p['id'] ? t('product.edit') : t('product.new'), 'products', '<a class="btn btn-sm" href="' . e(base_url('admin/?p=products&c=' . $p['category_id'])) . '">' . e(t('ui.back')) . '</a>');
    ?>
    <form method="post" enctype="multipart/form-data" class="split-2 split-form" data-sizes='<?= e(json_encode($sizes, JSON_UNESCAPED_UNICODE)) ?>'>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
        <section class="panel form">
            <div class="grid-2">
                <label class="field"><span><?= e(t('product.name')) ?></span><input name="name" value="<?= e($p['name']) ?>" required maxlength="120" autofocus></label>
                <label class="field"><span><?= e(t('product.category')) ?></span>
                    <select name="category_id" data-cat-select><?php foreach ($cats as $c) : ?><option value="<?= (int) $c['id'] ?>" <?= (int) $p['category_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select>
                </label>
            </div>
            <label class="field"><span><?= e(t('product.desc')) ?> <em class="muted">(<?= e(t('ui.optional')) ?>)</em></span><textarea name="description" rows="2" maxlength="300"><?= e($p['description']) ?></textarea></label>
            <div class="grid-4">
                <label class="field"><span data-size-label="0"><?= e(t('product.price')) ?></span><span class="input-suffix"><input name="price" value="<?= e($fmt($p['price'])) ?>" inputmode="decimal"><em><?= e(DB::setting('currency', '₺')) ?></em></span></label>
                <label class="field"><span data-size-label="1"><?= e(t('product.price2')) ?></span><span class="input-suffix"><input name="price2" value="<?= e($fmt($p['price2'])) ?>" inputmode="decimal"><em><?= e(DB::setting('currency', '₺')) ?></em></span></label>
                <label class="field"><span data-size-label="2"><?= e(t('product.price3')) ?></span><span class="input-suffix"><input name="price3" value="<?= e($fmt($p['price3'])) ?>" inputmode="decimal"><em><?= e(DB::setting('currency', '₺')) ?></em></span></label>
                <label class="field"><span><?= e(t('product.old_price')) ?></span><span class="input-suffix"><input name="old_price" value="<?= e($fmt($p['old_price'])) ?>" inputmode="decimal"><em><?= e(DB::setting('currency', '₺')) ?></em></span></label>
            </div>
            <p class="muted"><?= e(t('product.price_hint')) ?></p>
            <fieldset class="field"><span><?= e(t('product.badges')) ?></span>
                <div class="check-list check-list-3">
                    <?php foreach ($badges as $k => $l) : ?><label class="check"><input type="checkbox" name="badges[]" value="<?= e($k) ?>" <?= in_array($k, $sel, true) ? 'checked' : '' ?>><span><?= e($l) ?></span></label><?php endforeach; ?>
                </div>
            </fieldset>
        </section>
        <section class="panel form">
            <div class="field"><span><?= e(t('product.image')) ?> <em class="muted">(<?= e(t('ui.optional')) ?>)</em></span>
                <div class="imgpick">
                    <?php if ($p['image']) : ?><img src="<?= e(Menu::imageUrl($p['image'])) ?>" alt=""><?php else : ?><span class="imgpick-empty"><?= View::icon('image', 28) ?></span><?php endif; ?>
                    <div>
                        <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
                        <small class="muted"><?= e(t('upload.hint')) ?></small>
                        <?php if ($p['image']) : ?><label class="check"><input type="checkbox" name="remove_image" value="1"><span><?= e(t('product.remove_image')) ?></span></label><?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="field"><span><?= e(t('product.status')) ?></span>
                <label class="check"><input type="checkbox" name="is_active" value="1" <?= $p['is_active'] ? 'checked' : '' ?>><span><?= e(t('product.active')) ?></span></label>
                <label class="check"><input type="checkbox" name="is_soldout" value="1" <?= $p['is_soldout'] ? 'checked' : '' ?>><span><?= e(t('product.soldout_desc')) ?></span></label>
            </div>
            <div class="form-actions">
                <?php if (!$p['id']) : ?><button class="btn" name="next" value="new"><?= e(t('product.save_new')) ?></button><?php endif; ?>
                <button class="btn btn-primary"><?= e(t('ui.save')) ?></button>
            </div>
        </section>
    </form>
    <script>
    (function () {
        var form = document.querySelector('[data-sizes]');
        var sel = form.querySelector('[data-cat-select]');
        var sizes = JSON.parse(form.getAttribute('data-sizes'));
        var labels = form.querySelectorAll('[data-size-label]');
        var def = Array.prototype.map.call(labels, function (l) { return l.textContent; });
        function sync() {
            var s = sizes[sel.value] || [];
            labels.forEach(function (l, i) { l.textContent = s[i] || def[i]; });
        }
        sel.addEventListener('change', sync);
        sync();
    })();
    </script>
    <?php
    View::adminEnd();
    return;
}

/* ---------- Liste ---------- */
$where = [];
$args = [];
if ($cf) {
    $where[] = 'category_id = ?';
    $args[] = $cf;
}
if ($filter === 'soldout') {
    $where[] = 'is_soldout = 1';
} elseif ($filter === 'hidden') {
    $where[] = 'is_active = 0';
}
$rows = DB::all('SELECT * FROM {products}' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY sort_order, id', $args);
$grouped = [];
foreach ($rows as $r) {
    $grouped[(int) $r['category_id']][] = $r;
}
$counts = [];
foreach (DB::all('SELECT category_id, COUNT(*) n FROM {products} GROUP BY category_id') as $r) {
    $counts[(int) $r['category_id']] = (int) $r['n'];
}
$fmt = fn ($v) => $v === null ? '' : number_format((float) $v, abs((float) $v - round((float) $v)) < 0.005 ? 0 : 2, ',', '.');

View::adminStart(t('nav.products'), 'products', '<a class="btn btn-sm btn-primary" href="' . e(base_url('admin/?p=products&new=1' . ($cf ? '&c=' . $cf : ''))) . '">' . View::icon('plus', 15) . '<span>' . e(t('product.new')) . '</span></a>');
?>
<div class="toolbar">
    <nav class="tabs">
        <a href="<?= e(base_url('admin/?p=products')) ?>" class="<?= !$cf && !$filter ? 'is-active' : '' ?>"><?= e(t('product.all')) ?><em><?= array_sum($counts) ?></em></a>
        <?php foreach ($cats as $c) : ?>
            <a href="<?= e(base_url('admin/?p=products&c=' . $c['id'])) ?>" class="<?= $cf === (int) $c['id'] ? 'is-active' : '' ?>"><?= e($c['name']) ?><em><?= $counts[(int) $c['id']] ?? 0 ?></em></a>
        <?php endforeach; ?>
        <a href="<?= e(base_url('admin/?p=products&f=soldout')) ?>" class="<?= $filter === 'soldout' ? 'is-active' : '' ?>"><?= e(t('product.f_soldout')) ?></a>
    </nav>
    <input type="search" class="search" placeholder="<?= e(t('ui.search')) ?>" data-filter-input>
</div>

<?php if (!$rows) : ?>
    <section class="panel"><div class="empty-block"><?= View::icon('bowl', 30) ?><p><?= e($cats ? t('product.empty') : t('product.need_category')) ?></p>
        <a class="btn btn-primary" href="<?= e($cats ? base_url('admin/?p=products&new=1' . ($cf ? '&c=' . $cf : '')) : base_url('admin/?p=categories')) ?>"><?= e($cats ? t('product.new') : t('cat.new')) ?></a></div></section>
<?php else : ?>
<form method="post" data-dirty-form>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="quick">
    <button class="sr-default" tabindex="-1" aria-hidden="true"><?= e(t('ui.save')) ?></button>
    <?php foreach ($grouped as $cid => $list) : $c = $catMap[$cid] ?? ['name' => '?', 'size_labels' => '']; $sz = array_values(array_filter(array_map('trim', explode('|', (string) $c['size_labels'])), 'strlen')); ?>
        <section class="panel mt">
            <div class="panel-head"><h2><?= e($c['name']) ?></h2><a class="btn btn-sm" href="<?= e(base_url('admin/?p=products&new=1&c=' . $cid)) ?>"><?= View::icon('plus', 15) ?><span><?= e(t('product.add_here')) ?></span></a></div>
            <div class="table-wrap"><table class="table table-products is-wide">
                <thead><tr>
                    <th></th><th></th><th><?= e(t('product.name')) ?></th>
                    <th class="num"><?= e($sz[0] ?? t('product.price')) ?></th>
                    <th class="num"><?= e($sz[1] ?? t('product.price2')) ?></th>
                    <?php if (count($sz) > 2) : ?><th class="num"><?= e($sz[2]) ?></th><?php endif; ?>
                    <th><?= e(t('product.on_menu')) ?></th><th><?= e(t('product.soldout')) ?></th><th></th>
                </tr></thead>
                <tbody>
                <?php foreach ($list as $n => $r) : $id = (int) $r['id']; ?>
                    <tr class="<?= $r['is_active'] ? '' : 'is-off' ?>" data-filter-text="<?= e(mb_strtolower($r['name'] . ' ' . $r['description'])) ?>">
                        <input type="hidden" name="rows[]" value="<?= $id ?>">
                        <td class="nowrap">
                            <button class="iconbtn iconbtn-sm" name="move" value="<?= $id ?>:up" title="<?= e(t('ui.move_up')) ?>" <?= $n === 0 || $filter ? 'disabled' : '' ?>><?= View::icon('arrow-up', 14) ?></button>
                            <button class="iconbtn iconbtn-sm" name="move" value="<?= $id ?>:down" title="<?= e(t('ui.move_down')) ?>" <?= $n === count($list) - 1 || $filter ? 'disabled' : '' ?>><?= View::icon('arrow-dn', 14) ?></button>
                        </td>
                        <td><?php if ($r['image']) : ?><img class="thumb" src="<?= e(Menu::imageUrl($r['image'])) ?>" alt=""><?php else : ?><span class="thumb thumb-empty"><?= View::icon('image', 18) ?></span><?php endif; ?></td>
                        <td class="cell-name"><a class="strong" href="<?= e(base_url('admin/?p=products&edit=' . $id)) ?>"><?= e($r['name']) ?></a>
                            <?php foreach (array_filter(explode(',', (string) $r['badges'])) as $b) : ?><span class="chip is-accent"><?= e($badges[$b] ?? $b) ?></span><?php endforeach; ?>
                            <?php if ($r['description'] !== '') : ?><span class="sub"><?= e(mb_strimwidth($r['description'], 0, 90, '…')) ?></span><?php endif; ?></td>
                        <td class="num"><input class="cell" name="q[<?= $id ?>][price]" value="<?= e($fmt($r['price'])) ?>" inputmode="decimal" aria-label="<?= e(t('product.price')) ?>"></td>
                        <td class="num"><input class="cell" name="q[<?= $id ?>][price2]" value="<?= e($fmt($r['price2'])) ?>" inputmode="decimal" placeholder="—" aria-label="<?= e(t('product.price2')) ?>"></td>
                        <?php if (count($sz) > 2) : ?><td class="num"><input class="cell" name="q[<?= $id ?>][price3]" value="<?= e($fmt($r['price3'])) ?>" inputmode="decimal" placeholder="—"></td><?php else : ?><input type="hidden" name="q[<?= $id ?>][price3]" value="<?= e($fmt($r['price3'])) ?>"><?php endif; ?>
                        <td><label class="switch"><input type="checkbox" name="q[<?= $id ?>][active]" value="1" <?= $r['is_active'] ? 'checked' : '' ?>><span></span></label></td>
                        <td><label class="switch is-danger"><input type="checkbox" name="q[<?= $id ?>][soldout]" value="1" <?= $r['is_soldout'] ? 'checked' : '' ?>><span></span></label></td>
                        <td class="actions">
                            <a class="iconbtn iconbtn-sm" href="<?= e(base_url('admin/?p=products&edit=' . $id)) ?>" title="<?= e(t('ui.edit')) ?>"><?= View::icon('edit', 14) ?></a>
                            <button class="iconbtn iconbtn-sm btn-ghost-danger" form="del-<?= $id ?>" data-confirm="<?= e(t('product.confirm_delete', ['n' => $r['name']])) ?>" title="<?= e(t('ui.delete')) ?>"><?= View::icon('trash', 14) ?></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </section>
    <?php endforeach; ?>
    <p class="empty-line mt" data-filter-empty hidden><?= e(t('ui.no_results')) ?></p>
    <div class="dirty-bar" data-dirty-bar hidden><span><?= e(t('product.unsaved')) ?></span><button class="btn btn-primary btn-sm"><?= e(t('ui.save')) ?></button></div>
</form>
<?php foreach ($rows as $r) : ?>
    <form method="post" id="del-<?= (int) $r['id'] ?>" hidden><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"></form>
<?php endforeach; ?>
<?php endif; ?>
<?php View::adminEnd(); ?>
