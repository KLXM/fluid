<?php

use Klxm\Fluid\Block;
use Redaxo\Core\Translation\I18n;

/**
 * Logos: grid (wrapping row, centered) · marquee (pure CSS marquee: list doubled, second half aria-hidden;
 * pauses on hover/focus, stands still as a wrapping row with „Bewegung reduzieren“ or motion turned off).
 * @var Block $b  @var array $d
 */
$v = 'marquee' === $b->variant() ? 'marquee' : 'grid';
$items = array_values((array) ($d['items'] ?? []));
$render = static function (bool $dup) use ($items, $b): string {
    $h = '';
    foreach ($items as $i => $it) {
        $pic = !empty($it['image']) ? img((string) $it['image'], '200px', ['alt' => $dup ? '' : (string) $it['name']]) : '';
        $inner = '' !== $pic ? $pic : '<span class="logos__name"' . ($dup ? '' : $b->edit("items.$i.name")) . '>' . e($it['name']) . '</span>';
        $link = trim((string) ($it['link'] ?? ''));
        $h .= '<li class="logos__item' . ('' !== $pic ? ' logos__item--img' : '') . '">'
            . ('' !== $link && !is_editing() && !$dup ? '<a class="logos__link" ' . fluid_link_attrs($link) . '>' . $inner . '</a>' : $inner) . '</li>';
    }
    return $h;
};
?>
<div class="wrap">
  <?= fluid_head($b, 'sec-head--center') ?>
  <?php if ($items): ?>
  <?php if ('marquee' === $v): ?>
  <div class="marquee">
    <ul class="marquee__track" role="list"><?= $render(false) ?></ul>
    <ul class="marquee__track marquee__track--dup" role="list" aria-hidden="true"><?= $render(true) ?></ul>
  </div>
  <?php else: ?>
  <ul class="logos" role="list"><?= $render(false) ?></ul>
  <?php endif ?>
  <?php elseif (is_editing()): ?><p class="empty-hint"><?= I18n::msg('fluid_kit_empty_logos') ?></p><?php endif ?>
</div>
