<?php

declare(strict_types=1);

$self   = base_url('admin/?p=campaigns');
$colors = ['accent' => t('camp.c_accent'), 'warm' => t('camp.c_warm'), 'cool' => t('camp.c_cool'), 'dark' => t('camp.c_dark')];
$dayNames = [1 => t('day.1'), 2 => t('day.2'), 3 => t('day.3'), 4 => t('day.4'), 5 => t('day.5'), 6 => t('day.6'), 7 => t('day.7')];

if (is_post()) {
    csrf_check();
    $action = post_str('action');

    if ($move = post_str('move')) {
        [$id, $dir] = array_pad(explode(':', $move), 2, '');
        $ids = array_map('intval', array_column(DB::all('SELECT id FROM {campaigns} ORDER BY sort_order, id'), 'id'));
        $i = array_search((int) $id, $ids, true);
        $j = $dir === 'up' ? $i - 1 : $i + 1;
        if ($i !== false && isset($ids[$j])) {
            [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
            foreach ($ids as $n => $cid) {
                DB::update('campaigns', ['sort_order' => ($n + 1) * 10], 'id = ?', [$cid]);
            }
            Product::touch();
        }
        redirect($self);
    }

    if ($action === 'save') {
        $id    = post_int('id');
        $old   = $id ? DB::row('SELECT * FROM {campaigns} WHERE id = ?', [$id]) : null;
        $title = mb_substr(post_str('title'), 0, 120);
        if ($title === '') {
            flash(t('camp.err_title'), 'error');
            redirect($self . ($id ? '&edit=' . $id : ''));
        }
        $up = Upload::save('image');
        if (!$up['ok']) {
            flash($up['error'], 'error');
        }
        $image = $old['image'] ?? '';
        $prev = '';
        if ($up['file'] !== '' || post_bool('remove_image')) {
            $prev = $image;
            $image = $up['file'];
        }
        $days = implode('', array_filter(array_map('intval', (array) ($_POST['days'] ?? [])), fn ($d) => $d >= 1 && $d <= 7));
        $time = fn (string $k) => preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', post_str($k)) ? post_str($k) : '';
        $date = fn (string $k) => preg_match('/^\d{4}-\d{2}-\d{2}$/', post_str($k)) ? post_str($k) : '';
        $num  = fn (string $k) => post_str($k) !== '' ? parse_num(post_str($k)) : null;
        $color = post_str('color');
        $data = [
            'title'     => $title,
            'subtitle'  => mb_substr(post_str('subtitle'), 0, 240),
            'price'     => $num('price'),
            'old_price' => $num('old_price'),
            'image'     => $image,
            'color'     => isset($colors[$color]) ? $color : 'accent',
            'days'      => $days ?: '1234567',
            'time_from' => $time('time_from'),
            'time_to'   => $time('time_to'),
            'date_from' => $date('date_from'),
            'date_to'   => $date('date_to'),
            'is_active' => post_bool('is_active'),
        ];
        if ($old) {
            DB::update('campaigns', $data, 'id = ?', [$id]);
        } else {
            $data['sort_order'] = (int) DB::val('SELECT COALESCE(MAX(sort_order), 0) FROM {campaigns}') + 10;
            $data['created_at'] = time();
            DB::insert('campaigns', $data);
        }
        if ($prev !== '') {
            Upload::delete($prev);
        }
        Product::touch();
        flash(t('ui.saved'));
        redirect($self);
    }

    if ($action === 'delete') {
        $c = DB::row('SELECT * FROM {campaigns} WHERE id = ?', [post_int('id')]);
        if ($c) {
            DB::q('DELETE FROM {campaigns} WHERE id = ?', [(int) $c['id']]);
            Upload::delete((string) $c['image']);
            Product::touch();
            flash(t('ui.deleted'));
        }
        redirect($self);
    }
}

$list = DB::all('SELECT * FROM {campaigns} ORDER BY sort_order, id');
$edit = get_int('edit') ? DB::row('SELECT * FROM {campaigns} WHERE id = ?', [get_int('edit')]) : null;
$c = $edit ?: ['id' => 0, 'title' => '', 'subtitle' => '', 'price' => null, 'old_price' => null, 'image' => '', 'color' => 'accent', 'days' => '1234567', 'time_from' => '', 'time_to' => '', 'date_from' => '', 'date_to' => '', 'is_active' => 1];
$fmt = fn ($v) => $v === null ? '' : number_format((float) $v, abs((float) $v - round((float) $v)) < 0.005 ? 0 : 2, ',', '.');
$when = function (array $k) use ($dayNames): string {
    $parts = [];
    if ($k['days'] !== '1234567') {
        $parts[] = implode(', ', array_map(fn ($d) => t('day_s.' . (int) $d), str_split((string) $k['days'])));
    }
    if ($k['time_from'] && $k['time_to']) {
        $parts[] = $k['time_from'] . '–' . $k['time_to'];
    }
    if ($k['date_from'] || $k['date_to']) {
        $parts[] = ($k['date_from'] ? date('d.m.Y', strtotime($k['date_from'])) : '…') . ' – ' . ($k['date_to'] ? date('d.m.Y', strtotime($k['date_to'])) : '…');
    }
    return $parts ? implode(' · ', $parts) : t('camp.always');
};

View::adminStart(t('nav.campaigns'), 'campaigns');
?>
<div class="split-2 split-form">
    <section class="panel">
        <div class="panel-head"><h2><?= View::icon('spark') ?><?= e(t('camp.list')) ?></h2></div>
        <p class="muted"><?= e(t('camp.desc')) ?></p>
        <?php if ($list) : ?>
            <form method="post" class="mt">
                <?= csrf_field() ?>
                <div class="table-wrap"><table class="table">
                    <tbody>
                    <?php foreach ($list as $n => $k) : ?>
                        <tr class="<?= $k['is_active'] ? '' : 'is-off' ?>">
                            <td class="nowrap">
                                <button class="iconbtn iconbtn-sm" name="move" value="<?= (int) $k['id'] ?>:up" <?= $n === 0 ? 'disabled' : '' ?>><?= View::icon('arrow-up', 14) ?></button>
                                <button class="iconbtn iconbtn-sm" name="move" value="<?= (int) $k['id'] ?>:down" <?= $n === count($list) - 1 ? 'disabled' : '' ?>><?= View::icon('arrow-dn', 14) ?></button>
                            </td>
                            <td><span class="swatch swatch-<?= e($k['color']) ?>"></span></td>
                            <td><a class="strong" href="<?= e($self . '&edit=' . $k['id']) ?>"><?= e($k['title']) ?></a><span class="sub"><?= e($when($k)) ?></span></td>
                            <td class="num nowrap"><?= $k['price'] !== null ? e(Menu::price((float) $k['price'])) : '' ?></td>
                            <td class="actions">
                                <a class="iconbtn iconbtn-sm" href="<?= e($self . '&edit=' . $k['id']) ?>"><?= View::icon('edit', 14) ?></a>
                                <button class="iconbtn iconbtn-sm btn-ghost-danger" form="del-<?= (int) $k['id'] ?>" data-confirm="<?= e(t('camp.confirm_delete', ['n' => $k['title']])) ?>"><?= View::icon('trash', 14) ?></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
            </form>
            <?php foreach ($list as $k) : ?>
                <form method="post" id="del-<?= (int) $k['id'] ?>" hidden><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $k['id'] ?>"></form>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <section class="panel">
        <div class="panel-head"><h2><?= View::icon($c['id'] ? 'edit' : 'plus') ?><?= e($c['id'] ? t('camp.edit') : t('camp.new')) ?></h2><?php if ($c['id']) : ?><a class="btn btn-sm" href="<?= e($self) ?>"><?= e(t('camp.new')) ?></a><?php endif; ?></div>
        <form method="post" class="form" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
            <label class="field"><span><?= e(t('camp.title')) ?></span><input name="title" value="<?= e($c['title']) ?>" required maxlength="120" placeholder="<?= e(t('camp.title_ph')) ?>"></label>
            <label class="field"><span><?= e(t('camp.subtitle')) ?> <em class="muted">(<?= e(t('ui.optional')) ?>)</em></span><input name="subtitle" value="<?= e($c['subtitle']) ?>" maxlength="240"></label>
            <div class="grid-2">
                <label class="field"><span><?= e(t('camp.price')) ?></span><span class="input-suffix"><input name="price" value="<?= e($fmt($c['price'])) ?>" inputmode="decimal"><em><?= e(DB::setting('currency', '₺')) ?></em></span></label>
                <label class="field"><span><?= e(t('product.old_price')) ?></span><span class="input-suffix"><input name="old_price" value="<?= e($fmt($c['old_price'])) ?>" inputmode="decimal"><em><?= e(DB::setting('currency', '₺')) ?></em></span></label>
            </div>
            <fieldset class="field"><span><?= e(t('camp.color')) ?></span>
                <div class="swatches"><?php foreach ($colors as $k => $l) : ?><label><input type="radio" name="color" value="<?= e($k) ?>" <?= $c['color'] === $k ? 'checked' : '' ?>><span><i class="swatch-<?= e($k) ?>"></i><?= e($l) ?></span></label><?php endforeach; ?></div>
                <small><?= e(t('camp.color_hint')) ?></small>
            </fieldset>
            <div class="field"><span><?= e(t('camp.image')) ?> <em class="muted">(<?= e(t('ui.optional')) ?>)</em></span>
                <div class="imgpick">
                    <?php if ($c['image']) : ?><img src="<?= e(Menu::imageUrl($c['image'])) ?>" alt=""><?php else : ?><span class="imgpick-empty"><?= View::icon('image', 28) ?></span><?php endif; ?>
                    <div><input type="file" name="image" accept="image/jpeg,image/png,image/webp"><small class="muted"><?= e(t('camp.image_hint')) ?></small>
                    <?php if ($c['image']) : ?><label class="check"><input type="checkbox" name="remove_image" value="1"><span><?= e(t('product.remove_image')) ?></span></label><?php endif; ?></div>
                </div>
            </div>
            <fieldset class="field"><span><?= e(t('camp.days')) ?></span>
                <div class="days"><?php foreach ($dayNames as $d => $l) : ?><label><input type="checkbox" name="days[]" value="<?= $d ?>" <?= str_contains((string) $c['days'], (string) $d) ? 'checked' : '' ?>><span title="<?= e($l) ?>"><?= e(t('day_s.' . $d)) ?></span></label><?php endforeach; ?></div>
            </fieldset>
            <div class="grid-2">
                <label class="field"><span><?= e(t('camp.time_from')) ?></span><input type="time" name="time_from" value="<?= e($c['time_from']) ?>"></label>
                <label class="field"><span><?= e(t('camp.time_to')) ?></span><input type="time" name="time_to" value="<?= e($c['time_to']) ?>"></label>
                <label class="field"><span><?= e(t('camp.date_from')) ?></span><input type="date" name="date_from" value="<?= e($c['date_from']) ?>"></label>
                <label class="field"><span><?= e(t('camp.date_to')) ?></span><input type="date" name="date_to" value="<?= e($c['date_to']) ?>"></label>
            </div>
            <p class="muted"><?= e(t('camp.when_hint')) ?></p>
            <label class="check"><input type="checkbox" name="is_active" value="1" <?= $c['is_active'] ? 'checked' : '' ?>><span><?= e(t('camp.active')) ?></span></label>
            <div class="form-actions"><button class="btn btn-primary"><?= e(t('ui.save')) ?></button></div>
        </form>
    </section>
</div>
<?php View::adminEnd(); ?>
