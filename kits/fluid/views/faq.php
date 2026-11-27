<?php

use Redaxo\Core\Translation\I18n;

/** Questions & answers (details/summary – without JavaScript). split: header beside the list (sidebar pattern) · stacked. @var \Klxm\Fluid\Block $b  @var array $d */
$v = 'stacked' === $b->variant() ? 'stacked' : 'split';
?>
<div class="wrap faq faq--<?= e($v) ?>">
  <?= fluid_head($b, 'stacked' === $v ? 'sec-head--center' : 'faq__head') ?>
  <div class="faq__list">
    <?php foreach ((array) $d['items'] as $i => $it): ?>
    <details class="faq__item" name="<?= e($b->domId()) ?>-faq">
      <summary class="faq__q"><span<?= $b->edit("items.$i.q") ?>><?= e($it['q']) ?></span><span class="faq__icon" aria-hidden="true"></span></summary>
      <div class="faq__a prose"<?= $b->edit("items.$i.a", 'rich') ?>><?= rich($it['a']) ?></div>
    </details>
    <?php endforeach ?>
    <?php if (!$d['items'] && is_editing()): ?><p class="empty-hint"><?= I18n::msg('fluid_kit_empty_faq') ?></p><?php endif ?>
  </div>
</div>
