<?php

use Klxm\Fluid\Block;
use Klxm\Fluid\Kit;
use Klxm\VideoEditor\VideoEditor;
use Redaxo\Core\Language\Language;
use Redaxo\Core\MediaManager\MediaManager;
use Redaxo\Core\MediaPool\Media;
use Redaxo\Core\Translation\I18n;

/**
 * Video & audio in the kit „NativeCSS“:
 *   wide/text/cinema  own video file in <ncss-video-player> (timeline with chapters, subtitle menu, keys, fullscreen);
 *                     YouTube/Vimeo stay with fluid's two-click solution. Markup and tracks: fluid_video_embed().
 *   audio             <ncss-audio-player layout="card"> with cover, chapters and playlist (from two files).
 * Both players only enhance a native <video controls>/<audio controls>: without JavaScript (and while editing) the
 * browser's player remains. CSS/JS: css/ncss-video-player.css, css/ncss-audio-player.css (kit.php → assets).
 * @var Block $b  @var array $d
 */
if ('audio' === $b->variant()):
    $tracks = fluid_audio_tracks((array) ($d['audio'] ?? []));
    $cover = !empty($d['poster']) ? media((string) $d['poster']) : null;
    $coverUrl = $cover ? media_url($cover, 600) : '';
    $lang = Language::getCurrent()->code;
    $first = $tracks[0] ?? null;
?>
<div class="wrap nv-audio">
  <?= fluid_head($b) ?>
  <?php if ($first): ?>
  <figure class="nv-audio__fig">
    <ncss-audio-player layout="card"<?= '' !== trim((string) $d['title']) ? ' aria-label="' . e(strip_emphasis(trim((string) $d['title']))) . '"' : '' ?>>
      <?php if ('' !== $coverUrl): ?><img class="ncss-audio-player-cover" src="<?= e($coverUrl) ?>" alt=""><?php endif ?>
      <p class="ncss-audio-player-title"><?= e($first['title']) ?></p>
      <?php if ('' !== $first['artist'] || count($tracks) > 1): ?><p class="ncss-audio-player-artist"><?= e($first['artist']) ?></p><?php endif ?>
      <audio controls preload="metadata" src="<?= e($first['url']) ?>"><?= '' !== $first['chapters'] ? '<track kind="chapters" src="' . e($first['chapters']) . '" srclang="' . e($lang) . '">' : '' ?></audio>
      <?php if (count($tracks) > 1): ?>
      <ol class="ncss-audio-player-playlist">
        <?php foreach ($tracks as $t): ?>
        <li><a href="<?= e($t['url']) ?>" data-title="<?= e($t['title']) ?>" data-artist="<?= e($t['artist']) ?>"<?= '' !== $coverUrl ? ' data-cover="' . e($coverUrl) . '"' : '' ?><?= '' !== $t['chapters'] ? ' data-chapters="' . e($t['chapters']) . '"' : '' ?>><?= e($t['title']) ?></a></li>
        <?php endforeach ?>
      </ol>
      <?php endif ?>
    </ncss-audio-player>
    <?php if ('' !== trim(strip_tags((string) ($d['audio_transcript'] ?? '')))): ?>
    <details class="vtranscript"><summary><?= e(lt('Text zum Audio')) ?></summary><div class="vtranscript__body prose"><?= rich((string) $d['audio_transcript']) ?></div></details>
    <?php endif ?>
    <?php if ('' !== trim((string) $d['caption']) || is_editing()): ?><figcaption class="video__cap"<?= $b->edit('caption') ?>><?= fluid_title((string) $d['caption']) ?></figcaption><?php endif ?>
  </figure>
  <?php elseif (is_editing()): ?><p class="empty-hint"><?= I18n::msg('fluid_kit_empty_audio') ?></p><?php endif ?>
</div>
<?php return; endif;

// „Video mit Morphing beim Scrollen“ (this kit only): own file muted in a loop in .ncss-morph, play/pause toggle and
// loading/pausing as with the background video (fluid_video_toggle(), js/hero-video.js: never with reduced motion/data saver).
// The class ncss-video-bg-toggle makes NativeCSS's morph.css carry the toggle along with the media's corner.
// While editing and without own file: like „Video mit Text daneben“.
if ('morph' === $b->variant()) {
    $mv = !empty($d['video_file']) ? media((string) $d['video_file']) : null;
    $mv = $mv && str_starts_with((string) $mv['mime'], 'video/') ? $mv : null;
    if ($mv && !is_editing()) {
        Kit::active()->markUsed('video', '@morph');
        $mm = MediaManager::class;
        $posterFile = (string) ($d['poster'] ?? '');
        $posterUrl = '' !== $posterFile && Media::get($posterFile) ? $mm::getUrl('fluid_poster', $posterFile) : (class_exists(VideoEditor::class) ? (string) VideoEditor::frameUrl((string) $mv['file']) : '');
        echo '<div class="wrap nv-morph"><div class="ncss-morph ncss-morph--card"><div class="ncss-morph-stage">'
            . '<div class="ncss-morph-media nv-morph__media" data-hero-video-box><video data-hero-video muted loop playsinline preload="none" disablepictureinpicture tabindex="-1" aria-hidden="true"' . ('' !== $posterUrl ? ' poster="' . e($posterUrl) . '"' : '') . '>'
            . '<source data-src="' . e(media_url($mv)) . '" type="' . e((string) $mv['mime']) . '"></video>' . fluid_video_toggle('ncss-video-bg-toggle nv-morph__toggle') . '</div>'
            . '<div class="ncss-morph-content">' . fluid_head($b)
            . ('' !== trim(strip_tags((string) $d['text'])) ? '<div class="prose">' . rich((string) $d['text']) . '</div>' : '')
            . ('' !== trim((string) $d['caption']) ? '<p class="video__cap">' . fluid_title((string) $d['caption']) . '</p>' : '')
            . '</div></div></div></div>';

        return;
    }
}

// Video: fluid's output, an own video file gets NativeCSS's player
$embed = fluid_video_embed((string) $d['video_url'], (string) $d['video_file'], (string) $d['poster'], (string) ($d['ratio'] ?: '16-9'), strip_emphasis(trim((string) $d['title'])),
    (array) ($d['tracks'] ?? []), !empty($d['cc_on']), !empty($d['transcript']));
// (without the class .vembed: fluid's CSS for it positions the video absolutely in a frame – the player brings its own)
$embed = (string) preg_replace('~<div class="vembed[^"]*">(<video\b.*?</video>)</div>~s', '<div class="nv-vplayer"><ncss-video-player>$1</ncss-video-player></div>', $embed, 1);
if (str_contains($embed, '<ncss-video-player>')) {
    Kit::active()->markUsed('video', '@player');
}
$v = 'morph' === $b->variant() ? 'text' : (in_array($b->variant(), ['wide', 'text', 'cinema'], true) ? $b->variant() : 'wide');
$fig = '<figure class="video">' . $embed
    . (('' !== trim((string) $d['caption']) || is_editing()) ? '<figcaption class="video__cap"' . $b->edit('caption') . '>' . fluid_title((string) $d['caption']) . '</figcaption>' : '')
    . '</figure>' . fluid_caption_hint($d);
?>
<?php if ('text' === $v): ?>
<div class="wrap video-split">
  <div class="video-split__text">
    <?= fluid_head($b) ?>
    <?php if ('' !== trim(strip_tags((string) $d['text'])) || is_editing()): ?><div class="prose"<?= $b->edit('text', 'rich') ?>><?= rich((string) $d['text']) ?></div><?php endif ?>
  </div>
  <?= $fig ?>
</div>
<?php else: ?>
<div class="wrap<?= 'cinema' === $v ? ' video-cinema' : '' ?>">
  <?= fluid_head($b, 'cinema' === $v ? 'sec-head--center' : '') ?>
  <?= $fig ?>
</div>
<?php endif;
