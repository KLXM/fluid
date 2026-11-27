<?php
/** Call to action: band · box · split (with image, switcher) · big (large lettering in container units). @var \Klxm\Fluid\Block $b  @var array $d */
$v = in_array($b->variant(), ['band', 'box', 'split', 'big'], true) ? $b->variant() : 'band';
$pic = 'split' === $v ? fluid_image($d['image'] ?? null, '(min-width: 1080px) 560px, 100vw', '4:3', 'cta__media') : '';
?>
<div class="wrap">
  <div class="cta cta--<?= e($v) ?><?= '' !== $pic ? ' cta--media' : '' ?>">
    <div class="cta__main">
    <div class="cta__text">
      <?php if ('' !== trim((string) ($d['eyebrow'] ?? ''))): ?><p class="eyebrow"<?= $b->edit('eyebrow') ?>><?= e($d['eyebrow']) ?></p><?php endif ?>
      <h2 id="<?= e($b->titleId()) ?>" class="<?= 'big' === $v ? 'cta__big' : 'h2' ?> cta__title"<?= $b->edit('title') ?>><?= fluid_title((string) $d['title']) ?></h2>
      <?php if ('' !== $d['text'] || is_editing()): ?><p class="cta__lead"<?= $b->edit('text') ?>><?= nl2br(e($d['text']), false) ?></p><?php endif ?>
    </div>
    <?= fluid_buttons($b, 'cta__actions') ?>
    </div>
    <?= $pic ?>
  </div>
</div>
