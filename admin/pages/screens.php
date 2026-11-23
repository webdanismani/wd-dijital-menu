<?php

declare(strict_types=1);

$self    = base_url('admin/?p=screens');
$layouts = [
    'menu_promo' => ['screens.l_menu_promo', 'screens.l_menu_promo_d', 'grid'],
    'menu'       => ['screens.l_menu', 'screens.l_menu_d', 'list'],
    'promo'      => ['screens.l_promo', 'screens.l_promo_d', 'spark'],
];

if (is_post()) {
    csrf_check();
    $action = post_str('action');

    if ($action === 'save') {
        $id   = post_int('id');
        $name = mb_substr(post_str('name'), 0, 80);
        if ($name === '') {
            flash(t('screens.err_name'), 'error');
            redirect($self . ($id ? '&edit=' . $id : '&new=1'));
        }
        $layout = post_str('layout');
        $theme  = post_str('theme');
        $catIds = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['cats'] ?? [])))));
        $data = [
            'name'          => $name,
            'layout'        => isset($layouts[$layout]) ? $layout : 'menu_promo',
            'category_ids'  => implode(',', $catIds),
            'columns'       => max(0, min(4, post_int('columns'))),
            'theme'         => isset(Product::themes()[$theme]) ? $theme : 'bistro',
            'show_images'   => post_bool('show_images'),
            'show_desc'     => post_bool('show_desc'),
            'page_seconds'  => max(5, min(120, post_int('page_seconds', 12))),
            'promo_seconds' => max(4, min(120, post_int('promo_seconds', 8))),
            'title'         => mb_substr(post_str('title'), 0, 80),
            'footer'        => mb_substr(post_str('footer'), 0, 240),
        ];
        if ($id) {
            DB::update('screens', $data, 'id = ?', [$id]);
        } else {
            $data['screen_key'] = random_token(8);
            $data['created_at'] = time();
            $id = DB::insert('screens', $data);
        }
        flash(t('screens.saved'));
        redirect($self . '&edit=' . $id);
    }

    if ($action === 'regen') {
        DB::update('screens', ['screen_key' => random_token(8)], 'id = ?', [post_int('id')]);
        flash(t('screens.key_new'));
        redirect($self . '&edit=' . post_int('id'));
    }

    if ($action === 'delete') {
        DB::q('DELETE FROM {screens} WHERE id = ?', [post_int('id')]);
        flash(t('ui.deleted'));
        redirect($self);
    }
}

$cats    = DB::all('SELECT id, name FROM {categories} ORDER BY sort_order, id');
$screens = DB::all('SELECT * FROM {screens} ORDER BY id');
$editId  = get_int('edit');
$isNew   = get_str('new') === '1';
$s = $editId ? DB::row('SELECT * FROM {screens} WHERE id = ?', [$editId]) : null;

if ($s || $isNew) {
    $s = $s ?: ['id' => 0, 'name' => '', 'screen_key' => '', 'layout' => 'menu_promo', 'category_ids' => '', 'columns' => 0, 'theme' => 'bistro', 'show_images' => 1, 'show_desc' => 1, 'page_seconds' => 12, 'promo_seconds' => 8, 'title' => '', 'footer' => ''];
    $sel = Menu::ids((string) $s['category_ids']);
    View::adminStart($s['id'] ? $s['name'] : t('screens.new'), 'screens', '<a class="btn btn-sm" href="' . e($self) . '">' . e(t('ui.back')) . '</a>');
    ?>
    <div class="split-2 split-form">
        <section class="panel">
            <form method="post" class="form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                <label class="field"><span><?= e(t('screens.name')) ?></span><input name="name" value="<?= e($s['name']) ?>" required maxlength="80" placeholder="<?= e(t('screens.name_ph')) ?>"><small><?= e(t('screens.name_hint')) ?></small></label>
                <fieldset class="field"><span><?= e(t('screens.layout')) ?></span>
                    <div class="type-pick type-pick-3">
                        <?php foreach ($layouts as $k => [$l, $d, $ic]) : ?>
                            <label><input type="radio" name="layout" value="<?= e($k) ?>" <?= $s['layout'] === $k ? 'checked' : '' ?>><span><?= View::icon($ic) ?><strong><?= e(t($l)) ?></strong><small><?= e(t($d)) ?></small></span></label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <fieldset class="field" data-when="layout=menu_promo,menu"><span><?= e(t('screens.cats')) ?></span>
                    <div class="check-list"><?php foreach ($cats as $c) : ?><label class="check"><input type="checkbox" name="cats[]" value="<?= (int) $c['id'] ?>" <?= in_array((int) $c['id'], $sel, true) ? 'checked' : '' ?>><span><?= e($c['name']) ?></span></label><?php endforeach; ?></div>
                    <small><?= e(t('screens.cats_hint')) ?></small>
                </fieldset>
                <fieldset class="field"><span><?= e(t('screens.theme')) ?></span>
                    <div class="swatches"><?php foreach (Product::themes() as $k => [$l, $col]) : ?><label><input type="radio" name="theme" value="<?= e($k) ?>" <?= $s['theme'] === $k ? 'checked' : '' ?>><span><i style="background:<?= e($col) ?>"></i><?= e(t($l)) ?></span></label><?php endforeach; ?></div>
                </fieldset>
                <div class="grid-3">
                    <label class="field" data-when="layout=menu_promo,menu"><span><?= e(t('screens.columns')) ?></span><select name="columns"><option value="0"><?= e(t('screens.auto')) ?></option><?php for ($i = 1; $i <= 4; $i++) : ?><option <?= (int) $s['columns'] === $i ? 'selected' : '' ?>><?= $i ?></option><?php endfor; ?></select></label>
                    <label class="field" data-when="layout=menu_promo,menu"><span><?= e(t('screens.page_sec')) ?></span><span class="input-suffix"><input name="page_seconds" value="<?= (int) $s['page_seconds'] ?>" inputmode="numeric"><em><?= e(t('screens.sec')) ?></em></span></label>
                    <label class="field" data-when="layout=menu_promo,promo"><span><?= e(t('screens.promo_sec')) ?></span><span class="input-suffix"><input name="promo_seconds" value="<?= (int) $s['promo_seconds'] ?>" inputmode="numeric"><em><?= e(t('screens.sec')) ?></em></span></label>
                </div>
                <div class="field" data-when="layout=menu_promo,menu"><span><?= e(t('screens.show')) ?></span>
                    <label class="check"><input type="checkbox" name="show_images" value="1" <?= $s['show_images'] ? 'checked' : '' ?>><span><?= e(t('screens.show_images')) ?></span></label>
                    <label class="check"><input type="checkbox" name="show_desc" value="1" <?= $s['show_desc'] ? 'checked' : '' ?>><span><?= e(t('screens.show_desc')) ?></span></label>
                </div>
                <label class="field"><span><?= e(t('screens.title')) ?> <em class="muted">(<?= e(t('ui.optional')) ?>)</em></span><input name="title" value="<?= e($s['title']) ?>" maxlength="80" placeholder="<?= e(DB::setting('site_title', '')) ?>"></label>
                <label class="field"><span><?= e(t('screens.footer')) ?> <em class="muted">(<?= e(t('ui.optional')) ?>)</em></span><input name="footer" value="<?= e($s['footer']) ?>" maxlength="240"></label>
                <div class="form-actions"><button class="btn btn-primary"><?= e(t('ui.save')) ?></button></div>
            </form>
        </section>
        <section class="panel">
            <?php if ($s['id']) : ?>
                <div class="panel-head"><h2><?= View::icon('eye') ?><?= e(t('dash.preview')) ?></h2>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="regen"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button class="btn btn-sm" data-confirm="<?= e(t('screens.key_confirm')) ?>"><?= View::icon('refresh', 15) ?><span><?= e(t('screens.key_regen')) ?></span></button></form>
                </div>
                <?= View::screenLink(Product::screenUrl($s)) ?>
                <div class="preview mt"><iframe src="<?= e(Product::screenUrl($s)) ?>" title="<?= e(t('dash.preview')) ?>" tabindex="-1"></iframe></div>
                <div class="preview is-portrait mt"><iframe src="<?= e(Product::screenUrl($s)) ?>" title="<?= e(t('screens.portrait')) ?>" tabindex="-1" loading="lazy"></iframe></div>
                <p class="muted mt"><?= e(t('screens.preview_hint')) ?></p>
            <?php else : ?>
                <div class="empty-block"><?= View::icon('tv', 30) ?><p><?= e(t('screens.save_first')) ?></p></div>
            <?php endif; ?>
        </section>
    </div>
    <?php
    View::adminEnd();
    return;
}

View::adminStart(t('nav.screens'), 'screens', '<a class="btn btn-sm btn-primary" href="' . e($self . '&new=1') . '">' . View::icon('plus', 15) . '<span>' . e(t('screens.new')) . '</span></a>');
?>
<p class="muted"><?= e(t('screens.desc')) ?></p>
<div class="screen-grid">
    <?php foreach ($screens as $s) : ?>
        <section class="panel screen-card">
            <div class="preview"><iframe src="<?= e(Product::screenUrl($s)) ?>" title="<?= e($s['name']) ?>" tabindex="-1" loading="lazy"></iframe></div>
            <div class="screen-card-body">
                <div><h2><?= e($s['name']) ?></h2><span class="muted"><?= e(t($layouts[$s['layout']][0] ?? 'screens.l_menu')) ?> · <?= e(t(Product::themes()[$s['theme']][0] ?? 'theme.bistro')) ?></span></div>
                <div class="panel-tools">
                    <a class="btn btn-sm" href="<?= e($self . '&edit=' . $s['id']) ?>"><?= View::icon('edit', 15) ?><span><?= e(t('ui.edit')) ?></span></a>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button class="iconbtn iconbtn-sm btn-ghost-danger" data-confirm="<?= e(t('screens.confirm_delete', ['n' => $s['name']])) ?>" title="<?= e(t('ui.delete')) ?>"><?= View::icon('trash', 14) ?></button></form>
                </div>
            </div>
            <?= View::screenLink(Product::screenUrl($s)) ?>
        </section>
    <?php endforeach; ?>
    <?php if (!$screens) : ?><section class="panel"><div class="empty-block"><?= View::icon('tv', 30) ?><p><?= e(t('screens.empty')) ?></p></div></section><?php endif; ?>
</div>
<?php View::adminEnd(); ?>
