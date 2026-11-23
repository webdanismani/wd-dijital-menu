<?php

declare(strict_types=1);

if (is_post()) {
    csrf_check();
    $pos = post_str('currency_pos');
    $dec = post_str('price_decimals');
    DB::setSetting('currency', mb_substr(post_str('currency'), 0, 6));
    DB::setSetting('currency_pos', $pos === 'before' ? 'before' : 'after');
    DB::setSetting('price_decimals', in_array($dec, ['auto', '0', '2'], true) ? $dec : 'auto');

    $up = Upload::save('logo');
    if (!$up['ok']) {
        flash($up['error'], 'error');
    }
    $old = (string) DB::setting('logo', '');
    if ($up['file'] !== '' || post_bool('remove_logo')) {
        DB::setSetting('logo', $up['file']);
        Upload::delete($old);
    }
    Product::touch();
    flash(t('ui.saved'));
    redirect(base_url('admin/?p=settings'));
}

$s = fn (string $k, string $d = '') => (string) DB::setting($k, $d);
$logo = $s('logo');

View::adminStart(t('nav.settings'), 'settings');
?>
<div class="split-2 split-form">
    <section class="panel">
        <div class="panel-head"><h2><?= View::icon('tag') ?><?= e(t('settings.prices')) ?></h2></div>
        <form method="post" class="form" enctype="multipart/form-data" id="settings">
            <?= csrf_field() ?>
            <div class="grid-3">
                <label class="field"><span><?= e(t('settings.currency')) ?></span><input name="currency" value="<?= e($s('currency', '₺')) ?>" maxlength="6"></label>
                <label class="field"><span><?= e(t('settings.currency_pos')) ?></span><select name="currency_pos"><option value="after"><?= e(t('settings.after')) ?></option><option value="before" <?= $s('currency_pos') === 'before' ? 'selected' : '' ?>><?= e(t('settings.before')) ?></option></select></label>
                <label class="field"><span><?= e(t('settings.decimals')) ?></span><select name="price_decimals">
                    <option value="auto"><?= e(t('settings.dec_auto')) ?></option>
                    <option value="0" <?= $s('price_decimals') === '0' ? 'selected' : '' ?>><?= e(t('settings.dec_0')) ?></option>
                    <option value="2" <?= $s('price_decimals') === '2' ? 'selected' : '' ?>><?= e(t('settings.dec_2')) ?></option>
                </select></label>
            </div>
            <p class="muted"><?= e(t('settings.example', ['a' => Menu::price(120), 'b' => Menu::price(72.5)])) ?></p>

            <div class="field"><span><?= e(t('settings.logo')) ?> <em class="muted">(<?= e(t('ui.optional')) ?>)</em></span>
                <div class="imgpick imgpick-logo">
                    <?php if ($logo) : ?><img src="<?= e(Menu::imageUrl($logo)) ?>" alt=""><?php else : ?><span class="imgpick-empty"><?= View::icon('image', 28) ?></span><?php endif; ?>
                    <div><input type="file" name="logo" accept="image/png,image/webp,image/jpeg"><small class="muted"><?= e(t('settings.logo_hint')) ?></small>
                    <?php if ($logo) : ?><label class="check"><input type="checkbox" name="remove_logo" value="1"><span><?= e(t('settings.remove_logo')) ?></span></label><?php endif; ?></div>
                </div>
            </div>
            <div class="form-actions"><button class="btn btn-primary"><?= e(t('ui.save')) ?></button></div>
        </form>
    </section>
    <section class="panel">
        <div class="panel-head"><h2><?= View::icon('tv') ?><?= e(t('settings.tv')) ?></h2></div>
        <ol class="steps">
            <li><?= e(t('settings.tv_1')) ?></li>
            <li><?= e(t('settings.tv_2')) ?></li>
            <li><?= e(t('settings.tv_3')) ?></li>
            <li><?= e(t('settings.tv_4')) ?></li>
        </ol>
        <div class="hint mt"><?= View::icon('alert', 16) ?><div><?= e(t('settings.tv_hint')) ?></div></div>
    </section>
</div>
<?php View::adminEnd(); ?>
