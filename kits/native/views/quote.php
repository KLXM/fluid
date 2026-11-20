<?php
/**
 * Zitat / Stimmen im Kit „NativeCSS“: „Groß“ als hervorgehobene Kundenstimme (.ncss-testimonial--featured), „Raster“ als
 * Raster gleich hoher Karten mit bündiger Personenzeile (.ncss-testimonial-grid) – figure/blockquote/figcaption, der
 * Name als Text (nicht <cite>). „Wischbares Band“ und „Laufband“ kommen aus dem Kit fluid.
 * @var \Klxm\Fluid\Block $b  @var array $d
 */
$v = $b->variant() ?: 'single';
if (!in_array($v, ['single', 'grid'], true)) {
    require \Klxm\Fluid\Kit::get('fluid')->view('quote');

    return;
}
\Klxm\Fluid\Kit::active()->markUsed('quote', '@testimonial');
$items = array_values(array_filter((array) ($d['items'] ?? []), fn ($i) => trim((string) ($i['text'] ?? '')) !== ''));
if ('single' === $v && count($items) > 1 && !is_editing()) {
    $items = [$items[0]];
}
$fig = static function (array $it, int $i, string $mod) use ($b): string {
    $pic = !empty($it['image']) ? img((string) $it['image'], '48px', ['ratio' => '1:1', 'alt' => '', 'class' => 'nv-quote__avatar']) : '';
    $by = trim(($it['name'] ?? '') . ($it['role'] ?? '')) !== ''
        ? '<figcaption class="ncss-testimonial-author">' . $pic . '<span class="ncss-testimonial-person">'
            . '<span class="ncss-testimonial-name"' . $b->edit("items.$i.name") . '>' . e($it['name'] ?? '') . '</span>'
            . (($it['role'] ?? '') !== '' ? '<span class="ncss-testimonial-role"' . $b->edit("items.$i.role") . '>' . e($it['role']) . '</span>' : '')
            . '</span></figcaption>'
        : '';

    return '<figure class="ncss-testimonial' . $mod . '"><blockquote class="ncss-testimonial-quote"><p' . $b->edit("items.$i.text") . '>'
        . nl2br(e($it['text']), false) . '</p></blockquote>' . $by . '</figure>';
};
?>
<div class="wrap nv-quotes">
  <?= fluid_head($b, 'single' === $v ? 'sec-head--center' : '') ?>
  <?php if ($items && 'single' === $v): ?>
  <?= $fig($items[0], 0, ' ncss-testimonial--featured') ?>
  <?php elseif ($items): ?>
  <ul class="ncss-testimonial-grid" role="list">
    <?php foreach ($items as $i => $it): ?><li data-reveal><?= $fig($it, $i, '') ?></li><?php endforeach; ?>
  </ul>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch kein Zitat – in der Seitenleiste hinzufügen.</p><?php endif; ?>
</div>
