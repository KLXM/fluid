<?php
/** Chart: standard (below the header) · split (header and intro left, chart right). Output Klxm\Fluid\Chart\Chart. @var \Klxm\Fluid\Block $b  @var array $d */
use Klxm\Fluid\Chart\Chart as Charts;
use Klxm\Fluid\Kit\Fluid\Module\Chart;
use Redaxo\Core\Translation\I18n;

$v = $b->variant() ?: 'standard';
$chart = Charts::render(Chart::spec($d), ['table' => Chart::tableOption($d)]);
if (is_editing() && '' === (string) ($d['source'] ?? '') && '' === trim((string) ($d['labels'] ?? ''))) {
    $chart = '<p class="empty-hint">' . I18n::msg('fluid_kit_empty_chart') . '</p>';
}
?>
<div class="wrap<?= 'split' === $v ? ' chart-split' : '' ?>">
  <?= fluid_head($b) ?>
  <?= $chart ?>
</div>
