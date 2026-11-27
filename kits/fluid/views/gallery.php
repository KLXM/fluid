<?php

use Klxm\Meta\Alt;
use Redaxo\Core\MediaPool\Media;

/**
 * Image gallery: grid (same ratio) · masonry (columns, original ratio) · justified (flush rows). Lightbox via
 * js/media.js – without JavaScript the images link to the large version. @var \Klxm\Fluid\Block $b  @var array $d.
 */
$layout = in_array($b->variant(), ['grid', 'masonry', 'justified'], true) ? $b->variant() : 'grid';
$cols = in_array((string) ($d['columns'] ?? ''), ['2', '3', '4', '5'], true) ? (string) $d['columns'] : '3';
$ratio = (string) ($d['ratio'] ?? '4:3');
$lightbox = !empty($d['lightbox']) && !is_editing();
$items = [];
foreach (array_values((array) ($d['images'] ?? [])) as $i => $it) {
    $file = (string) ($it['image'] ?? '');
    if ('' !== $file && str_starts_with((string) Media::get($file)?->type, 'image/')) {
        $items[] = ['file' => $file, 'caption' => trim((string) ($it['caption'] ?? '')), 'i' => $i];
    }
}
$sizes = [
    '2' => '(min-width: 1280px) 600px, (min-width: 640px) 50vw, 100vw',
    '3' => '(min-width: 1280px) 400px, (min-width: 640px) 50vw, 100vw',
    '4' => '(min-width: 1280px) 300px, (min-width: 860px) 25vw, 50vw',
    '5' => '(min-width: 1280px) 240px, (min-width: 860px) 20vw, 50vw',
][$cols];
if ('justified' === $layout) {
    $sizes = '(min-width: 860px) 40vw, 100vw';
}
$r = 'r-' . str_replace(':', '-', $ratio);
// exactly one image: editors choose the size – like the gallery (one column), medium or large in the original ratio
// (default „groß“ for new blocks; existing ones keep „wie die Galerie“ via DefaultChanges)
$singleSize = in_array($d['single_size'] ?? '', ['grid', 'medium', 'large'], true) ? $d['single_size'] : 'grid';
$single = 1 === count($items) && 'grid' !== $singleSize;
?>
<div class="wrap">
  <?= fluid_head($b) ?>
  <?php if ($single):
      $it = $items[0];
      $pic = img($it['file'], 'medium' === $singleSize ? '(min-width: 760px) 704px, 100vw' : '(min-width: 1280px) 1280px, 100vw');
      $cap = !empty($d['captions']) ? $it['caption'] : '';
  ?>
  <figure class="cms-gallery cms-gallery--single cms-gallery--single-<?= e($singleSize) ?> cms-gallery__fig <?= e(fluid_aspect_class($it['file'])) ?>"<?= $lightbox ? ' data-cms-lightbox' : '' ?>>
    <?php if ($lightbox): ?>
    <a class="cms-gallery__link"<?= fluid_lightbox_link($it['file'], $it['caption']) ?>><?= $pic ?><span class="sr-only"><?= e(lt('Bild {n} vergrößern', ['n' => 1])) ?></span></a>
    <?php else: ?>
    <div class="cms-gallery__link"><?= $pic ?></div>
    <?php endif ?>
    <?php if ('' !== $cap || (is_editing() && !empty($d['captions']))): ?><figcaption class="cms-gallery__cap"<?= $b->edit('images.' . $it['i'] . '.caption') ?>><?= e($cap) ?></figcaption><?php endif ?>
  </figure>
  <?= $lightbox ? fluid_lightbox() : '' ?>
  <?php elseif ($items): ?>
  <ul class="cms-gallery cms-gallery--<?= e($layout) ?> cols-<?= e($cols) ?>" role="list"<?= $lightbox ? ' data-cms-lightbox' : '' ?>>
    <?php foreach ($items as $n => $it):
        $pic = img($it['file'], $sizes, 'grid' === $layout ? ['ratio' => $ratio] : []);
        $cap = !empty($d['captions']) ? $it['caption'] : '';
        $alt = Alt::attribute($it['file']);
        $cls = 'cms-gallery__link' . ('grid' === $layout ? ' ' . $r : '');
    ?>
    <li class="cms-gallery__item<?= 'justified' === $layout ? ' ' . fluid_aspect_class($it['file']) : '' ?>">
      <figure class="cms-gallery__fig">
        <?php if ($lightbox): ?>
        <a class="<?= e($cls) ?>"<?= fluid_lightbox_link($it['file'], $it['caption']) ?>><?= $pic ?><span class="sr-only"><?= e(lt('Bild {n} vergrößern', ['n' => $n + 1])) ?></span></a>
        <?php else: ?>
        <div class="<?= e($cls) ?>"><?= $pic ?></div>
        <?php endif ?>
        <?php if ('' !== $cap || (is_editing() && !empty($d['captions']))): ?><figcaption class="cms-gallery__cap"<?= $b->edit('images.' . $it['i'] . '.caption') ?>><?= e($cap) ?></figcaption><?php endif ?>
      </figure>
    </li>
    <?php endforeach ?>
  </ul>
  <?= $lightbox ? fluid_lightbox() : '' ?>
  <?php elseif (is_editing()): ?>
  <p class="empty-hint">Noch keine Bilder – in der Seitenleiste unter „Bilder“ hinzufügen.</p>
  <?php endif ?>
</div>
