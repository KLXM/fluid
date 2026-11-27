<?php

use Klxm\Fluid\Block;
use Klxm\Fluid\Kit;

/**
 * Scrollytelling in the kit „NativeCSS“ with <ncss-scrolly>: the sections pass by as cards, the image beside them stays
 * and changes to the active section; on narrow screens the image is on top and the sections scroll below it. Without
 * JavaScript sections and images are stacked. Sections without their own image keep the previous one.
 * While editing fluid's output (image directly at the text). Text: rich text (formerly text field) – old plain text as
 * before; image format „auto“: each image uncropped.
 * @var Block $b  @var array $d
 */
if (is_editing()) {
    require Kit::get('fluid')->view('scrolly');

    return;
}
$ratio = (string) ($d['ratio'] ?? '') ?: '4:5';
$items = array_values(array_filter((array) ($d['items'] ?? []), static fn ($i) => '' !== trim((string) ($i['title'] ?? ''))));
$tag = fluid_htag($d);
$img = '';
$fmt = 'auto' === $ratio ? [] : ['ratio' => $ratio];
?>
<div class="wrap nv-scrolly<?= $fmt ? '' : ' nv-scrolly--auto' ?>">
  <?= fluid_head($b) ?>
  <?php if ($items): ?>
  <ncss-scrolly<?= 'right' === $b->variant() ? '' : ' media-start' ?>>
    <ol class="ncss-scrolly-steps" role="list">
      <?php foreach ($items as $i => $it): ?>
      <li class="ncss-scrolly-step"><div>
        <?php if ('' !== trim((string) ($it['eyebrow'] ?? ''))): ?><p class="eyebrow"><?= fluid_title((string) $it['eyebrow']) ?></p><?php endif ?>
        <<?= $tag ?> class="nv-scrolly__title"><?= fluid_title((string) $it['title']) ?></<?= $tag ?>>
        <?php $text = (string) ($it['text'] ?? '');
        if ('' === trim($text)): ?>
        <?php elseif (fluid_is_plain_text($text)): ?><p><?= nl2br(fluid_title($text), false) ?></p>
        <?php else: Kit::active()->markUsed('@rich') ?><div class="prose"><?= fluid_rich_below(rich($text), (int) $tag[1]) ?></div><?php endif ?>
      </div></li>
      <?php endforeach ?>
    </ol>
    <div class="ncss-scrolly-media" aria-hidden="true">
      <?php foreach ($items as $i => $it):
          $img = !empty($it['image']) ? (string) $it['image'] : $img ?>
      <figure class="ncss-scrolly-figure"><?= '' !== $img ? img($img, '(min-width: 1080px) 560px, 90vw', $fmt + ['alt' => '']) : '' ?></figure>
      <?php endforeach ?>
    </div>
  </ncss-scrolly>
  <?php endif ?>
</div>
