<?php

use Klxm\Fluid\Block;

/**
 * Vorher/Nachher: zwei Bilder übereinander, Regler (echtes <input type="range">: Maus, Touch, Tastatur, Screenreader)
 * verschiebt die Trennlinie. Ohne JavaScript nebeneinander. Markup: fluid_compare() (functions.php), CSS css/hx-compare.css,
 * Skript js/compare.js. Das Kit „NativeCSS“ nimmt <ncss-compare>.
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
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch keine Bilder – in der Seitenleiste „Bild vorher“ und „Bild nachher“ wählen.</p><?php endif ?>
</div>
