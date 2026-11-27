<?php

use Klxm\Fluid\Block;
use Redaxo\Core\Translation\I18n;

/**
 * Before/after: two images on top of each other, a slider (real <input type="range">: mouse, touch, keyboard, screen reader)
 * moves the dividing line. Side by side without JavaScript. Markup: fluid_compare() (functions.php), CSS css/hx-compare.css,
 * script js/compare.js. The kit „NativeCSS“ uses <ncss-compare>.
 * @var Block $b  @var array $d
 */
$ratio = in_array($d['ratio'] ?? '', ['16:9', '3:2', '4:3', '1:1', '4:5', '3:4'], true) ? (string) $d['ratio'] : '16:9';
$cmp = fluid_compare($d, '(min-width: 1080px) 1000px, 100vw', $ratio, 'hx-cmp');
?>
<div class="wrap cmp-block">
  <?= fluid_head($b) ?>
  <?php if ('' !== $cmp): ?>
  <figure class="cmp-block__fig">
    <div class="hx-ar-<?= e(str_replace(':', '-', $ratio)) ?>"><?= $cmp ?></div>
    <?php if ('' !== trim((string) $d['caption']) || is_editing()): ?><figcaption class="video__cap"<?= $b->edit('caption') ?>><?= fluid_title((string) $d['caption']) ?></figcaption><?php endif ?>
  </figure>
  <?= fluid_alt_hint($d['before'] ?? null) . fluid_alt_hint($d['after'] ?? null) ?>
  <?php elseif (is_editing()): ?><p class="empty-hint"><?= I18n::msg('fluid_kit_empty_compare') ?></p><?php endif ?>
</div>
