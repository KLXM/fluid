<?php
/**
 * Text + Bild im Kit „NativeCSS“ (.ncss-split: nebeneinander, stapelt sich per Container Query – Bild links bzw. rechts
 * allein über die Reihenfolge im Markup). auto wechselt die Seite in einer Folge von „Text + Bild“-Blöcken wie bei fluid.
 * „split“ (randlos 50/50) und „overlap“ (Karte über großem Bild) kommen aus dem Kit fluid.
 * @var \Klxm\Fluid\Block $b  @var array $d
 */
$v = $b->variant() ?: 'auto';
// „Morphing beim Scrollen“ (nur dieses Kit, kit.php → blocks): beim Bearbeiten als „Bild rechts“ – die hohe Scroll-Strecke
// stört dort nur; für Besucher .ncss-morph von NativeCSS (bei „Bewegung reduzieren“ statisch nebeneinander)
if ('morph' === $v && !is_editing()) {
    \Klxm\Fluid\Kit::active()->markUsed('media_text', '@morph');
    $mpic = img(!empty($d['image']) ? (string) $d['image'] : null, '(min-width: 1080px) 1200px, 100vw', ['ratio' => (string) ($d['ratio'] ?? '') ?: '4:3']);
    if ('' !== $mpic) {
        echo '<div class="wrap nv-morph"><div class="ncss-morph ncss-morph--card"><div class="ncss-morph-stage">'
            . '<div class="ncss-morph-media">' . $mpic . '</div>'
            . '<div class="ncss-morph-content">' . fluid_head($b, 'nv-mt__head')
            . ('' !== trim(strip_tags((string) $d['text'])) ? '<div class="prose">' . rich((string) $d['text']) . '</div>' : '')
            . fluid_checks(fluid_lines($d['list'] ?? '')) . fluid_buttons($b) . '</div>'
            . '</div></div></div>';

        return;
    }
}
if ('morph' === $v) {
    $v = 'right';
}
if (!in_array($v, ['auto', 'right', 'left'], true)) {
    require \Klxm\Fluid\Kit::get('fluid')->view('media_text');

    return;
}
$ratio = (string) ($d['ratio'] ?? '') ?: '4:3';
$side = $v;
if ('auto' === $v) {
    $n = 0;
    for ($p = $b->prev; $p && 'media_text' === $p->type && ($p->data['variant'] ?? 'auto') === $v; $p = $p->prev) {
        ++$n;
    }
    $side = $n % 2 ? 'left' : 'right';
}
$list = fluid_lines($d['list'] ?? '');
$body = fluid_head($b, 'nv-mt__head')
    . ('' !== trim(strip_tags((string) $d['text'])) || is_editing() ? '<div class="prose"' . $b->edit('text', 'rich') . '>' . rich((string) $d['text']) . '</div>' : '')
    . fluid_checks($list)
    . fluid_buttons($b);
$pic = fluid_image($d['image'] ?? null, '(min-width: 1080px) 600px, 100vw', $ratio, 'ncss-radius-lg');
$media = '' !== $pic ? '<div class="ncss-split-media">' . $pic . '</div>' : '';
$split = in_array($d['split'] ?? 'even', ['text', 'media', 'mediaxl'], true) ? ' nv-mt--s-' . $d['split'] : '';
// Bild ausrichten: mitlaufen (Standard für neue Blöcke) · oben · mitte (bisher; bestehende Blöcke über DefaultChanges)
$valign = in_array($d['valign'] ?? '', ['follow', 'top', 'center'], true) ? $d['valign'] : 'follow';
?>
<div class="wrap">
  <div class="ncss-split-container">
    <div class="ncss-split nv-mt nv-mt--<?= e($side) ?> nv-mt--va-<?= e($valign) ?><?= e($split) ?>">
      <?= 'left' === $side ? $media : '' ?>
      <div class="ncss-split-content"><?= $body ?></div>
      <?= 'right' === $side ? $media : '' ?>
    </div>
  </div>
</div>
