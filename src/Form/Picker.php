<?php

namespace Klxm\Fluid\Form;

use Klxm\ImageEditor\ImageEditor;
use Klxm\ImageEditor\ImageEditorAddon;
use Klxm\VideoEditor\VideoEditor;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Content\Category;
use Redaxo\Core\Language\Language;
use Redaxo\Core\MediaManager\MediaManager;
use Redaxo\Core\MediaPool\Media;

use function in_array;
use function Redaxo\Core\View\escape;

/**
 * Selection of media (images, files) and articles – in the backend and in the website editor, without popup windows.
 * In the backend assets/form.js opens the core's media pool or linkmap as an overlay (rex.pick), on the website its own
 * selection in the editor (Media\Library, Links).
 *
 * Link fields store `redaxo://ID` (article) or an address (https://…, mailto:, tel:, #anchor).
 *
 * @internal
 */
final class Picker
{
    public const array IMAGE_TYPES = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg'];
    public const array VIDEO_TYPES = ['mp4', 'm4v', 'mov', 'webm', 'mkv', 'ogv', 'avi'];

    /** @param list<string>|null $types erlaubte Endungen (Feldoption `types`, z. B. ['vtt', 'srt']) */
    public static function media(string $id, string $name, string $value, bool $images, bool $backend, ?array $types = null): string
    {
        $types ??= $images ? self::IMAGE_TYPES : null;
        $preview = MediaManager::getUrl('rex_media_small', '__FILE__');
        $media = '' !== $value ? Media::get($value) : null;
        $thumb = $media && in_array(strtolower($media->getExtension()), self::IMAGE_TYPES, true) ? '<img src="' . escape(self::thumb($media->fileName)) . '" alt="">' : '';
        // addon image_editor: „Bearbeiten“ opens the image editor (crop, rotate …) – API in the respective area
        $ie = $images && class_exists(ImageEditorAddon::class) ? ImageEditorAddon::config() : null;
        // addon video_editor: „Video bearbeiten“ opens the video in the media pool (optimize, poster …) – only with permission
        $ve = !$images && (null === $types || [] !== array_intersect($types, self::VIDEO_TYPES)) && class_exists(VideoEditor::class)
            ? VideoEditor::editUrlTemplate() : null;

        return '<div class="blocks-pick blocks-pick--media" data-blocks-pick="media" data-types="' . escape(implode(',', $types ?? [])) . '" data-preview="' . escape($preview) . '"'
            . ($ie ? ' data-ie-api="' . escape($ie['api']) . '" data-ie-js="' . escape($ie['js']) . '" data-ie-css="' . escape($ie['css']) . '"' : '')
            . ($ve ? ' data-ve-edit="' . escape($ve) . '"' : '') . '>'
            . '<div class="blocks-pick__row">'
            . '<span class="blocks-pick__thumb" data-blocks-pick-thumb' . ('' === $thumb ? ' hidden' : '') . '>' . $thumb . '</span>'
            . self::group($backend, '<input class="rex-input" type="text" id="' . escape($id) . '" name="' . escape($name) . '" value="' . escape($value) . '" placeholder="Datei aus dem Medienpool">', 'Medien auswählen', 'rex-icon-open-mediapool', 'Auswählen', null !== $ie, null !== $ve)
            . '</div></div>';
    }

    public static function link(string $id, string $name, string $value, bool $backend): string
    {
        $art = preg_match('~^redaxo://(\d+)~', $value, $m) ? Article::get((int) $m[1]) : null;
        $category = $art->categoryId ?? Category::getCurrent()->id ?? 0;

        return '<div class="blocks-pick blocks-pick--link" data-blocks-pick="link" data-category="' . $category . '" data-language="' . Language::getCurrentId() . '">'
            . '<div class="blocks-pick__row">'
            . self::group($backend, '<input class="rex-input" type="text" id="' . escape($id) . '" name="' . escape($name) . '" value="' . escape($value) . '" placeholder="Seite, https://…, E-Mail, tel: oder #anker">', 'Seite auswählen', 'rex-icon-open-linkmap', 'Seite wählen')
            . '</div>'
            . '<span class="blocks-pick__name" data-blocks-pick-name>' . escape($art ? $art->name . ' [' . $art->id . ']' : '') . '</span>'
            . '</div>';
    }

    /** Preview image (with addon image_editor the edited version) */
    public static function thumb(string $file): string
    {
        return class_exists(ImageEditor::class)
            ? ImageEditor::url($file, 320)
            : MediaManager::getUrl('rex_media_small', $file);
    }

    /** Input field with buttons – in the backend with the classes of the core widgets (rex-input-group), plain on the website */
    private static function group(bool $backend, string $input, string $title, string $icon, string $label, bool $edit = false, bool $video = false): string
    {
        if ($backend) {
            $btn = static fn (string $attr, string $title, string $icon): string => '<button type="button" class="rex-button" data-variant="field" ' . $attr
                . ' title="' . escape($title) . '" aria-label="' . escape($title) . '"><i class="rex-icon ' . $icon . '" aria-hidden="true"></i></button>';

            return '<div class="blocks-pick__field"><div class="rex-input-group">' . $input . '<span class="rex-input-group-actions">'
                . $btn('data-blocks-pick-open', $title, $icon)
                . ($edit ? $btn('data-blocks-pick-edit', 'Bild bearbeiten (Zuschneiden, Drehen …)', 'fa-solid fa-crop-simple')
                    . $btn('data-blocks-pick-adjust', 'Bild anpassen (Effekte, Fokuspunkt)', 'fa-solid fa-sliders') : '')
                . ($video ? $btn('data-blocks-pick-video', 'Video bearbeiten (Optimieren, Poster …) – öffnet den Medienpool', 'fa-solid fa-film') : '')
                . $btn('data-blocks-pick-clear', 'Entfernen', 'rex-icon-delete-media')
                . '</span></div></div>';
        }

        return $input . '<span class="blocks-pick__btns">'
            . '<button type="button" class="blocks-btn" data-blocks-pick-open title="' . escape($title) . '">' . escape($label) . '</button>'
            . ($edit ? '<button type="button" class="blocks-btn" data-blocks-pick-edit title="Bild bearbeiten (Zuschneiden, Drehen …)">Bearbeiten</button>'
                . '<button type="button" class="blocks-btn" data-blocks-pick-adjust title="Bild anpassen (Effekte, Fokuspunkt)">Anpassen</button>' : '')
            . ($video ? '<button type="button" class="blocks-btn" data-blocks-pick-video title="Video bearbeiten (Optimieren, Poster …) – öffnet den Medienpool">Video bearbeiten</button>' : '')
            . '<button type="button" class="blocks-btn" data-blocks-pick-clear title="Entfernen" aria-label="Entfernen">✕</button>'
            . '</span>';
    }
}
