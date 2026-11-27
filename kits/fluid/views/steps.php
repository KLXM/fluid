<?php

use Klxm\Fluid\Block;
use Redaxo\Core\Translation\I18n;

/**
 * Steps: numbers (grid, auto-fit) · timeline (vertical line; date on the left once the container is wide enough) ·
 * process (steps with arrows – side by side if there is room, otherwise stacked with a downward arrow).
 * Fields marker (circles), colors, motion (animation) – classes steps--m-*, steps--c-*, steps--a-* (assets/css/b-steps.css).
 * @var Block $b  @var array $d
 */
$v = in_array($b->variant(), ['numbers', 'timeline', 'process'], true) ? $b->variant() : 'numbers';
$items = array_values(array_filter((array) ($d['items'] ?? []), static fn ($i) => '' !== trim((string) ($i['title'] ?? ''))));
$tag = fluid_htag($d);
// circles, colors, animation (field values; „auto“ = like design or website). Colors without own circle choice: light fill, border
$pick = static fn (string $k, array $ok) => in_array($d[$k] ?? 'auto', $ok, true) ? (string) $d[$k] : 'auto';
$colors = $pick('colors', ['alternate', 'accent', 'neutral']);
$marker = 'numbers' === $v ? 'auto' : $pick('marker', ['filled', 'soft', 'ring', 'dot']);
if ('auto' === $marker && 'auto' !== $colors && 'numbers' !== $v) {
$marker = 'soft';
}
$motion = $pick('motion', ['sequence', 'draw', 'none']);
if ('draw' === $motion && 'timeline' !== $v) {
$motion = 'auto';
}
$cls = ('auto' !== $marker ? ' steps--m-' . $marker : '') . ('auto' !== $colors ? ' steps--c-' . $colors : '') . ('auto' !== $motion ? ' steps--a-' . $motion : '');
?>
<div class="wrap">
  <?= fluid_head($b) ?>
  <?php if ($items): ?>
  <ol class="steps steps--<?= e($v) ?><?= 'numbers' === $v ? ' grid min-s' : '' ?><?= e($cls) ?>" role="list">
    <?php foreach ($items as $i => $it): ?>
    <li class="step"<?= 'none' === $motion ? '' : ' data-reveal' ?>>
      <span class="step__num" aria-hidden="true"><?= !empty($it['icon']) ? icon((string) $it['icon']) : str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
      <div class="step__body">
        <?php if ('' !== trim((string) ($it['meta'] ?? ''))): ?><p class="step__meta"<?= $b->edit("items.$i.meta") ?>><?= e($it['meta']) ?></p><?php endif ?>
        <<?= $tag ?> class="step__title"><span class="sr-only"><?= e(lt('Schritt {nr}:', ['nr' => $i + 1])) ?> </span><span<?= $b->edit("items.$i.title") ?>><?= e($it['title']) ?></span></<?= $tag ?>>
        <?php if ('' !== trim((string) ($it['text'] ?? ''))): ?><p class="step__text"<?= $b->edit("items.$i.text") ?>><?= nl2br(e($it['text']), false) ?></p><?php endif ?>
      </div>
    </li>
    <?php endforeach ?>
  </ol>
  <?php elseif (is_editing()): ?><p class="empty-hint"><?= I18n::msg('fluid_kit_empty_steps') ?></p><?php endif ?>
</div>
