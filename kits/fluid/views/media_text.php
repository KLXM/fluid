<?php

use Klxm\Fluid\Block;

/**
 * Text + image. auto: switches sides if the previous block was also „Text + Bild“ (alternating) · right/left ·
 * split: borderless 50/50 (switcher: side by side while both halves are ≥ 22 rem wide) · overlap: card over a large image.
 * @var Block $b  @var array $d
 */
$v = $b->variant() ?: 'auto';
// Display variants of other kits (e.g. „Morphing“ in the kit NativeCSS) after a kit switch: like „Bild rechts“
if (!in_array($v, ['auto', 'right', 'left', 'split', 'overlap'], true)) {
$v = 'right';
}
$ratio = (string) ($d['ratio'] ?? '') ?: '4:3';
$side = $v;
// Alternating („auto“, likewise „split“): count the position in a sequence of blocks of the same kind
$n = 0;
for ($p = $b->prev; $p && 'media_text' === $p->type && ($p->data['variant'] ?? 'auto') === $v; $p = $p->prev) {
++$n;
}
if ('auto' === $v) {
$side = $n % 2 ? 'left' : 'right';
}
$list = fluid_lines($d['list'] ?? '');
// Image alignment: follow along (default for new blocks) · top · center (previously; existing blocks via DefaultChanges)
$valign = in_array($d['valign'] ?? '', ['follow', 'top', 'center'], true) ? $d['valign'] : 'follow';
$body = fluid_head($b, 'mt__head')
    . (('' !== trim(strip_tags((string) $d['text'])) || is_editing()) ? '<div class="prose"' . $b->edit('text', 'rich') . '>' . rich((string) $d['text']) . '</div>' : '')
    . fluid_checks($list)
    . fluid_buttons($b);
?>
<?php if ('split' === $v): ?>
<div class="mt-split<?= 'screen' === $b->tune('height') ? ' mt-split--screen' : '' ?><?= $n % 2 ? ' mt-split--flip' : '' ?>">
  <?= fluid_image($d['image'] ?? null, '(min-width: 900px) 50vw, 100vw', '', 'mt-split__media', ['eager' => null === $b->prev]) ?>
  <div class="mt-split__text"><div class="mt-split__inner"><?= $body ?></div></div>
</div>
<?php elseif ('overlap' === $v): ?>
<div class="wrap mt-over">
  <?= fluid_image($d['image'] ?? null, '(min-width: 1280px) 1280px, 100vw', '16:9', 'mt-over__media') ?>
  <div class="mt-over__card"><?= $body ?></div>
</div>
<?php else: ?>
<div class="wrap mt mt--<?= e($side) ?> mt--va-<?= e($valign) ?><?= in_array($d['split'] ?? 'even', ['text', 'media', 'mediaxl'], true) ? ' mt--s-' . e($d['split']) : '' ?>">
  <div class="mt__text"><?= $body ?></div>
  <?= fluid_image($d['image'] ?? null, '(min-width: 1080px) 600px, 100vw', $ratio, 'mt__media') ?>
</div>
<?php endif;
