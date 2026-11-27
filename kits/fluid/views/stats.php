<?php

use Redaxo\Core\Translation\I18n;

/** Key figures: row (large numbers, dividers) · cards · split (heading next to the numbers, sidebar pattern). @var \Klxm\Fluid\Block $b  @var array $d */
$v = $b->variant() ?: 'row';
$items = array_values((array) ($d['items'] ?? []));
?>
<div class="wrap<?= 'split' === $v ? ' stats-split' : '' ?>">
  <?= fluid_head($b) ?>
  <?php if ($items): ?>
  <dl class="grid stats stats--<?= e($v) ?>">
    <?php foreach ($items as $i => $it): ?>
    <div class="stat<?= 'cards' === $v ? ' card' : '' ?>" data-reveal>
      <dt class="stat__label"<?= $b->edit("items.$i.label") ?>><?= e($it['label']) ?></dt>
      <dd class="stat__value"<?= $b->edit("items.$i.value") ?>><?= e($it['value']) ?></dd>
      <?php if (($it['text'] ?? '') !== ''): ?><dd class="stat__text"<?= $b->edit("items.$i.text") ?>><?= e($it['text']) ?></dd><?php endif ?>
    </div>
    <?php endforeach ?>
  </dl>
  <?php elseif (is_editing()): ?><p class="empty-hint"><?= I18n::msg('fluid_kit_empty_stats') ?></p><?php endif ?>
</div>
