<?php

namespace Klxm\Fluid\Kit\Fluid\Module\Item;

use Klxm\Fluid\Field\Choice;
use Klxm\Fluid\Field\MediaPicker;
use Klxm\Fluid\Field\Text;
use Redaxo\Core\MediaPool\Media;
use Redaxo\Core\Translation\Translatable;

/** Entry of „Untertitel und Kapitel (eigene Videodatei)“ in the block Video */
final class VideoTrack
{
    #[MediaPicker(
        new Translatable('fluid_block_video_track_item_file'),
        required: true,
        types: ['vtt', 'srt'],
    )]
    public ?Media $file = null;

    #[Text(
        new Translatable('fluid_block_video_track_item_lang'),
        required: true,
        maxLength: 10,
        placeholder: new Translatable('fluid_block_video_track_item_lang_placeholder'),
        width: 'half',
    )]
    public string $lang = '';

    #[Choice(
        new Translatable('fluid_field_kind'),
        choices: [
            'subtitles' => new Translatable('fluid_block_video_track_item_kind_subtitles'),
            'captions' => new Translatable('fluid_block_video_track_item_kind_captions'),
            'chapters' => new Translatable('fluid_block_video_track_item_kind_chapters'),
        ],
        width: 'half',
    )]
    public string $kind = 'subtitles';

    #[Text(
        new Translatable('fluid_block_video_track_item_label'),
        maxLength: 40,
        placeholder: new Translatable('fluid_block_video_track_item_label_placeholder'),
    )]
    public string $label = '';
}
