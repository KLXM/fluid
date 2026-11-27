<?php

use Klxm\Fluid\Block;
use Klxm\Fluid\Kit;
use Redaxo\Core\Translation\I18n;

/**
 * Packages / comparison in the kit „NativeCSS“:
 *   cards  NativeCSS pricing table (.ncss-pricing): cards of equal height, rows aligned via subgrid, „Hervorheben“ with
 *          the badge as real text; features with check marks – a line starting with „–“ counts as not included (cross +
 *          text for screen readers). No monthly/yearly toggle: the block only knows one price per package.
 *   list   price list with leader dots (.ncss-leaders)
 *   table  fluid's comparison table (with scroll shadow)
 * @var Block $b  @var array $d
 */
$v = $b->variant() ?: 'cards';
if ('table' === $v) {
    require Kit::get('fluid')->view('pricing');

    return;
}
$tag = fluid_htag($d);
if ('list' === $v):
    Kit::active()->markUsed('pricing', '@leaders');
    $entries = array_values(array_filter((array) ($d['entries'] ?? []), static fn ($i) => '' !== trim((string) ($i['name'] ?? ''))));
?>
<div class="wrap nv-pricelist">
  <?= fluid_head($b, 'sec-head--center') ?>
  <?php if ($entries): ?>
  <dl class="ncss-leaders">
    <?php foreach ($entries as $i => $it): ?>
    <div class="ncss-leaders-item">
      <dt<?= $b->edit("entries.$i.name") ?>><?= fluid_title((string) $it['name']) ?></dt>
      <dd<?= $b->edit("entries.$i.price") ?>><?= e((string) ($it['price'] ?? '')) ?></dd>
      <?php if ('' !== trim((string) ($it['text'] ?? ''))): ?><dd class="ncss-leaders-desc"<?= $b->edit("entries.$i.text") ?>><?= e($it['text']) ?></dd><?php endif ?>
    </div>
    <?php endforeach ?>
  </dl>
  <?php elseif (is_editing()): ?><p class="empty-hint"><?= I18n::msg('fluid_kit_empty_pricing_list') ?></p><?php endif ?>
  <?php if ('' !== trim((string) ($d['note'] ?? ''))): ?><p class="plans__note"<?= $b->edit('note') ?>><?= fluid_title((string) $d['note']) ?></p><?php endif ?>
</div>
<?php return; endif;

Kit::active()->markUsed('pricing', '@plans');
$items = array_values(array_filter((array) ($d['items'] ?? []), static fn ($i) => '' !== trim((string) ($i['name'] ?? ''))));
?>
<div class="wrap nv-plans">
  <?= fluid_head($b, 'sec-head--center') ?>
  <?php if ($items): ?>
  <div class="ncss-pricing">
    <ul class="ncss-pricing-plans" role="list">
      <?php foreach ($items as $i => $it):
          $hl = !empty($it['highlight']);
          $badge = trim((string) ($it['badge'] ?? ''));
          $label = trim((string) ($it['button_label'] ?? ''));
          $link = trim((string) ($it['button_link'] ?? ''));
      ?>
      <li class="ncss-pricing-plan<?= $hl ? ' ncss-pricing-plan--featured' : '' ?>">
        <div class="ncss-pricing-header">
          <?php if ('' !== $badge): ?><p class="ncss-pricing-badge"<?= $b->edit("items.$i.badge") ?>><?= fluid_title($badge) ?></p><?php endif ?>
          <<?= $tag ?> class="ncss-pricing-name"<?= $b->edit("items.$i.name") ?>><?= fluid_title((string) $it['name']) ?></<?= $tag ?>>
          <?php if ('' !== trim((string) ($it['text'] ?? ''))): ?><p class="ncss-pricing-desc"<?= $b->edit("items.$i.text") ?>><?= nl2br(fluid_title((string) $it['text']), false) ?></p><?php endif ?>
        </div>
        <p class="ncss-pricing-price"><?php if ('' !== trim((string) ($it['price'] ?? ''))): ?><span class="ncss-pricing-value"<?= $b->edit("items.$i.price") ?>><?= e($it['price']) ?></span>
          <?php if ('' !== trim((string) ($it['period'] ?? ''))): ?><span class="ncss-pricing-period"<?= $b->edit("items.$i.period") ?>><?= e($it['period']) ?></span><?php endif; endif ?></p>
        <ul class="ncss-pricing-features" role="list">
          <?php foreach (fluid_lines($it['features'] ?? '') as $line): $out = (bool) preg_match('~^[–—-]\s*~u', $line);
          $line = (string) preg_replace('~^[–—-]\s*~u', '', $line) ?>
          <li<?= $out ? ' class="ncss-pricing-excluded"' : '' ?>><span class="ncss-visually-hidden"><?= e($out ? lt('Nicht enthalten:') : lt('Enthalten:')) ?></span> <?= fluid_title($line) ?></li>
          <?php endforeach ?>
        </ul>
        <div class="ncss-pricing-action"><?php if ('' !== $label && '' !== $link): ?><a class="ncss-btn <?= $hl ? 'ncss-btn--primary' : 'ncss-btn--secondary' ?>" <?= fluid_link_attrs($link) ?>><span<?= $b->edit("items.$i.button_label") ?>><?= e($label) ?></span><span class="ncss-visually-hidden">: <?= e(strip_emphasis((string) $it['name'])) ?></span></a><?php endif ?></div>
      </li>
      <?php endforeach ?>
    </ul>
  </div>
  <?php elseif (is_editing()): ?><p class="empty-hint"><?= I18n::msg('fluid_kit_empty_pricing') ?></p><?php endif ?>
  <?php if ('' !== trim((string) ($d['note'] ?? ''))): ?><p class="plans__note"<?= $b->edit('note') ?>><?= fluid_title((string) $d['note']) ?></p><?php endif ?>
</div>
