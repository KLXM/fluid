<?php
/**
 * Kennzahlen im Kit „NativeCSS“ – wie bei fluid (row · cards · split), dazu zählen ganze Zahlen beim Hereinscrollen hoch
 * (.ncss-count-up von NativeCSS). Ohne eigene Einstellung: Es zählt, was nach einer Zahl aussieht („25+“, „98 %“,
 * „12.500“, „€ 40“), nicht Jahreszahlen und Werte wie „24/7“ oder „3,5“. Die echte Zahl bleibt als Text stehen (Screenreader,
 * ohne JavaScript, „Bewegung reduzieren“); aus im Design („Dezente Animationen“ aus) und beim Bearbeiten.
 * @var \Klxm\Fluid\Block $b  @var array $d
 */
$v = $b->variant() ?: 'row';
$items = array_values((array) ($d['items'] ?? []));
$count = !is_editing() && false !== (bool) (\Klxm\Fluid\Kit::active()->design()->get('motion') ?? true);
$counting = false;
$value = static function (string $raw) use ($count, &$counting): string {
    $raw = trim($raw);
    // Präfix (nicht Ziffer), Zahl mit Tausenderpunkt/-leerzeichen, Suffix ohne weitere Ziffern
    if (!$count || !preg_match('~^(\D{0,3}?)(\d{1,3}(?:[.\x{202F}\x{00A0} ]\d{3})+|\d{1,7})(\D{0,6})$~u', $raw, $m) || preg_match('~^[,./:]~', $m[3]) || preg_match('~[;"\\\\{}<>]~', $m[1] . $m[3])) {
        return e($raw);
    }
    $n = (int) preg_replace('~\D~', '', $m[2]);
    if ('' === $m[1] && '' === trim($m[3]) && $n >= 1800 && $n <= 2100 || $n < 2) {
        return e($raw);   // Jahreszahl („seit 1998“) oder 0/1: nicht zählen
    }
    $counting = true;
    $css = '--ncss-count-to:' . $n . ('' !== $m[1] ? ';--ncss-count-prefix:"' . addcslashes($m[1], '"\\') . '"' : '') . ('' !== $m[3] ? ';--ncss-count-suffix:"' . addcslashes($m[3], '"\\') . '"' : '');

    return '<span class="ncss-count-up ' . e(\Klxm\Fluid\InlineStyle::class($css)) . '" data-ncss-count-up>' . e($raw) . '</span>';
};
?>
<div class="wrap<?= $v === 'split' ? ' stats-split' : '' ?>">
  <?= fluid_head($b) ?>
  <?php if ($items): ?>
  <dl class="grid stats stats--<?= e($v) ?>">
    <?php foreach ($items as $i => $it): ?>
    <div class="stat<?= $v === 'cards' ? ' card' : '' ?>" data-reveal>
      <dt class="stat__label"<?= $b->edit("items.$i.label") ?>><?= e($it['label']) ?></dt>
      <dd class="stat__value"<?= $b->edit("items.$i.value") ?>><?= $value((string) $it['value']) ?></dd>
      <?php if (($it['text'] ?? '') !== ''): ?><dd class="stat__text"<?= $b->edit("items.$i.text") ?>><?= e($it['text']) ?></dd><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </dl>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch keine Kennzahlen – in der Seitenleiste hinzufügen.</p><?php endif; ?>
</div>
<?php if ($counting) {
    \Klxm\Fluid\Kit::active()->markUsed('stats', '@count');
}
