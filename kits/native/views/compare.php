<?php

use Klxm\Fluid\Block;
use Klxm\Fluid\InlineStyle;
use Redaxo\Core\Translation\I18n;

/**
 * Before/after in the kit „NativeCSS“ with <ncss-compare>: real slider (input range) over both images, without JavaScript a
 * static 50/50 comparison. „Vorher“ is always on the left. CSS/JS: css/ncss-compare.css, ncss/js/compare.js.
 * @var Block $b  @var array $d
 */
$ratio = in_array($d['ratio'] ?? '', ['16:9', '3:2', '4:3', '1:1', '4:5', '3:4'], true) ? (string) $d['ratio'] : '16:9';
$sizes = '(min-width: 1080px) 1000px, 100vw';
$before = img((string) ($d['before'] ?? ''), $sizes, ['ratio' => $ratio, 'class' => 'ncss-compare-before']);
$after = img((string) ($d['after'] ?? ''), $sizes, ['ratio' => $ratio, 'class' => 'ncss-compare-after']);
$lb = trim((string) ($d['label_before'] ?? '')) ?: lt('Vorher');
$la = trim((string) ($d['label_after'] ?? '')) ?: lt('Nachher');
?>
<div class="wrap nv-compare">
  <?= fluid_head($b) ?>
  <?php if ('' !== $before && '' !== $after): ?>
  <figure class="nv-compare__fig">
    <ncss-compare class="<?= e(InlineStyle::class('--ncss-compare-ratio:' . str_replace(':', ' / ', $ratio))) ?>">
      <?= $before ?>
      <?= $after ?>
      <span class="ncss-compare-label" aria-hidden="true"><?= e($lb) ?></span>
      <span class="ncss-compare-label ncss-compare-label--end" aria-hidden="true"><?= e($la) ?></span>
      <input class="ncss-compare-range" type="range" min="0" max="100" value="50" aria-label="<?= e(lt('Trennlinie verschieben: links {a}, rechts {b}', ['a' => $lb, 'b' => $la])) ?>">
    </ncss-compare>
    <?php if ('' !== trim((string) $d['caption']) || is_editing()): ?><figcaption class="video__cap"<?= $b->edit('caption') ?>><?= fluid_title((string) $d['caption']) ?></figcaption><?php endif ?>
  </figure>
  <?= fluid_alt_hint($d['before'] ?? null) . fluid_alt_hint($d['after'] ?? null) ?>
  <?php elseif (is_editing()): ?><p class="empty-hint"><?= I18n::msg('fluid_kit_empty_compare') ?></p><?php endif ?>
</div>
