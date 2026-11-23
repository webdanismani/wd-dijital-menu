<?php

declare(strict_types=1);

/**
 * Toplu fiyat güncelleme: seçilen kategorilerde yüzde veya tutar kadar artış/indirim,
 * istenen basamağa yuvarlama. Uygulamadan önce tüm değişiklikler önizlenir.
 */

$cats  = DB::all('SELECT * FROM {categories} ORDER BY sort_order, id');
$steps = ['0' => t('prices.round_none'), '0.5' => '0,50', '1' => '1', '5' => '5', '10' => '10', '25' => '25', '50' => '50'];

$in = [
    'cats'  => array_map('intval', is_post() ? (array) ($_POST['cats'] ?? []) : array_column($cats, 'id')),
    'mode'  => post_str('mode', 'pct') === 'fixed' ? 'fixed' : 'pct',
    'value' => post_str('value', '10'),
    'round' => isset($steps[post_str('round', '5')]) ? post_str('round', '5') : '5',
    'dir'   => post_str('dir', 'up') === 'down' ? 'down' : 'up',
];

$calc = function (?float $v) use ($in): ?float {
    if ($v === null) {
        return null;
    }
    $amount = abs(parse_num($in['value']) ?? 0);
    $sign = $in['dir'] === 'down' ? -1 : 1;
    $n = $in['mode'] === 'pct' ? $v * (1 + $sign * $amount / 100) : $v + $sign * $amount;
    $step = (float) $in['round'];
    if ($step > 0) {
        // Artışta yukarı, indirimde aşağı yuvarlanır; böylece 152 → 155 olur, 150'ye düşmez.
        $n = $in['dir'] === 'up' ? ceil(round($n / $step, 6)) * $step : floor(round($n / $step, 6)) * $step;
    }
    return max(0, round($n, 2));
};

$preview = null;
if (is_post()) {
    csrf_check();
    $valid = $in['cats'] && (parse_num($in['value']) ?? 0) > 0;
    if (!$valid) {
        flash(t('prices.err'), 'error');
    } else {
        $ph   = implode(',', array_fill(0, count($in['cats']), '?'));
        $rows = DB::all("SELECT * FROM {products} WHERE category_id IN ($ph) ORDER BY category_id, sort_order, id", $in['cats']);
        if (post_str('action') === 'apply') {
            DB::transaction(function () use ($rows, $calc) {
                foreach ($rows as $r) {
                    DB::update('products', [
                        'price'      => $calc($r['price'] !== null ? (float) $r['price'] : null),
                        'price2'     => $calc($r['price2'] !== null ? (float) $r['price2'] : null),
                        'price3'     => $calc($r['price3'] !== null ? (float) $r['price3'] : null),
                        'updated_at' => time(),
                    ], 'id = ?', [(int) $r['id']]);
                }
            });
            Product::touch();
            flash(t('prices.done', ['n' => count($rows)]));
            redirect(base_url('admin/?p=products'));
        }
        $preview = $rows;
    }
}

$catName = array_column($cats, 'name', 'id');

View::adminStart(t('nav.prices'), 'prices');
?>
<div class="split-2 split-form">
    <section class="panel">
        <div class="panel-head"><h2><?= View::icon('percent') ?><?= e(t('prices.title')) ?></h2></div>
        <p class="muted"><?= e(t('prices.desc')) ?></p>
        <form method="post" class="form mt">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="preview">
            <fieldset class="field"><span><?= e(t('prices.cats')) ?></span>
                <div class="check-list">
                    <?php foreach ($cats as $c) : ?><label class="check"><input type="checkbox" name="cats[]" value="<?= (int) $c['id'] ?>" <?= in_array((int) $c['id'], $in['cats'], true) ? 'checked' : '' ?>><span><?= e($c['name']) ?></span></label><?php endforeach; ?>
                </div>
            </fieldset>
            <div class="seg">
                <label><input type="radio" name="dir" value="up" <?= $in['dir'] === 'up' ? 'checked' : '' ?>><span><strong><?= e(t('prices.up')) ?></strong><small><?= e(t('prices.up_desc')) ?></small></span></label>
                <label><input type="radio" name="dir" value="down" <?= $in['dir'] === 'down' ? 'checked' : '' ?>><span><strong><?= e(t('prices.down')) ?></strong><small><?= e(t('prices.down_desc')) ?></small></span></label>
            </div>
            <div class="grid-3">
                <label class="field"><span><?= e(t('prices.mode')) ?></span><select name="mode"><option value="pct"><?= e(t('prices.pct')) ?></option><option value="fixed" <?= $in['mode'] === 'fixed' ? 'selected' : '' ?>><?= e(t('prices.fixed', ['c' => DB::setting('currency', '₺')])) ?></option></select></label>
                <label class="field"><span><?= e(t('prices.value')) ?></span><input name="value" value="<?= e($in['value']) ?>" inputmode="decimal" required></label>
                <label class="field"><span><?= e(t('prices.round')) ?></span><select name="round"><?php foreach ($steps as $k => $l) : ?><option value="<?= e($k) ?>" <?= $in['round'] === (string) $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
            </div>
            <div class="form-actions"><button class="btn btn-primary"><?= View::icon('eye', 15) ?><span><?= e(t('prices.preview')) ?></span></button></div>
        </form>
    </section>

    <section class="panel">
        <div class="panel-head"><h2><?= View::icon('list') ?><?= e(t('prices.result')) ?></h2></div>
        <?php if ($preview === null) : ?>
            <div class="empty-block"><?= View::icon('percent', 28) ?><p><?= e(t('prices.result_empty')) ?></p></div>
        <?php elseif (!$preview) : ?>
            <p class="muted"><?= e(t('prices.no_products')) ?></p>
        <?php else : ?>
            <div class="table-wrap"><table class="table">
                <thead><tr><th><?= e(t('product.name')) ?></th><th class="num"><?= e(t('prices.now')) ?></th><th class="num"><?= e(t('prices.new')) ?></th></tr></thead>
                <tbody>
                <?php foreach ($preview as $r) : $olds = []; $news = [];
                    foreach (['price', 'price2', 'price3'] as $k) {
                        if ($r[$k] !== null) {
                            $olds[] = Menu::price((float) $r[$k]);
                            $news[] = Menu::price($calc((float) $r[$k]));
                        }
                    } ?>
                    <tr><td><span class="strong"><?= e($r['name']) ?></span><span class="sub"><?= e($catName[$r['category_id']] ?? '') ?></span></td>
                        <td class="num muted"><?= e(implode(' / ', $olds) ?: '—') ?></td>
                        <td class="num strong"><?= e(implode(' / ', $news) ?: '—') ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <form method="post" class="form-actions mt">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="apply">
                <?php foreach ($in['cats'] as $c) : ?><input type="hidden" name="cats[]" value="<?= (int) $c ?>"><?php endforeach; ?>
                <input type="hidden" name="mode" value="<?= e($in['mode']) ?>"><input type="hidden" name="value" value="<?= e($in['value']) ?>">
                <input type="hidden" name="round" value="<?= e($in['round']) ?>"><input type="hidden" name="dir" value="<?= e($in['dir']) ?>">
                <button class="btn btn-primary" data-confirm="<?= e(t('prices.confirm', ['n' => count($preview)])) ?>"><?= View::icon('check', 15) ?><span><?= e(t('prices.apply', ['n' => count($preview)])) ?></span></button>
            </form>
        <?php endif; ?>
    </section>
</div>
<?php View::adminEnd(); ?>
