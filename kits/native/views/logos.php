<?php

use Klxm\Fluid\Block;
use Klxm\Fluid\Kit;

/**
 * Logos im Kit „NativeCSS“: „Laufband“ mit .ncss-marquee von NativeCSS – weiche Ränder, hält bei Maus/Fokus an, steht bei
 * „Bewegung reduzieren“ still (dann horizontal scrollbar) und hat eine sichtbare Schaltfläche „Laufband anhalten“
 * (WCAG 2.2.2; js/marquee.js). Beim Bearbeiten und mit „Dezente Animationen“ aus: ruhiges Raster. „Raster“ kommt von fluid.
 * @var Block $b  @var array $d
 */
$motion = false !== (bool) (Kit::active()->design()->get('motion') ?? true);
if ('marquee' !== $b->variant() || is_editing() || !$motion) {
    require Kit::get('fluid')->view('logos');

    return;
}
Kit::active()->markUsed('logos', '@marquee');
$items = array_values((array) ($d['items'] ?? []));
$id = $b->domId() . '-marquee';
$render = static function () use ($items): string {
    $h = '';
    foreach ($items as $it) {
        $pic = !empty($it['image']) ? img((string) $it['image'], '200px', ['alt' => (string) $it['name']]) : '';
        $inner = '' !== $pic ? $pic : '<span class="logos__name">' . e($it['name']) . '</span>';
        $link = trim((string) ($it['link'] ?? ''));
        $h .= '<li class="logos__item' . ('' !== $pic ? ' logos__item--img' : '') . '">' . ('' !== $link ? '<a class="logos__link" ' . fluid_link_attrs($link) . '>' . $inner . '</a>' : $inner) . '</li>';
    }

    return $h;
};
?>
<div class="wrap nv-logos">
  <?= fluid_head($b, 'sec-head--center') ?>
  <?php if ($items): ?>
  <div class="ncss-marquee ncss-marquee--fade nv-logos__marquee" id="<?= e($id) ?>" data-ncss-marquee>
    <ul class="ncss-marquee-group" role="list"><?= $render() ?></ul>
  </div>
  <p class="nv-logos__pause"><button type="button" class="ncss-btn ncss-btn--secondary nv-logos__toggle" data-ncss-marquee-toggle aria-controls="<?= e($id) ?>" aria-pressed="false"><?= e(lt('Laufband anhalten')) ?></button></p>
  <?php endif ?>
</div>
