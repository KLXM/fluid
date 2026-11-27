<?php

use Klxm\Fluid\Block;
use Redaxo\Core\Translation\I18n;

/**
 * Questions & answers in the kit „NativeCSS“: native details/summary styled as .ncss-disclosure (without JavaScript;
 * name= closes the list's other answers). split: header beside the list · stacked: header above.
 * @var Block $b  @var array $d
 */
$v = 'stacked' === $b->variant() ? 'stacked' : 'split';
?>
<div class="wrap nv-faq nv-faq--<?= e($v) ?>">
  <?= fluid_head($b, 'stacked' === $v ? 'sec-head--center' : 'nv-faq__head') ?>
  <div class="ncss-stack nv-faq__list">
    <?php foreach ((array) $d['items'] as $i => $it): ?>
    <details class="ncss-disclosure nv-faq__item" name="<?= e($b->domId()) ?>-faq">
      <summary><span<?= $b->edit("items.$i.q") ?>><?= e($it['q']) ?></span></summary>
      <div class="prose nv-faq__a"<?= $b->edit("items.$i.a", 'rich') ?>><?= rich($it['a']) ?></div>
    </details>
    <?php endforeach ?>
    <?php if (!$d['items'] && is_editing()): ?><p class="empty-hint"><?= I18n::msg('fluid_kit_empty_faq') ?></p><?php endif ?>
  </div>
</div>
