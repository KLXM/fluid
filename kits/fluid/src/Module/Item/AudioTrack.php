<?php

namespace Klxm\Fluid\Kit\Fluid\Module\Item;

use Klxm\Fluid\Field\MediaPicker;
use Klxm\Fluid\Field\Text;
use Redaxo\Core\MediaPool\Media;
use Redaxo\Core\Translation\Translatable;

/** Eintrag von „Audiodateien“ im Block „Video & Audio“ (Darstellung „Audio“) */
final class AudioTrack
{
    #[MediaPicker(
        new Translatable('fluid_block_video_audio_item_file'),
        required: true,
        types: ['mp3', 'm4a', 'aac', 'ogg', 'oga', 'opus', 'wav', 'flac'],
    )]
    public ?Media $file = null;

    #[Text(
        new Translatable('fluid_field_item_title'),
        maxLength: 90,
        width: 'half',
        placeholder: new Translatable('fluid_block_video_audio_item_title_placeholder'),
    )]
    public string $title = '';

    #[Text(
        new Translatable('fluid_block_video_audio_item_artist'),
        maxLength: 90,
        width: 'half',
    )]
    public string $artist = '';

    #[MediaPicker(
        new Translatable('fluid_block_video_audio_item_chapters'),
        types: ['vtt', 'srt'],
        note: new Translatable('fluid_block_video_audio_item_chapters_help'),
    )]
    public ?Media $chapters = null;
}
