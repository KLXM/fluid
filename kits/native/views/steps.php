<?php
/**
 * Ablauf / Zeitleiste im Kit „NativeCSS“: „Zeitleiste“ mit .ncss-timeline von NativeCSS (Datum, Markierung als Punkt oder
 * Symbol des Schritts, ab ~40rem Containerbreite abwechselnd links/rechts). „Nummerierte Schritte“ und „Prozess mit
 * Pfeilen“ kommen aus dem Kit fluid – der Stepper von NativeCSS zeigt einen Fortschritt (erledigt/aktuell/offen), das
 * passt nicht zu einer Anleitung. Die Optionen „Kreise“ und „Animation“ gelten hier nur für die Varianten von fluid.
 * @var \Klxm\Fluid\Block $b  @var array $d
 */
if ('timeline' !== $b->variant()) {
    require \Klxm\Fluid\Kit::get('fluid')->view('steps');

    return;
}
\Klxm\Fluid\Kit::active()->markUsed('steps', '@timeline');
$items = array_values(array_filter((array) ($d['items'] ?? []), fn ($i) => trim((string) ($i['title'] ?? '')) !== ''));
$tag = fluid_htag($d);
?>
<div class="wrap nv-timeline">
  <?= fluid_head($b) ?>
  <?php if ($items): ?>
  <ol class="ncss-timeline ncss-timeline--alternate" role="list">
    <?php foreach ($items as $i => $it): ?>
    <li class="ncss-timeline-item" data-reveal>
      <span class="ncss-timeline-marker" aria-hidden="true"><?= !empty($it['icon']) ? icon((string) $it['icon']) : '' ?></span>
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
