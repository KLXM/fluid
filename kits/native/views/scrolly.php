<?php

use Klxm\Fluid\Block;
use Klxm\Fluid\Kit;

/**
 * Scrollytelling im Kit „NativeCSS“ mit <ncss-scrolly>: Die Abschnitte ziehen als Karten vorbei, das Bild daneben bleibt
 * stehen und wechselt zum aktiven Abschnitt; schmal steht das Bild oben und die Abschnitte laufen darunter durch. Ohne
 * JavaScript stehen Abschnitte und Bilder untereinander. Abschnitte ohne eigenes Bild behalten das vorige.
 * Beim Bearbeiten die Ausgabe von fluid (Bild direkt am Text). Text: Rich-Text (früher Textfeld) – alter Klartext wie bisher;
 * Bildformat „auto“: jedes Bild ohne Zuschnitt.
 * @var Block $b  @var array $d
 */
if (is_editing()) {
    require Kit::get('fluid')->view('scrolly');

    return;
}
$ratio = (string) ($d['ratio'] ?? '') ?: '4:5';
$items = array_values(array_filter((array) ($d['items'] ?? []), static fn ($i) => '' !== trim((string) ($i['title'] ?? ''))));
$tag = fluid_htag($d);
$img = '';
$fmt = 'auto' === $ratio ? [] : ['ratio' => $ratio];
?>
<div class="wrap nv-scrolly<?= $fmt ? '' : ' nv-scrolly--auto' ?>">
  <?= fluid_head($b) ?>
  <?php if ($items): ?>
  <ncss-scrolly<?= 'right' === $b->variant() ? '' : ' media-start' ?>>
    <ol class="ncss-scrolly-steps" role="list">
      <?php foreach ($items as $i => $it): ?>
      <li class="ncss-scrolly-step"><div>
        <?php if ('' !== trim((string) ($it['eyebrow'] ?? ''))): ?><p class="eyebrow"><?= fluid_title((string) $it['eyebrow']) ?></p><?php endif ?>
        <<?= $tag ?> class="nv-scrolly__title"><?= fluid_title((string) $it['title']) ?></<?= $tag ?>>
        <?php $text = (string) ($it['text'] ?? '');
        if ('' === trim($text)): ?>
        <?php elseif (fluid_is_plain_text($text)): ?><p><?= nl2br(fluid_title($text), false) ?></p>
        <?php else: Kit::active()->markUsed('@rich') ?><div class="prose"><?= fluid_rich_below(rich($text), (int) $tag[1]) ?></div><?php endif ?>
      </div></li>
      <?php endforeach ?>
    </ol>
    <div class="ncss-scrolly-media" aria-hidden="true">
      <?php foreach ($items as $i => $it):
          $img = !empty($it['image']) ? (string) $it['image'] : $img ?>
      <figure class="ncss-scrolly-figure"><?= '' !== $img ? img($img, '(min-width: 1080px) 560px, 90vw', $fmt + ['alt' => '']) : '' ?></figure>
      <?php endforeach ?>
    </div>
  </ncss-scrolly>
  <?php endif ?>
</div>
