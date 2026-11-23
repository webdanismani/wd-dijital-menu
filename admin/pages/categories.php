<?php

declare(strict_types=1);

$self = base_url('admin/?p=categories');

if (is_post()) {
    csrf_check();
    $action = post_str('action');

    if ($move = post_str('move')) {
        [$id, $dir] = array_pad(explode(':', $move), 2, '');
        $ids = array_map('intval', array_column(DB::all('SELECT id FROM {categories} ORDER BY sort_order, id'), 'id'));
        $i = array_search((int) $id, $ids, true);
        $j = $dir === 'up' ? $i - 1 : $i + 1;
        if ($i !== false && isset($ids[$j])) {
            [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
            foreach ($ids as $n => $cid) {
                DB::update('categories', ['sort_order' => ($n + 1) * 10], 'id = ?', [$cid]);
            }
            Product::touch();
        }
        redirect($self);
    }

    if ($action === 'save') {
        $id   = post_int('id');
        $name = mb_substr(post_str('name'), 0, 80);
        if ($name === '') {
            flash(t('cat.err_name'), 'error');
            redirect($self . ($id ? '&edit=' . $id : ''));
        }
        $icon = post_str('icon');
        $sizes = implode('|', array_slice(array_values(array_filter(array_map(fn ($s) => mb_substr(trim($s), 0, 20), explode('|', str_replace([',', ';'], '|', post_str('size_labels')))), 'strlen')), 0, 3));
        $data = [
            'name'        => $name,
            'note'        => mb_substr(post_str('note'), 0, 160),
            'icon'        => in_array($icon, Product::catIcons(), true) ? $icon : '',
            'size_labels' => $sizes,
            'is_active'   => post_bool('is_active'),
        ];
        if ($id) {
            DB::update('categories', $data, 'id = ?', [$id]);
        } else {
            $data['sort_order'] = (int) DB::val('SELECT COALESCE(MAX(sort_order), 0) FROM {categories}') + 10;
            $data['created_at'] = time();
            DB::insert('categories', $data);
        }
        Product::touch();
        flash(t('ui.saved'));
        redirect($self);
    }

    if ($action === 'delete') {
        $id = post_int('id');
        $images = array_column(DB::all('SELECT image FROM {products} WHERE category_id = ?', [$id]), 'image');
        DB::q('DELETE FROM {products} WHERE category_id = ?', [$id]);
        foreach ($images as $img) {
            Upload::delete((string) $img);
        }
        DB::q('DELETE FROM {categories} WHERE id = ?', [$id]);
        Product::touch();
        flash(t('ui.deleted'));
        redirect($self);
    }
}

$cats = DB::all('SELECT c.*, (SELECT COUNT(*) FROM {products} p WHERE p.category_id = c.id) AS n FROM {categories} c ORDER BY c.sort_order, c.id');
$edit = get_int('edit') ? DB::row('SELECT * FROM {categories} WHERE id = ?', [get_int('edit')]) : null;
$e = $edit ?: ['id' => 0, 'name' => '', 'note' => '', 'icon' => '', 'size_labels' => '', 'is_active' => 1];
$iconNames = ['' => t('cat.icon_none'), 'breakfast' => t('cat.i_breakfast'), 'coffee' => t('cat.i_coffee'), 'tea' => t('cat.i_tea'), 'drink' => t('cat.i_drink'), 'dessert' => t('cat.i_dessert'), 'grill' => t('cat.i_grill'), 'burger' => t('cat.i_burger'), 'pizza' => t('cat.i_pizza'), 'salad' => t('cat.i_salad'), 'soup' => t('cat.i_soup'), 'fish' => t('cat.i_fish'), 'pasta' => t('cat.i_pasta')];

View::adminStart(t('nav.categories'), 'categories');
?>
<div class="split-2 split-form">
    <section class="panel">
        <div class="panel-head"><h2><?= View::icon('list') ?><?= e(t('cat.list')) ?></h2></div>
        <?php if (!$cats) : ?>
            <p class="muted"><?= e(t('cat.empty')) ?></p>
        <?php else : ?>
            <form method="post">
                <?= csrf_field() ?>
                <div class="table-wrap"><table class="table">
                    <thead><tr><th></th><th><?= e(t('cat.name')) ?></th><th class="num"><?= e(t('cat.products')) ?></th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($cats as $n => $c) : ?>
                        <tr class="<?= $c['is_active'] ? '' : 'is-off' ?>">
                            <td class="nowrap">
                                <button class="iconbtn iconbtn-sm" name="move" value="<?= (int) $c['id'] ?>:up" <?= $n === 0 ? 'disabled' : '' ?> title="<?= e(t('ui.move_up')) ?>"><?= View::icon('arrow-up', 14) ?></button>
                                <button class="iconbtn iconbtn-sm" name="move" value="<?= (int) $c['id'] ?>:down" <?= $n === count($cats) - 1 ? 'disabled' : '' ?> title="<?= e(t('ui.move_down')) ?>"><?= View::icon('arrow-dn', 14) ?></button>
                            </td>
                            <td><a class="strong" href="<?= e($self . '&edit=' . $c['id']) ?>"><?= e($c['name']) ?></a>
                                <?php if (!$c['is_active']) : ?><span class="chip"><?= e(t('ui.passive')) ?></span><?php endif; ?>
                                <span class="sub"><?= e(trim($c['note'] . ($c['size_labels'] !== '' ? ' · ' . str_replace('|', ' / ', $c['size_labels']) : ''), ' ·')) ?></span></td>
                            <td class="num"><a href="<?= e(base_url('admin/?p=products&c=' . $c['id'])) ?>"><?= (int) $c['n'] ?></a></td>
                            <td class="actions">
                                <a class="iconbtn iconbtn-sm" href="<?= e($self . '&edit=' . $c['id']) ?>" title="<?= e(t('ui.edit')) ?>"><?= View::icon('edit', 14) ?></a>
                                <button class="iconbtn iconbtn-sm btn-ghost-danger" form="del-<?= (int) $c['id'] ?>" data-confirm="<?= e(t('cat.confirm_delete', ['n' => $c['name'], 'c' => $c['n']])) ?>" title="<?= e(t('ui.delete')) ?>"><?= View::icon('trash', 14) ?></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
            </form>
            <?php foreach ($cats as $c) : ?>
                <form method="post" id="del-<?= (int) $c['id'] ?>" hidden><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"></form>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <section class="panel">
        <div class="panel-head"><h2><?= View::icon($e['id'] ? 'edit' : 'plus') ?><?= e($e['id'] ? t('cat.edit') : t('cat.new')) ?></h2><?php if ($e['id']) : ?><a class="btn btn-sm" href="<?= e($self) ?>"><?= e(t('cat.new')) ?></a><?php endif; ?></div>
        <form method="post" class="form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= (int) $e['id'] ?>">
            <label class="field"><span><?= e(t('cat.name')) ?></span><input name="name" value="<?= e($e['name']) ?>" required maxlength="80" placeholder="<?= e(t('cat.name_ph')) ?>"></label>
            <label class="field"><span><?= e(t('cat.note')) ?> <em class="muted">(<?= e(t('ui.optional')) ?>)</em></span><input name="note" value="<?= e($e['note']) ?>" maxlength="160" placeholder="<?= e(t('cat.note_ph')) ?>"></label>
            <div class="grid-2">
                <label class="field"><span><?= e(t('cat.icon')) ?></span><select name="icon"><?php foreach ($iconNames as $k => $l) : ?><option value="<?= e($k) ?>" <?= $e['icon'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
                <label class="field"><span><?= e(t('cat.sizes')) ?></span><input name="size_labels" value="<?= e(str_replace('|', ' | ', $e['size_labels'])) ?>" maxlength="80" placeholder="Küçük | Orta | Büyük"></label>
            </div>
            <p class="muted"><?= e(t('cat.sizes_hint')) ?></p>
            <label class="check"><input type="checkbox" name="is_active" value="1" <?= $e['is_active'] ? 'checked' : '' ?>><span><?= e(t('cat.active')) ?></span></label>
            <div class="form-actions"><button class="btn btn-primary"><?= e(t('ui.save')) ?></button></div>
        </form>
    </section>
</div>
<?php View::adminEnd(); ?>
