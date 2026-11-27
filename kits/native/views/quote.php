<?php

use Klxm\Fluid\Block;
use Klxm\Fluid\Kit;

/**
 * Quote / testimonials in the kit „NativeCSS“: „Groß“ as a featured testimonial (.ncss-testimonial--featured), „Raster“ as
 * a grid of equal-height cards with an aligned person row (.ncss-testimonial-grid) – figure/blockquote/figcaption, the
 * name as text (not <cite>). „Wischbares Band“ and „Laufband“ come from the kit fluid.
 * @var Block $b  @var array $d
 */
$v = $b->variant() ?: 'single';
if (!in_array($v, ['single', 'grid'], true)) {
    require Kit::get('fluid')->view('quote');

    return;
}
Kit::active()->markUsed('quote', '@testimonial');
$items = array_values(array_filter((array) ($d['items'] ?? []), static fn ($i) => '' !== trim((string) ($i['text'] ?? ''))));
if ('single' === $v && count($items) > 1 && !is_editing()) {
    $items = [$items[0]];
}
$fig = static function (array $it, int $i, string $mod) use ($b): string {
    $pic = !empty($it['image']) ? img((string) $it['image'], '48px', ['ratio' => '1:1', 'alt' => '', 'class' => 'nv-quote__avatar']) : '';
    $by = '' !== trim(($it['name'] ?? '') . ($it['role'] ?? ''))
        ? '<figcaption class="ncss-testimonial-author">' . $pic . '<span class="ncss-testimonial-person">'
            . '<span class="ncss-testimonial-name"' . $b->edit("items.$i.name") . '>' . e($it['name'] ?? '') . '</span>'
            . (($it['role'] ?? '') !== '' ? '<span class="ncss-testimonial-role"' . $b->edit("items.$i.role") . '>' . e($it['role']) . '</span>' : '')
            . '</span></figcaption>'
        : '';

    return '<figure class="ncss-testimonial' . $mod . '"><blockquote class="ncss-testimonial-quote"><p' . $b->edit("items.$i.text") . '>'
        . nl2br(e($it['text']), false) . '</p></blockquote>' . $by . '</figure>';
};
?>
<div class="wrap nv-quotes">
  <?= fluid_head($b, 'single' === $v ? 'sec-head--center' : '') ?>
  <?php if ($items && 'single' === $v): ?>
  <?= $fig($items[0], 0, ' ncss-testimonial--featured') ?>
  <?php elseif ($items): ?>
  <ul class="ncss-testimonial-grid" role="list">
    <?php foreach ($items as $i => $it): ?><li data-reveal><?= $fig($it, $i, '') ?></li><?php endforeach ?>
  </ul>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch kein Zitat – in der Seitenleiste hinzufügen.</p><?php endif ?>
</div>
