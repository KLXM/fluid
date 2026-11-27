<?php

use Klxm\Fluid\Block;
use Klxm\Fluid\Kit;

/**
 * Steps / timeline in kit „NativeCSS“: „Zeitleiste“ with .ncss-timeline of NativeCSS (date, marker as dot or
 * icon of the step, alternating left/right from ~40rem container width). „Nummerierte Schritte“ and „Prozess mit
 * Pfeilen“ come from kit fluid – the NativeCSS stepper shows progress (done/current/open), which
 * doesn't suit instructions. Options: „Kreise“ → marker with number (or icon) filled/subtle/outline, „Kleiner
 * Punkt“ without number; „Farben“ → accent, alternating or neutral; „Animation“ → fade in entries (like the website),
 * „Linie wächst“ → .ncss-reveal (wipe from the top) on the timeline, „Keine“ none. Reduced motion: no animation.
 * @var Block $b  @var array $d
 */
if ('timeline' !== $b->variant()) {
    require Kit::get('fluid')->view('steps');

    return;
}
Kit::active()->markUsed('steps', '@timeline');
$items = array_values(array_filter((array) ($d['items'] ?? []), static fn ($i) => '' !== trim((string) ($i['title'] ?? ''))));
$tag = fluid_htag($d);
$pick = static fn (string $k, array $ok, string $def) => in_array($d[$k] ?? '', $ok, true) ? (string) $d[$k] : $def;
$marker = $pick('marker', ['filled', 'soft', 'ring', 'dot'], 'soft');
$colors = $pick('colors', ['alternate', 'accent', 'neutral'], 'accent');
$motion = $pick('motion', ['sequence', 'draw', 'none'], 'auto');
$motionOn = !is_editing() && false !== (bool) (Kit::active()->design()->get('motion') ?? true);
$draw = 'draw' === $motion && $motionOn;
if ($draw) {
    Kit::active()->markUsed('@reveal');
}
?>
<div class="wrap nv-timeline">
  <?= fluid_head($b) ?>
  <?php if ($items): ?>
  <ol class="ncss-timeline ncss-timeline--alternate nv-tl--m-<?= e($marker) ?> nv-tl--c-<?= e($colors) ?><?= $draw ? ' ncss-reveal ncss-reveal--wipe-down' : '' ?>" role="list"<?= $draw ? ' data-ncss-reveal' : '' ?>>
    <?php foreach ($items as $i => $it): ?>
    <li class="ncss-timeline-item"<?= in_array($motion, ['auto', 'sequence'], true) ? ' data-reveal' : '' ?>>
      <span class="ncss-timeline-marker" aria-hidden="true"><?= 'dot' === $marker ? '' : (!empty($it['icon']) ? icon((string) $it['icon']) : '<span class="nv-tl__num">' . ($i + 1) . '</span>') ?></span>
      <div class="ncss-timeline-content">
        <?php if ('' !== trim((string) ($it['meta'] ?? ''))): ?><p class="ncss-timeline-date"<?= $b->edit("items.$i.meta") ?>><?= e($it['meta']) ?></p><?php endif ?>
        <<?= $tag ?> class="ncss-timeline-title"><span<?= $b->edit("items.$i.title") ?>><?= e($it['title']) ?></span></<?= $tag ?>>
        <?php if ('' !== trim((string) ($it['text'] ?? ''))): ?><p<?= $b->edit("items.$i.text") ?>><?= nl2br(e($it['text']), false) ?></p><?php endif ?>
      </div>
    </li>
    <?php endforeach ?>
  </ol>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch keine Schritte – in der Seitenleiste hinzufügen.</p><?php endif ?>
</div>
