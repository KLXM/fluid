<?php
/**
 * Ablauf / Zeitleiste im Kit „NativeCSS“: „Zeitleiste“ mit .ncss-timeline von NativeCSS (Datum, Markierung als Punkt oder
 * Symbol des Schritts, ab ~40rem Containerbreite abwechselnd links/rechts). „Nummerierte Schritte“ und „Prozess mit
 * Pfeilen“ kommen aus dem Kit fluid – der Stepper von NativeCSS zeigt einen Fortschritt (erledigt/aktuell/offen), das
 * passt nicht zu einer Anleitung. Optionen: „Kreise“ → Markierung mit Nummer (bzw. Symbol) gefüllt/zart/Rand, „Kleiner
 * Punkt“ ohne Nummer; „Farben“ → Akzent, abwechselnd oder neutral; „Animation“ → Einträge einblenden (wie die Website),
 * „Linie wächst“ → .ncss-reveal (Wischen von oben) an der Zeitleiste, „Keine“ ohne. Bewegung reduzieren: keine Animation.
 * @var \Klxm\Fluid\Block $b  @var array $d
 */
if ('timeline' !== $b->variant()) {
    require \Klxm\Fluid\Kit::get('fluid')->view('steps');

    return;
}
\Klxm\Fluid\Kit::active()->markUsed('steps', '@timeline');
$items = array_values(array_filter((array) ($d['items'] ?? []), fn ($i) => trim((string) ($i['title'] ?? '')) !== ''));
$tag = fluid_htag($d);
$pick = static fn (string $k, array $ok, string $def) => in_array($d[$k] ?? '', $ok, true) ? (string) $d[$k] : $def;
$marker = $pick('marker', ['filled', 'soft', 'ring', 'dot'], 'soft');
$colors = $pick('colors', ['alternate', 'accent', 'neutral'], 'accent');
$motion = $pick('motion', ['sequence', 'draw', 'none'], 'auto');
$motionOn = !is_editing() && false !== (bool) (\Klxm\Fluid\Kit::active()->design()->get('motion') ?? true);
$draw = 'draw' === $motion && $motionOn;
if ($draw) {
    \Klxm\Fluid\Kit::active()->markUsed('@reveal');
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
        <?php if (trim((string) ($it['meta'] ?? '')) !== ''): ?><p class="ncss-timeline-date"<?= $b->edit("items.$i.meta") ?>><?= e($it['meta']) ?></p><?php endif; ?>
        <<?= $tag ?> class="ncss-timeline-title"><span<?= $b->edit("items.$i.title") ?>><?= e($it['title']) ?></span></<?= $tag ?>>
        <?php if (trim((string) ($it['text'] ?? '')) !== ''): ?><p<?= $b->edit("items.$i.text") ?>><?= nl2br(e($it['text']), false) ?></p><?php endif; ?>
      </div>
    </li>
    <?php endforeach; ?>
  </ol>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch keine Schritte – in der Seitenleiste hinzufügen.</p><?php endif; ?>
</div>
