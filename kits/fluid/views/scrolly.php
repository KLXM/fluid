<?php
/**
 * Scrollytelling: Bildspalte bleibt stehen (position: sticky), Textabschnitte ziehen vorbei; js/scrolly.js blendet das
 * passende Bild ein (IntersectionObserver). Container-Query: Unter ~46 rem Blockbreite steht jedes Bild direkt über
 * seinem Text (die stehende Spalte entfällt). Ohne JavaScript bleibt das erste Bild stehen – alle Bilder stehen zusätzlich
 * am Text. Bei „Bewegung reduzieren“ wechseln die Bilder ohne Überblendung.
 *  - Bild je Abschnitt optional: Ohne eigenes Bild bleibt das vorige stehen (data-img am Abschnitt = Index des Abschnitts,
 *    dessen Bild gilt; leer = noch kein Bild). Auf schmalen Flächen steht ein Bild nur bei dem Abschnitt, der es bringt.
 *  - Bildformat „auto“: jedes Bild in seinem eigenen Seitenverhältnis (kein Zuschnitt, Hochformat bleibt ganz).
 *  - Text: Rich-Text (früher Textfeld) – alter Klartext erscheint unverändert wie bisher. Zwischenüberschriften im Text liegen
 *    immer unter der Ebene des Abschnittstitels (unter h3 → h4).
 * @var \Klxm\Fluid\Block $b  @var array $d
 */
$ratio = (string) ($d['ratio'] ?? '') ?: '4:5';
$auto = 'auto' === $ratio;
$items = array_values(array_filter((array) ($d['items'] ?? []), fn($i) => trim((string) ($i['title'] ?? '')) !== '' || is_editing()));
$tag = fluid_htag($d);
$stage = $show = [];
$cur = null;
$long = false;
foreach ($items as $i => $it) {
    $pic = !empty($it['image']) ? img((string) $it['image'], '(min-width: 1080px) 560px, 50vw', ($auto ? [] : ['ratio' => $ratio]) + ['alt' => '']) : '';
    if ('' !== $pic) {
        $stage[$i] = $pic;
        $cur = $i;
    }
    $show[$i] = $cur;
    if (!fluid_is_plain_text((string) ($it['text'] ?? ''))) {
        $long = true;
    }
}
if ($long) {
    \Klxm\Fluid\Kit::active()->markUsed('@rich');   // css/prose.css für die Texte
}
?>
<div class="wrap">
  <?= fluid_head($b) ?>
  <?php if ($items): ?>
  <div class="scrolly scrolly--<?= $b->variant() === 'right' ? 'right' : 'left' ?><?= $long ? ' scrolly--long' : '' ?>" data-scrolly>
    <div class="scrolly__stage" aria-hidden="true">
      <div class="scrolly__frame <?= $auto ? 'scrolly__frame--auto' : 'r-' . e(str_replace(':', '-', $ratio)) ?>">
        <?php foreach ($stage as $i => $pic): ?>
        <div class="scrolly__img<?= $i === 0 ? ' is-active' : '' ?>" data-step-img="<?= $i ?>"><?= $pic ?></div>
        <?php endforeach; ?>
      </div>
    </div>
    <ol class="scrolly__steps" role="list">
      <?php foreach ($items as $i => $it): $text = (string) ($it['text'] ?? ''); ?>
      <li class="scrolly__step<?= $i === 0 ? ' is-active' : '' ?>" data-step="<?= $i ?>" data-img="<?= $show[$i] ?? '' ?>">
        <?php if (isset($stage[$i])): ?><?= $auto ? '<div class="scrolly__inline scrolly__inline--auto">' . img((string) $it['image'], '100vw') . '</div>' : fluid_image((string) $it['image'], '100vw', $ratio, 'scrolly__inline') ?><?php endif; ?>
        <span class="scrolly__num" aria-hidden="true"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
        <?php if (trim((string) ($it['eyebrow'] ?? '')) !== ''): ?><p class="eyebrow"<?= $b->edit("items.$i.eyebrow") ?>><?= fluid_title((string) $it['eyebrow']) ?></p><?php endif; ?>
        <<?= $tag ?> class="scrolly__title"<?= $b->edit("items.$i.title") ?>><?= fluid_title((string) ($it['title'] ?? '')) ?></<?= $tag ?>>
        <?php if (trim($text) === ''): ?>
        <?php elseif (fluid_is_plain_text($text)): ?><p class="scrolly__text"<?= $b->edit("items.$i.text") ?>><?= nl2br(fluid_title($text), false) ?></p>
        <?php else: ?><div class="scrolly__text scrolly__rich prose"<?= $b->edit("items.$i.text", 'rich') ?>><?= fluid_rich_below(rich($text), (int) $tag[1]) ?></div>
        <?php endif; ?>
      </li>
      <?php endforeach; ?>
    </ol>
  </div>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch keine Abschnitte – in der Seitenleiste hinzufügen.</p><?php endif; ?>
</div>
