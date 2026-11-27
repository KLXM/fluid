<?php

use Klxm\Fluid\Block;

/**
 * Body text / article. standard: reading width · article: reading width + table of contents as a side column (sidebar
 * pattern: beside the text when there is room, otherwise above) · columns: CSS columns with minimum width (column-width).
 * @var Block $b  @var array $d
 */
$v = $b->variant() ?: 'standard';
$html = rich((string) $d['text']);
$toc = [];
if ('article' === $v) {
[$html, $toc] = fluid_toc($html, $b->domId());
}
$showToc = 'article' === $v && !empty($d['toc']) && count(array_filter($toc, static fn ($t) => 2 === $t['level'])) >= 2;
$meta = trim((string) ($d['meta'] ?? ''));
$measure = in_array($d['measure'] ?? 'normal', ['narrow', 'wide', 'full'], true) && 'columns' !== $v ? ' prose--m-' . $d['measure'] : '';
// Reveal on scroll (field reveal): whole block or paragraphs one by one (site.js sets data-reveal on the paragraphs) – not in the editor
$reveal = is_editing() ? 'off' : (string) ($d['reveal'] ?? 'off');
$prose = '<div class="prose' . (!empty($d['dropcap']) ? ' prose--dropcap' : '') . ('columns' === $v ? ' prose--columns' : '') . $measure . '"' . ('paragraphs' === $reveal ? ' data-reveal-children' : '') . $b->edit('text', 'rich') . '>' . $html . '</div>';
?>
<?php if ('article' === $v): ?>
<div class="wrap article"<?= 'block' === $reveal ? ' data-reveal' : '' ?>>
  <?= fluid_head($b, 'article__head') ?>
  <?php if ('' !== $meta || '' !== trim(strip_tags($html))): ?>
  <p class="article__meta"><?php if ('' !== $meta): ?><span<?= $b->edit('meta') ?>><?= e($meta) ?></span><span aria-hidden="true"> · </span><?php endif ?><?= e(lt('{n} Min. Lesezeit', ['n' => fluid_reading_time($html)])) ?></p>
  <?php endif ?>
  <div class="article__body<?= $showToc ? ' has-toc' : '' ?>">
    <?php if ($showToc): ?>
    <?php $spy = in_array($d['scrollspy'] ?? 'off', ['mark', 'progress'], true) ? (string) $d['scrollspy'] : '' ?>
    <nav class="toc<?= $spy ? ' toc--spy toc--spy-' . e($spy) : '' ?>" aria-labelledby="<?= e($b->domId()) ?>-toc"<?= $spy ? ' data-scrollspy' : '' ?>>
      <p class="toc__title" id="<?= e($b->domId()) ?>-toc"><?= e(lt('Inhalt')) ?></p>
      <ol class="toc__list" role="list">
        <?php foreach ($toc as $t): ?><li class="toc__item toc__item--<?= (int) $t['level'] ?>"><a href="#<?= e($t['id']) ?>"><?= e($t['label']) ?></a></li><?php endforeach ?>
      </ol>
    </nav>
    <?php endif ?>
    <?= $prose ?>
  </div>
</div>
<?php else: ?>
<div class="wrap<?= 'columns' === $v ? '' : ' wrap--text' ?>"<?= 'block' === $reveal ? ' data-reveal' : '' ?>>
  <?= fluid_head($b) ?>
  <?= $prose ?>
</div>
<?php endif;
