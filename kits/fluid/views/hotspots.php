<?php

use Klxm\Fluid\Block;
use Klxm\Fluid\Hotspots;
use Redaxo\Core\Translation\I18n;

/**
 * Interactive image (Klxm\Fluid\Hotspots): image with numbered buttons and popups (<button popovertarget>), areas as
 * SVG, below the list of all markers (visible on phones, otherwise for screen readers). Position without style attributes (CSP).
 * While editing the buttons open nothing (the script doesn't load) – markers are set by the editor in the sidebar.
 * @var Block $b  @var array $d
 */
$file = (string) ($d['image'] ?? '');
$html = Hotspots::render($file, $d['hotspots'] ?? null, [
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
  <p class="empty-hint"><?= I18n::msg('fluid_kit_empty_hotspots') ?></p>
  <?php endif ?>
</div>
