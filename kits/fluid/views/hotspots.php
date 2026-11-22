<?php
/**
 * Interaktives Bild (Klxm\Fluid\Hotspots): Bild mit nummerierten Knöpfen und Hinweisfenstern (<button popovertarget>), Flächen als
 * SVG, darunter die Liste aller Markierungen (Telefon sichtbar, sonst für Screenreader). Lage ohne style-Attribute (CSP).
 * Beim Bearbeiten öffnen die Knöpfe nichts (das Skript lädt nicht) – Markierungen setzt der Editor in der Seitenleiste.
 * @var \Klxm\Fluid\Block $b  @var array $d
 */
$file = (string) ($d['image'] ?? '');
$html = \Klxm\Fluid\Hotspots::render($file, $d['hotspots'] ?? null, [
    'label' => str_replace('*', '', trim((string) ($d['title'] ?? ''))),
    'id' => $b->domId() . '-hs',
    'list' => (string) ($d['list'] ?? 'auto'),
    'sizes' => 'text' === ($d['width'] ?? '') ? '(min-width: 800px) 760px, 100vw' : '(min-width: 1280px) 1200px, 100vw',
]);
if ('' === $html && !is_editing()) {
    return;
}
?>
<div class="wrap">
  <?= fluid_head($b) ?>
  <?php if ('' !== $html): ?>
  <div class="cms-hs-wrap<?= 'text' === ($d['width'] ?? '') ? ' cms-hs-wrap--text' : '' ?>"><?= $html ?></div>
  <?php else: ?>
  <p class="empty-hint">Noch kein Bild – in der Seitenleiste ein Bild wählen und Markierungen setzen.</p>
  <?php endif; ?>
</div>
