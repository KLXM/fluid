<?php

use Klxm\Fluid\Block;

/**
 * Bento grid: dense grid (grid-auto-flow: dense) of tiles of different sizes. The number of columns follows from the
 * width (auto-fill, min. 15 rem); wide/tall tiles only span when the container has room (@container).
 * @var Block $b  @var array $d
 */
$items = array_values(array_filter((array) ($d['items'] ?? []), static fn ($i) => '' !== trim((string) ($i['title'] ?? '')) || is_editing()));
$tag = fluid_htag($d);
$tones = ['card', 'tint', 'highlight', 'accent', 'dark', 'image'];
?>
<div class="wrap">
  <?= fluid_head($b) ?>
  <?php if ($items): ?>
  <div class="bento-wrap">
  <ul class="bento" role="list">
    <?php foreach ($items as $i => $it):
        $size = in_array($it['size'] ?? '', ['s', 'wide', 'tall', 'big'], true) ? $it['size'] : 's';
        $tone = in_array($it['tone'] ?? '', $tones, true) ? $it['tone'] : 'card';
        $img = !empty($it['image']) ? img((string) $it['image'], 'big' === $size || 'wide' === $size ? '(min-width: 1080px) 640px, 100vw' : '(min-width: 1080px) 360px, 100vw', 'image' === $tone ? ['alt' => ''] : []) : '';
        if ('image' === $tone && '' === $img) {
        $tone = 'dark';
        }
        $link = trim((string) ($it['link'] ?? ''));
        $eyebrow = trim((string) ($it['eyebrow'] ?? ''));
        $big = '' !== $eyebrow && preg_match('#^[\d.,+%€$×x/<>~\s-]{1,8}$#u', $eyebrow);
    ?>
    <li class="tile tile--<?= e($size) ?> tile--<?= e($tone) ?><?= ['tint' => ' bg-tint', 'accent' => ' bg-accent', 'dark' => ' bg-dark', 'image' => ' on-media'][$tone] ?? '' ?><?= '' !== $img ? ' tile--has-img' : '' ?>" data-reveal><?= $b->targetEdit($link, (string) ($it['title'] ?? '')) ?>
      <?php if ('' !== $img): ?><div class="tile__img"<?= 'image' === $tone ? ' aria-hidden="true"' : '' ?>><?= $img ?></div><?php endif ?>
      <div class="tile__body">
        <?php if (!empty($it['icon'])): ?><span class="tile__ico"><?= icon((string) $it['icon']) ?></span><?php endif ?>
        <?php if ('' !== $eyebrow): ?><p class="<?= $big ? 'tile__num' : 'tile__eyebrow' ?>"<?= $b->edit("items.$i.eyebrow") ?>><?= fluid_title($eyebrow) ?></p><?php endif ?>
        <<?= $tag ?> class="tile__title"><?php if ('' !== $link && !is_editing()): ?><a class="cover-link" <?= fluid_link_attrs($link) ?>><?= fluid_title((string) $it['title']) ?></a><?php else: ?><span<?= $b->edit("items.$i.title") ?>><?= fluid_title((string) ($it['title'] ?? '')) ?></span><?php endif ?></<?= $tag ?>>
        <?php if ('' !== trim((string) ($it['text'] ?? ''))): ?><p class="tile__text"<?= $b->edit("items.$i.text") ?>><?= nl2br(fluid_title((string) $it['text']), false) ?></p><?php endif ?>
        <?php if ('' !== $link): ?><span class="tile__go" aria-hidden="true"><?= icon('arrow-up-right') ?></span><?php endif ?>
      </div>
    </li>
    <?php endforeach ?>
  </ul>
  </div>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch keine Kacheln – in der Seitenleiste hinzufügen.</p><?php endif ?>
</div>
