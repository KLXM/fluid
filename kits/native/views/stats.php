<?php

use Klxm\Fluid\Block;
use Klxm\Fluid\InlineStyle;
use Klxm\Fluid\Kit;

/**
 * Key figures in the kit „NativeCSS“ – as in fluid (row · cards · split), plus whole numbers count up when scrolled into view
 * (.ncss-count-up from NativeCSS). Without own setting: whatever looks like a number counts ("25+", "98 %",
 * "12.500", "€ 40"), not years and values like "24/7" or "3,5". The real number stays as text (screen readers,
 * without JavaScript, „Bewegung reduzieren“); off in the design („Dezente Animationen“ off) and while editing.
 * @var Block $b  @var array $d
 */
$v = $b->variant() ?: 'row';
$items = array_values((array) ($d['items'] ?? []));
$count = !is_editing() && false !== (bool) (Kit::active()->design()->get('motion') ?? true);
$counting = false;
$value = static function (string $raw) use ($count, &$counting): string {
    $raw = trim($raw);
    // prefix (not a digit), number with thousands dot/space, suffix without further digits
    if (!$count || !preg_match('~^(\D{0,3}?)(\d{1,3}(?:[.\x{202F}\x{00A0} ]\d{3})+|\d{1,7})(\D{0,6})$~u', $raw, $m) || preg_match('~^[,./:]~', $m[3]) || preg_match('~[;"\\\\{}<>]~', $m[1] . $m[3])) {
        return e($raw);
    }
    $n = (int) preg_replace('~\D~', '', $m[2]);
    if ('' === $m[1] && '' === trim($m[3]) && $n >= 1800 && $n <= 2100 || $n < 2) {
        return e($raw);   // year ("seit 1998") or 0/1: don't count
    }
    $counting = true;
    $css = '--ncss-count-to:' . $n . ('' !== $m[1] ? ';--ncss-count-prefix:"' . addcslashes($m[1], '"\\') . '"' : '') . ('' !== $m[3] ? ';--ncss-count-suffix:"' . addcslashes($m[3], '"\\') . '"' : '');

    return '<span class="ncss-count-up ' . e(InlineStyle::class($css)) . '" data-ncss-count-up>' . e($raw) . '</span>';
};
?>
<div class="wrap<?= 'split' === $v ? ' stats-split' : '' ?>">
  <?= fluid_head($b) ?>
  <?php if ($items): ?>
  <dl class="grid stats stats--<?= e($v) ?>">
    <?php foreach ($items as $i => $it): ?>
    <div class="stat<?= 'cards' === $v ? ' card' : '' ?>" data-reveal>
      <dt class="stat__label"<?= $b->edit("items.$i.label") ?>><?= e($it['label']) ?></dt>
      <dd class="stat__value"<?= $b->edit("items.$i.value") ?>><?= $value((string) $it['value']) ?></dd>
      <?php if (($it['text'] ?? '') !== ''): ?><dd class="stat__text"<?= $b->edit("items.$i.text") ?>><?= e($it['text']) ?></dd><?php endif ?>
    </div>
    <?php endforeach ?>
  </dl>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch keine Kennzahlen – in der Seitenleiste hinzufügen.</p><?php endif ?>
</div>
<?php if ($counting) {
    Kit::active()->markUsed('stats', '@count');
}
