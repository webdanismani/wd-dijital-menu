<?php

declare(strict_types=1);

$stats = [
    'products' => (int) DB::val('SELECT COUNT(*) FROM {products} WHERE is_active = 1'),
    'cats'     => (int) DB::val('SELECT COUNT(*) FROM {categories} WHERE is_active = 1'),
    'soldout'  => (int) DB::val('SELECT COUNT(*) FROM {products} WHERE is_active = 1 AND is_soldout = 1'),
    'promos'   => (int) DB::val('SELECT COUNT(*) FROM {campaigns} WHERE is_active = 1'),
];
$screens = DB::all('SELECT * FROM {screens} ORDER BY id');
$recent  = DB::all('SELECT p.*, c.name AS cat FROM {products} p LEFT JOIN {categories} c ON c.id = p.category_id ORDER BY p.updated_at DESC, p.id DESC LIMIT 8');
$soldout = DB::all('SELECT id, name FROM {products} WHERE is_active = 1 AND is_soldout = 1 ORDER BY name LIMIT 12');

View::adminStart(t('nav.dashboard'), 'dashboard', '<a class="btn btn-sm btn-primary" href="' . e(base_url('admin/?p=products&new=1')) . '">' . View::icon('plus', 15) . '<span>' . e(t('product.new')) . '</span></a>');
?>
<?php if (DB::setting('demo', '0') === '1') : ?>
    <div class="alert demo-alert">
        <span><?= e(t('dash.demo')) ?></span>
        <form method="post" action="<?= e(base_url('admin/?p=action')) ?>"><?= csrf_field() ?><input type="hidden" name="do" value="demo_keep"><button class="btn btn-sm"><?= e(t('dash.demo_keep')) ?></button></form>
        <form method="post" action="<?= e(base_url('admin/?p=action')) ?>"><?= csrf_field() ?><input type="hidden" name="do" value="demo_clear"><button class="btn btn-sm btn-ghost-danger" data-confirm="<?= e(t('dash.demo_confirm')) ?>"><?= e(t('dash.demo_clear')) ?></button></form>
    </div>
<?php endif; ?>

<div class="stat-strip">
    <div><span><?= View::icon('bowl', 14) ?><?= e(t('dash.products')) ?></span><strong><?= $stats['products'] ?></strong></div>
    <div><span><?= View::icon('list', 14) ?><?= e(t('dash.cats')) ?></span><strong><?= $stats['cats'] ?></strong></div>
    <div class="<?= $stats['soldout'] ? 'is-warn' : '' ?>"><span><?= View::icon('alert', 14) ?><?= e(t('dash.soldout')) ?></span><strong><?= $stats['soldout'] ?></strong></div>
    <div><span><?= View::icon('spark', 14) ?><?= e(t('dash.promos')) ?></span><strong><?= $stats['promos'] ?></strong></div>
</div>

<div class="split-2">
    <section class="panel">
        <div class="panel-head"><h2><?= View::icon('tv') ?><?= e(t('dash.screens')) ?></h2><a class="btn btn-sm" href="<?= e(base_url('admin/?p=screens')) ?>"><?= e(t('dash.manage')) ?></a></div>
        <?php if ($screens) : $first = $screens[0]; ?>
            <div class="preview"><iframe src="<?= e(Product::screenUrl($first)) ?>" title="<?= e($first['name']) ?>" tabindex="-1"></iframe></div>
            <?php foreach ($screens as $s) : ?>
                <div class="mt"><p class="strong-line"><?= e($s['name']) ?></p><?= View::screenLink(Product::screenUrl($s)) ?></div>
            <?php endforeach; ?>
        <?php else : ?>
            <div class="empty-block"><?= View::icon('tv', 28) ?><p><?= e(t('screens.empty')) ?></p><a class="btn btn-primary" href="<?= e(base_url('admin/?p=screens&new=1')) ?>"><?= e(t('screens.new')) ?></a></div>
        <?php endif; ?>
    </section>

    <div class="stack">
        <section class="panel">
            <div class="panel-head"><h2><?= View::icon('alert') ?><?= e(t('dash.soldout_list')) ?></h2></div>
            <?php if (!$soldout) : ?>
                <p class="empty-line"><?= View::icon('check', 16) ?><?= e(t('dash.soldout_none')) ?></p>
            <?php else : ?>
                <form method="post" action="<?= e(base_url('admin/?p=action')) ?>" class="chips-form">
                    <?= csrf_field() ?><input type="hidden" name="do" value="restock">
                    <?php foreach ($soldout as $p) : ?><button class="chip-btn" name="id" value="<?= (int) $p['id'] ?>" title="<?= e(t('dash.restock')) ?>"><?= e($p['name']) ?><?= View::icon('check', 13) ?></button><?php endforeach; ?>
                </form>
                <p class="muted mt"><?= e(t('dash.restock_hint')) ?></p>
            <?php endif; ?>
        </section>
        <section class="panel">
            <div class="panel-head"><h2><?= View::icon('clock') ?><?= e(t('dash.recent')) ?></h2><a class="btn btn-sm" href="<?= e(base_url('admin/?p=prices')) ?>"><?= View::icon('percent', 15) ?><span><?= e(t('nav.prices')) ?></span></a></div>
            <div class="table-wrap"><table class="table">
                <tbody>
                <?php foreach ($recent as $p) : ?>
                    <tr><td><a class="strong" href="<?= e(base_url('admin/?p=products&edit=' . $p['id'])) ?>"><?= e($p['name']) ?></a><span class="sub"><?= e((string) $p['cat']) ?></span></td>
                        <td class="num nowrap"><?= e(implode(' / ', array_map(fn ($v) => Menu::price((float) $v), array_filter([$p['price'], $p['price2'], $p['price3']], fn ($v) => $v !== null))) ?: '—') ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </section>
    </div>
</div>
<?php View::adminEnd(); ?>
