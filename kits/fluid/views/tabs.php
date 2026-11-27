<?php

use Klxm\Fluid\Block;
use Redaxo\Core\Translation\I18n;

/**
 * Tabs. Without JavaScript: all contents stacked with subheadings. js/tabs.js turns them into a
 * tab bar (role=tablist, arrow keys, Home/End). „Reiter links“: sidebar pattern – the bar is on the left as long as
 * ≥ 60 % of space remains next to it, otherwise on top (horizontally scrollable).
 * @var Block $b  @var array $d
 */
$items = array_values(array_filter((array) $d['items'], static fn ($i) => '' !== trim((string) ($i['label'] ?? ''))));
$id = $b->domId();
?>
<div class="wrap">
  <?= fluid_head($b) ?>
  <?php if ($items): ?>
  <div class="tabs tabs--<?= 'side' === $b->variant() ? 'side' : 'top' ?>" data-tabs>
    <div class="tabs__list" role="tablist"<?= '' !== trim((string) $d['title']) ? ' aria-labelledby="' . e($b->titleId()) . '"' : '' ?> hidden>
      <?php foreach ($items as $i => $it): ?><button type="button" class="tabs__tab" role="tab" id="<?= e("$id-tab-$i") ?>" aria-controls="<?= e("$id-panel-$i") ?>" aria-selected="<?= 0 === $i ? 'true' : 'false' ?>"<?= $i ? ' tabindex="-1"' : '' ?>><?= fluid_title((string) $it['label']) ?></button><?php endforeach ?>
    </div>
    <div class="tabs__panels">
      <?php foreach ($items as $i => $it):
          $pic = !empty($it['image']) ? fluid_image((string) $it['image'], '(min-width: 1080px) 480px, 100vw', '4:3', 'tabs__media') : '' ?>
      <section class="tabs__panel<?= '' !== $pic ? ' tabs__panel--media' : '' ?>" id="<?= e("$id-panel-$i") ?>" aria-labelledby="<?= e("$id-tab-$i") ?>">
        <div class="tabs__text">
          <h3 class="tabs__title h3"<?= $b->edit("items.$i.label") ?>><?= fluid_title((string) $it['label']) ?></h3>
          <div class="prose"<?= $b->edit("items.$i.text", 'rich') ?>><?= rich((string) ($it['text'] ?? '')) ?></div>
        </div>
        <?= $pic ?>
      </section>
      <?php endforeach ?>
    </div>
  </div>
  <?php elseif (is_editing()): ?><p class="empty-hint"><?= I18n::msg('fluid_kit_empty_tabs') ?></p><?php endif ?>
</div>
