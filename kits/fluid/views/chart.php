<?php
/** Diagramm: standard (unter dem Kopf) · split (Kopf und Einleitung links, Diagramm rechts). Ausgabe Klxm\Fluid\Chart\Chart. @var \Klxm\Fluid\Block $b  @var array $d */
use Klxm\Fluid\Chart\Chart as Charts;
use Klxm\Fluid\Kit\Fluid\Module\Chart;

$v = $b->variant() ?: 'standard';
$chart = Charts::render(Chart::spec($d), ['table' => Chart::tableOption($d)]);
if (is_editing() && '' === (string) ($d['source'] ?? '') && trim((string) ($d['labels'] ?? '')) === '') {
    $chart = '<p class="empty-hint">Noch keine Werte – Beschriftungen und Reihen in der Seitenleiste eintragen.</p>';
}
?>
<div class="wrap<?= 'split' === $v ? ' chart-split' : '' ?>">
  <?= fluid_head($b) ?>
  <?= $chart ?>
</div>
