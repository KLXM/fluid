<?php

use Klxm\Fluid\Block;
use Klxm\Fluid\Kit;
use Redaxo\Core\Translation\I18n;

/**
 * Scrollytelling: the image column stays in place (position: sticky), text sections scroll past; js/scrolly.js fades in
 * the matching image (IntersectionObserver). Container query: below ~46 rem block width each image sits directly above
 * its text (the sticky column is dropped). Without JavaScript the first image stays – all images also appear at the
 * text. With reduced motion the images change without crossfade.
 *  - Image per section optional: without its own image the previous one stays (data-img on the section = index of the
 *    section whose image applies; empty = no image yet). On narrow areas an image only appears with the section that brings it.
 *  - Image format „auto“: each image in its own aspect ratio (no cropping, portrait stays whole).
 *  - Text: rich text (formerly a text field) – old plain text appears unchanged as before. Subheadings in the text are
 *    always below the level of the section title (below h3 → h4).
 * @var Block $b  @var array $d
 */
$ratio = (string) ($d['ratio'] ?? '') ?: '4:5';
$auto = 'auto' === $ratio;
$items = array_values(array_filter((array) ($d['items'] ?? []), static fn ($i) => '' !== trim((string) ($i['title'] ?? '')) || is_editing()));
$tag = fluid_htag($d);
$stage = $show = [];
$cur = null;
$long = false;
foreach ($items as $i => $it) {
    $pic = !empty($it['image']) ? img((string) $it['image'], '(min-width: 1080px) 560px, 50vw', ($auto ? [] : ['ratio' => $ratio]) + ['alt' => '']) : '';
    if ('' !== $pic) {
        $stage[$i] = $pic;
        $cur = $i;
    }
    $show[$i] = $cur;
    if (!fluid_is_plain_text((string) ($it['text'] ?? ''))) {
        $long = true;
    }
}
if ($long) {
    Kit::active()->markUsed('@rich');   // css/prose.css for the texts
}
?>
<div class="wrap">
  <?= fluid_head($b) ?>
  <?php if ($items): ?>
  <div class="scrolly scrolly--<?= 'right' === $b->variant() ? 'right' : 'left' ?><?= $long ? ' scrolly--long' : '' ?>" data-scrolly>
    <div class="scrolly__stage" aria-hidden="true">
      <div class="scrolly__frame <?= $auto ? 'scrolly__frame--auto' : 'r-' . e(str_replace(':', '-', $ratio)) ?>">
        <?php foreach ($stage as $i => $pic): ?>
        <div class="scrolly__img<?= 0 === $i ? ' is-active' : '' ?>" data-step-img="<?= $i ?>"><?= $pic ?></div>
        <?php endforeach ?>
      </div>
    </div>
    <ol class="scrolly__steps" role="list">
      <?php foreach ($items as $i => $it): $text = (string) ($it['text'] ?? '') ?>
      <li class="scrolly__step<?= 0 === $i ? ' is-active' : '' ?>" data-step="<?= $i ?>" data-img="<?= $show[$i] ?? '' ?>">
        <?php if (isset($stage[$i])): ?><?= $auto ? '<div class="scrolly__inline scrolly__inline--auto">' . img((string) $it['image'], '100vw') . '</div>' : fluid_image((string) $it['image'], '100vw', $ratio, 'scrolly__inline') ?><?php endif ?>
        <span class="scrolly__num" aria-hidden="true"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
        <?php if ('' !== trim((string) ($it['eyebrow'] ?? ''))): ?><p class="eyebrow"<?= $b->edit("items.$i.eyebrow") ?>><?= fluid_title((string) $it['eyebrow']) ?></p><?php endif ?>
        <<?= $tag ?> class="scrolly__title"<?= $b->edit("items.$i.title") ?>><?= fluid_title((string) ($it['title'] ?? '')) ?></<?= $tag ?>>
        <?php if ('' === trim($text)): ?>
        <?php elseif (fluid_is_plain_text($text)): ?><p class="scrolly__text"<?= $b->edit("items.$i.text") ?>><?= nl2br(fluid_title($text), false) ?></p>
        <?php else: ?><div class="scrolly__text scrolly__rich prose"<?= $b->edit("items.$i.text", 'rich') ?>><?= fluid_rich_below(rich($text), (int) $tag[1]) ?></div>
        <?php endif ?>
      </li>
      <?php endforeach ?>
    </ol>
  </div>
  <?php elseif (is_editing()): ?><p class="empty-hint"><?= I18n::msg('fluid_kit_empty_scrolly') ?></p><?php endif ?>
</div>
