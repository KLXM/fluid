<?php

namespace Klxm\Fluid\Kit\Fluid\Module;

use Klxm\Fluid\Field\AsBlock;
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

#[AsModule('fluid.video', 'Video')]
#[AsBlock(
    icon: 'play-circle',
    group: 'Medien',
    label: 'Video & Audio',
    help: 'Video: YouTube/Vimeo mit Zwei-Klick-Lösung (lädt erst nach Klick, ohne Cookies bis dahin) oder eigene Videodatei aus dem Medienpool – mit Vorschaubild, Untertiteln, Kapiteln und Transkript. Audio: Podcast, Interview oder Musik aus dem Medienpool, mehrere Dateien als Playlist.',
    variants: ['wide' => 'Video breit', 'text' => 'Video mit Text daneben', 'cinema' => 'Video im Kino-Look (randlos, dunkel)', 'audio' => 'Audio (Podcast, Musik)'],
    variantHelp: [
        'wide' => 'Das Video steht für sich – der Normalfall.',
        'text' => 'Video neben einem erklärenden Text.',
        'cinema' => 'Großer Auftritt: Film, Trailer, Imagevideo.',
        'audio' => 'Eine oder mehrere Audiodateien (MP3, M4A …). Ab zwei Dateien entsteht eine Playlist mit Weiter und Zurück.',
    ],
    nestable: ['wide', 'audio'],
)]
final class Video extends FluidBlock
{
    /** Darstellungen mit Video (die Felder für Video fehlen bei „Audio“); „morph“ ergänzt das Kit „NativeCSS“ (kit.php → blocks) */
    public const array VIDEO = ['wide', 'text', 'cinema'];

    #[Text('Dachzeile (optional)', maxLength: 60, width: 'half')]
    public string $eyebrow = '';

    #[Text('Überschrift', maxLength: 110, width: 'half')]
    public string $title = '';

    #[Textarea('Einleitung (optional)', maxLength: 400, rows: 2)]
    public string $intro = '';

    #[Url('YouTube- oder Vimeo-Link', width: 'half', variants: self::VIDEO)]
    public string $videoUrl = '';

    #[MediaPicker(
        'oder eigene Videodatei',
        types: ['mp4', 'm4v', 'webm', 'mov'],
        width: 'half',
        note: 'Datei aus dem Medienpool (MP4 oder WebM).',
        variants: [...self::VIDEO, 'morph'],
    )]
    public ?Media $videoFile = null;

    #[MediaPicker(
        'Eigenes Vorschaubild bzw. Cover (optional)',
        width: 'half',
        note: 'Ohne Auswahl zeigt eine eigene Videodatei automatisch ein Standbild aus dem Video. Bei Audio: quadratisches Cover.',
        preview: true,
    )]
    public ?Media $poster = null;

    #[Collection(
        'Untertitel und Kapitel (eigene Videodatei)',
        item: VideoTrack::class,
        itemLabel: 'Spur',
        titleField: 'lang',
        note: 'WebVTT- (.vtt) oder SubRip-Datei (.srt) aus dem Medienpool – Besucher schalten sie im Player ein. Videos mit Sprache brauchen Untertitel (Barrierefreiheit); aus ihnen entsteht auch das Transkript.',
        variants: self::VIDEO,
    )]
    public array $tracks = [];

    #[Checkbox('Untertitel in der Sprache der Seite gleich einschalten', width: 'half', variants: self::VIDEO)]
    public bool $ccOn = false;

    #[Checkbox('Transkript unter dem Video („Text zum Video“)', width: 'half', variants: self::VIDEO)]
    public bool $transcript = false;

    #[Choice(
        'Format',
        required: true,
        choices: ['16-9' => '16:9', '4-3' => '4:3', '21-9' => '21:9'],
        width: 'half',
        variants: [...self::VIDEO, 'morph'],
    )]
    public string $ratio = '16-9';

    /** @var list<AudioTrack> */
    #[Collection(
        'Audiodateien',
        item: AudioTrack::class,
        itemLabel: 'Audiodatei',
        titleField: 'title',
        max: 20,
        variants: ['audio'],
        note: 'Die erste spielt zuerst. Ab zwei Dateien zeigt der Player eine Playlist. Jede Datei braucht einen verständlichen Titel – ihn liest der Player vor.',
    )]
    public array $audio = [];

    #[RichText(
        'Transkript (empfohlen)',
        variants: ['audio'],
        note: 'Der gesprochene Text zum Nachlesen – für Menschen, die nicht hören können, und für die Suche. Erscheint aufklappbar unter dem Player („Text zum Audio“).',
    )]
    public string $audioTranscript = '';

    #[Text('Bildunterschrift (optional)', maxLength: 200)]
    public string $caption = '';

    #[RichText('Text daneben', variants: ['text', 'morph'])]
    public string $text = '';
}
