<?php

use Klxm\Fluid\Block;
use Redaxo\Core\Language\Language;

/**
 * Video (YouTube/Vimeo with two-click solution, MP4 from the media pool): wide · text (text beside) · cinema (borderless, dark).
 * From KLXM Studio; embedding: fluid_video_embed() (functions.php), script js/embed.js (loads the player only after a click).
 * audio: audio files with the browser's player (<audio controls>), several as a list – the kit „NativeCSS“ uses
 * <ncss-audio-player> with playlist for this.
 * @var Block $b  @var array $d
 */
if ('audio' === $b->variant()):
    $tracks = fluid_audio_tracks((array) ($d['audio'] ?? []));
    $cover = (string) ($d['poster'] ?? '');
?>
<div class="wrap audio-block">
  <?= fluid_head($b) ?>
  <?php if ($tracks): ?>
  <figure class="audio-card">
    <?= '' !== $cover ? '<div class="audio-card__cover">' . img($cover, '160px', ['alt' => '', 'ratio' => '1:1']) . '</div>' : '' ?>
    <ol class="audio-card__list" role="list">
      <?php foreach ($tracks as $i => $t): ?>
      <li class="audio-card__item">
        <p class="audio-card__title"><?= e($t['title']) ?><?php if ('' !== $t['artist']): ?> <span class="audio-card__artist">· <?= e($t['artist']) ?></span><?php endif ?></p>
        <audio controls preload="<?= 0 === $i ? 'metadata' : 'none' ?>" src="<?= e($t['url']) ?>"><?= '' !== $t['chapters'] ? '<track kind="chapters" src="' . e($t['chapters']) . '" srclang="' . e(Language::getCurrent()->code) . '">' : '' ?></audio>
      </li>
      <?php endforeach ?>
    </ol>
    <?php if ('' !== trim(strip_tags((string) ($d['audio_transcript'] ?? '')))): ?>
    <details class="vtranscript"><summary><?= e(lt('Text zum Audio')) ?></summary><div class="vtranscript__body prose"><?= rich((string) $d['audio_transcript']) ?></div></details>
    <?php endif ?>
    <?php if ('' !== trim((string) $d['caption']) || is_editing()): ?><figcaption class="video__cap"<?= $b->edit('caption') ?>><?= fluid_title((string) $d['caption']) ?></figcaption><?php endif ?>
  </figure>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch keine Audiodatei – in der Seitenleiste hinzufügen.</p><?php endif ?>
</div>
<?php return; endif;
// "morph" (kit NativeCSS) after a kit switch: video with text beside
$v = 'morph' === $b->variant() ? 'text' : (in_array($b->variant(), ['wide', 'text', 'cinema'], true) ? $b->variant() : 'wide');
$embed = fluid_video_embed((string) $d['video_url'], (string) $d['video_file'], (string) $d['poster'], (string) ($d['ratio'] ?: '16-9'), strip_emphasis(trim((string) $d['title'])),
    (array) ($d['tracks'] ?? []), !empty($d['cc_on']), !empty($d['transcript']));
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
