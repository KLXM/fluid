<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Field\AsFluidBlock;
use Klxm\Fluid\Field\Checkbox;
use Klxm\Fluid\Field\Choice;
use Klxm\Fluid\Field\Collection;
use Klxm\Fluid\Field\MediaPicker;
use Klxm\Fluid\Field\RichText;
use Klxm\Fluid\Field\Text;
use Klxm\Fluid\Field\Textarea;
use Klxm\Fluid\Field\Url;
use Klxm\Fluid\Kit\Fluid\FluidBlock;
use Klxm\Fluid\Kit\Fluid\Module\Item\AudioTrack;
use Klxm\Fluid\Kit\Fluid\Module\Item\VideoTrack;
use Redaxo\Core\Content\AsModule;
use Redaxo\Core\MediaPool\Media;
use Redaxo\Core\Translation\Translatable;

#[AsModule('fluid.video', new Translatable('fluid_block_video'))]
#[AsFluidBlock(
    icon: 'play-circle',
    group: new Translatable('fluid_block_group_media'),
    label: new Translatable('fluid_block_video_label'),
    help: new Translatable('fluid_block_video_help'),
    variants: ['wide' => new Translatable('fluid_block_video_variant_wide'), 'text' => new Translatable('fluid_block_video_variant_text'), 'cinema' => new Translatable('fluid_block_video_variant_cinema'), 'audio' => new Translatable('fluid_block_video_variant_audio')],
    variantHelp: [
        'wide' => new Translatable('fluid_block_video_variant_wide_help'),
        'text' => new Translatable('fluid_block_video_variant_text_help'),
        'cinema' => new Translatable('fluid_block_video_variant_cinema_help'),
        'audio' => new Translatable('fluid_block_video_variant_audio_help'),
    ],
    nestable: ['wide', 'audio'],
)]
final class Video extends FluidBlock
{
    /** Displays with video (the video fields are missing for „Audio“); kit „NativeCSS“ adds „morph“ (kit.php → blocks) */
    public const array VIDEO = ['wide', 'text', 'cinema'];

    #[Text(
        new Translatable('fluid_field_eyebrow'),
        maxLength: 60,
        width: 'half',
    )]
    public string $eyebrow = '';

    #[Text(
        new Translatable('fluid_field_title'),
        maxLength: 110,
        width: 'half',
    )]
    public string $title = '';

    #[Textarea(
        new Translatable('fluid_field_intro'),
        maxLength: 400,
        rows: 2,
    )]
    public string $intro = '';

    #[Url(
        new Translatable('fluid_block_video_video_url'),
        width: 'half',
        variants: self::VIDEO,
    )]
    public string $videoUrl = '';

    #[MediaPicker(
        new Translatable('fluid_block_video_video_file'),
        types: ['mp4', 'm4v', 'webm', 'mov'],
        width: 'half',
        note: new Translatable('fluid_block_video_video_file_help'),
        variants: [...self::VIDEO, 'morph'],
    )]
    public ?Media $videoFile = null;

    #[MediaPicker(
        new Translatable('fluid_block_video_poster'),
        width: 'half',
        note: new Translatable('fluid_block_video_poster_help'),
        preview: true,
    )]
    public ?Media $poster = null;

    #[Collection(
        new Translatable('fluid_block_video_tracks'),
        item: VideoTrack::class,
        itemLabel: new Translatable('fluid_block_video_tracks_item_label'),
        titleField: 'lang',
        note: new Translatable('fluid_block_video_tracks_help'),
        variants: self::VIDEO,
    )]
    public array $tracks = [];

    #[Checkbox(
        new Translatable('fluid_block_video_cc_on'),
        width: 'half',
        variants: self::VIDEO,
    )]
    public bool $ccOn = false;

    #[Checkbox(
        new Translatable('fluid_block_video_transcript'),
        width: 'half',
        variants: self::VIDEO,
    )]
    public bool $transcript = false;

    #[Choice(
        new Translatable('fluid_block_video_ratio'),
        required: true,
        choices: ['16-9' => '16:9', '4-3' => '4:3', '21-9' => '21:9'],
        width: 'half',
        variants: [...self::VIDEO, 'morph'],
    )]
    public string $ratio = '16-9';

    /** @var list<AudioTrack> */
    #[Collection(
        new Translatable('fluid_block_video_audio'),
        item: AudioTrack::class,
        itemLabel: new Translatable('fluid_block_video_audio_item_label'),
        titleField: 'title',
        max: 20,
        variants: ['audio'],
        note: new Translatable('fluid_block_video_audio_help'),
    )]
    public array $audio = [];

    #[RichText(
        new Translatable('fluid_block_video_audio_transcript'),
        variants: ['audio'],
        note: new Translatable('fluid_block_video_audio_transcript_help'),
    )]
    public string $audioTranscript = '';

    #[Text(
        new Translatable('fluid_field_caption_optional'),
        maxLength: 200,
    )]
    public string $caption = '';

    #[RichText(
        new Translatable('fluid_block_video_text'),
        variants: ['text', 'morph'],
    )]
    public string $text = '';
}
