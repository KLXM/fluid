<?php
/**
 * Video & Audio im Kit „NativeCSS“:
 *   wide/text/cinema  eigene Videodatei im <ncss-video-player> (Zeitleiste mit Kapiteln, Untertitel-Menü, Tasten, Vollbild);
 *                     YouTube/Vimeo bleiben bei der Zwei-Klick-Lösung von fluid. Markup und Spuren: fluid_video_embed().
 *   audio             <ncss-audio-player layout="card"> mit Cover, Kapiteln und Playlist (ab zwei Dateien).
 * Beide Player erweitern nur ein natives <video controls>/<audio controls>: ohne JavaScript (und beim Bearbeiten) bleibt
 * der Player des Browsers. CSS/JS: css/ncss-video-player.css, css/ncss-audio-player.css (kit.php → assets).
 * @var \Klxm\Fluid\Block $b  @var array $d
 */
if ('audio' === $b->variant()):
    $tracks = fluid_audio_tracks((array) ($d['audio'] ?? []));
    $cover = !empty($d['poster']) ? media((string) $d['poster']) : null;
    $coverUrl = $cover ? media_url($cover, 600) : '';
    $lang = \Redaxo\Core\Language\Language::getCurrent()->code;
    $first = $tracks[0] ?? null;
?>
<div class="wrap nv-audio">
  <?= fluid_head($b) ?>
  <?php if ($first): ?>
  <figure class="nv-audio__fig">
    <ncss-audio-player layout="card"<?= '' !== trim((string) $d['title']) ? ' aria-label="' . e(strip_emphasis(trim((string) $d['title']))) . '"' : '' ?>>
      <?php if ('' !== $coverUrl): ?><img class="ncss-audio-player-cover" src="<?= e($coverUrl) ?>" alt=""><?php endif; ?>
      <p class="ncss-audio-player-title"><?= e($first['title']) ?></p>
      <?php if ('' !== $first['artist'] || count($tracks) > 1): ?><p class="ncss-audio-player-artist"><?= e($first['artist']) ?></p><?php endif; ?>
      <audio controls preload="metadata" src="<?= e($first['url']) ?>"><?= '' !== $first['chapters'] ? '<track kind="chapters" src="' . e($first['chapters']) . '" srclang="' . e($lang) . '">' : '' ?></audio>
      <?php if (count($tracks) > 1): ?>
      <ol class="ncss-audio-player-playlist">
        <?php foreach ($tracks as $t): ?>
        <li><a href="<?= e($t['url']) ?>" data-title="<?= e($t['title']) ?>" data-artist="<?= e($t['artist']) ?>"<?= '' !== $coverUrl ? ' data-cover="' . e($coverUrl) . '"' : '' ?><?= '' !== $t['chapters'] ? ' data-chapters="' . e($t['chapters']) . '"' : '' ?>><?= e($t['title']) ?></a></li>
        <?php endforeach; ?>
      </ol>
      <?php endif; ?>
    </ncss-audio-player>
    <?php if (trim(strip_tags((string) ($d['audio_transcript'] ?? ''))) !== ''): ?>
    <details class="vtranscript"><summary><?= e(lt('Text zum Audio')) ?></summary><div class="vtranscript__body prose"><?= rich((string) $d['audio_transcript']) ?></div></details>
    <?php endif; ?>
    <?php if (trim((string) $d['caption']) !== '' || is_editing()): ?><figcaption class="video__cap"<?= $b->edit('caption') ?>><?= fluid_title((string) $d['caption']) ?></figcaption><?php endif; ?>
  </figure>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch keine Audiodatei – in der Seitenleiste hinzufügen.</p><?php endif; ?>
</div>
<?php return; endif;

// Video: Ausgabe von fluid, eine eigene Videodatei bekommt den Player von NativeCSS
$embed = fluid_video_embed((string) $d['video_url'], (string) $d['video_file'], (string) $d['poster'], (string) ($d['ratio'] ?: '16-9'), strip_emphasis(trim((string) $d['title'])),
    (array) ($d['tracks'] ?? []), !empty($d['cc_on']), !empty($d['transcript']));
// (ohne die Klasse .vembed: deren CSS von fluid legt das Video absolut in einen Rahmen – der Player bringt sein eigenes)
$embed = (string) preg_replace('~<div class="vembed[^"]*">(<video\b.*?</video>)</div>~s', '<div class="nv-vplayer"><ncss-video-player>$1</ncss-video-player></div>', $embed, 1);
if (str_contains($embed, '<ncss-video-player>')) {
    \Klxm\Fluid\Kit::active()->markUsed('video', '@player');
}
$v = in_array($b->variant(), ['wide', 'text', 'cinema'], true) ? $b->variant() : 'wide';
$fig = '<figure class="video">' . $embed
    . ((trim((string) $d['caption']) !== '' || is_editing()) ? '<figcaption class="video__cap"' . $b->edit('caption') . '>' . fluid_title((string) $d['caption']) . '</figcaption>' : '')
    . '</figure>' . fluid_caption_hint($d);
?>
<?php if ($v === 'text'): ?>
<div class="wrap video-split">
  <div class="video-split__text">
    <?= fluid_head($b) ?>
    <?php if (trim(strip_tags((string) $d['text'])) !== '' || is_editing()): ?><div class="prose"<?= $b->edit('text', 'rich') ?>><?= rich((string) $d['text']) ?></div><?php endif; ?>
  </div>
  <?= $fig ?>
</div>
<?php else: ?>
<div class="wrap<?= $v === 'cinema' ? ' video-cinema' : '' ?>">
  <?= fluid_head($b, $v === 'cinema' ? 'sec-head--center' : '') ?>
  <?= $fig ?>
</div>
<?php endif;
