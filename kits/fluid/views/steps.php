<?php

use Klxm\Fluid\Block;

/**
 * Ablauf: numbers (Raster, auto-fit) · timeline (senkrechte Linie; ab genug Containerbreite Datum links) ·
 * process (Schritte mit Pfeilen – nebeneinander, wenn Platz ist, sonst untereinander mit Pfeil nach unten).
 * Felder marker (Kreise), colors (Farben), motion (Animation) – Klassen steps--m-*, steps--c-*, steps--a-* (assets/css/b-steps.css).
 * @var Block $b  @var array $d
 */
$v = in_array($b->variant(), ['numbers', 'timeline', 'process'], true) ? $b->variant() : 'numbers';
$items = array_values(array_filter((array) ($d['items'] ?? []), static fn ($i) => '' !== trim((string) ($i['title'] ?? ''))));
$tag = fluid_htag($d);
// Kreise, Farben, Animation (Feldwerte; „auto“ = wie Design bzw. Website). Farben ohne eigene Kreis-Wahl: zart gefüllt mit Rand
$pick = static fn (string $k, array $ok) => in_array($d[$k] ?? 'auto', $ok, true) ? (string) $d[$k] : 'auto';
$colors = $pick('colors', ['alternate', 'accent', 'neutral']);
$marker = 'numbers' === $v ? 'auto' : $pick('marker', ['filled', 'soft', 'ring', 'dot']);
if ('auto' === $marker && 'auto' !== $colors && 'numbers' !== $v) {
$marker = 'soft';
}
$motion = $pick('motion', ['sequence', 'draw', 'none']);
if ('draw' === $motion && 'timeline' !== $v) {
$motion = 'auto';
}
$cls = ('auto' !== $marker ? ' steps--m-' . $marker : '') . ('auto' !== $colors ? ' steps--c-' . $colors : '') . ('auto' !== $motion ? ' steps--a-' . $motion : '');
?>
<div class="wrap">
  <?= fluid_head($b) ?>
  <?php if ($items): ?>
  <ol class="steps steps--<?= e($v) ?><?= 'numbers' === $v ? ' grid min-s' : '' ?><?= e($cls) ?>" role="list">
    <?php foreach ($items as $i => $it): ?>
    <li class="step"<?= 'none' === $motion ? '' : ' data-reveal' ?>>
      <span class="step__num" aria-hidden="true"><?= !empty($it['icon']) ? icon((string) $it['icon']) : str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
      <div class="step__body">
        <?php if ('' !== trim((string) ($it['meta'] ?? ''))): ?><p class="step__meta"<?= $b->edit("items.$i.meta") ?>><?= e($it['meta']) ?></p><?php endif ?>
        <<?= $tag ?> class="step__title"><span class="sr-only"><?= e(lt('Schritt {nr}:', ['nr' => $i + 1])) ?> </span><span<?= $b->edit("items.$i.title") ?>><?= e($it['title']) ?></span></<?= $tag ?>>
        <?php if ('' !== trim((string) ($it['text'] ?? ''))): ?><p class="step__text"<?= $b->edit("items.$i.text") ?>><?= nl2br(e($it['text']), false) ?></p><?php endif ?>
      </div>
    </li>
    <?php endforeach ?>
  </ol>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch keine Schritte – in der Seitenleiste hinzufügen.</p><?php endif ?>
</div>
