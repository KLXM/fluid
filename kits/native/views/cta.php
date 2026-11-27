<?php

use Klxm\Fluid\Block;
use Klxm\Fluid\Kit;

/**
 * Call to action in the kit „NativeCSS“:
 *   band  colored area in the brand color (.ncss-tile--brand, light text automatically)
 *   box   card with border on a tinted area (.ncss-tile--muted)
 * „split“ (with image) and „big“ (large lettering) come from the kit fluid.
 * @var Block $b  @var array $d
 */
$v = in_array($b->variant(), ['band', 'box', 'split', 'big'], true) ? $b->variant() : 'band';
if (!in_array($v, ['band', 'box'], true)) {
    require Kit::get('fluid')->view('cta');

    return;
}
?>
<div class="wrap">
  <div class="ncss-tile <?= 'band' === $v ? 'ncss-tile--brand' : 'ncss-tile--muted ncss-border' ?> ncss-radius-lg nv-cta nv-cta--<?= e($v) ?>">
    <div class="nv-cta__text">
      <?php if ('' !== trim((string) ($d['eyebrow'] ?? ''))): ?><p class="nv-cta__eyebrow"<?= $b->edit('eyebrow') ?>><?= e($d['eyebrow']) ?></p><?php endif ?>
      <h2 id="<?= e($b->titleId()) ?>" class="h2 nv-cta__title"<?= $b->edit('title') ?>><?= fluid_title((string) $d['title']) ?></h2>
      <?php if ('' !== $d['text'] || is_editing()): ?><p class="ncss-text-lead nv-cta__lead"<?= $b->edit('text') ?>><?= nl2br(e($d['text']), false) ?></p><?php endif ?>
    </div>
    <?= fluid_buttons($b, 'nv-cta__actions') ?>
  </div>
</div>
