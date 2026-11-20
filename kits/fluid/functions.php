<?php

/**
 * Hilfsfunktionen für die Ausgaben des Kits „fluid“.
 *
 * Die View-Dateien stammen aus KLXM Studio; die Funktionen hier bilden die dort genutzten Helfer auf REDAXO ab
 * (Medienpool statt Mediathek, REDAXO-Links, Sanitizer des Cores). Prototyp – im Paket kommen sie in einen Namespace.
 */

use Klxm\Fluid\Block;
use Klxm\Fluid\Editing;
use Klxm\Fluid\Kit\Fluid\FluidKit;
use Redaxo\Core\Content\Article;
use Redaxo\Core\MediaPool\Media;

use function Redaxo\Core\View\escape;

if (!function_exists('e')) {
    function e(mixed $v): string
    {
        return (string) escape((string) $v);
    }
}

/**
 * Feste Texte der Website in der Sprache der aufgerufenen Seite (Quelle Deutsch, wie lt() in KLXM Studio):
 * lang/site/{code}.php des Kits; Projekte ergänzen/ändern per Extension Point FLUID_SITE_TRANSLATIONS (Subject: Array, Param code).
 */
function lt(string $text, array $vars = []): string
{
    static $tables = [];
    $code = \Redaxo\Core\Language\Language::getCurrent()->code;
    if ('de' !== $code) {
        if (!isset($tables[$code])) {
            $file = __DIR__ . '/lang/site/' . basename($code) . '.php';
            $tables[$code] = (array) \Redaxo\Core\ExtensionPoint\Extension::dispatch(new \Redaxo\Core\ExtensionPoint\ExtensionPoint(
                'FLUID_SITE_TRANSLATIONS', is_file($file) ? (array) require $file : [], ['code' => $code]));
        }
        $text = $tables[$code][$text] ?? $text;
    }
    foreach ($vars as $k => $v) {
        $text = str_replace('{' . $k . '}', (string) $v, $text);
    }

    return $text;
}

function __(string $text): string
{
    return $text;
}

function is_editing(): bool
{
    return Editing::active();
}

/** Website-Einstellungen (Klxm\Fluid\Settings) – wie setting() in KLXM Studio */
function setting(string $key, mixed $default = null): mixed
{
    return \Klxm\Fluid\Settings::get($key, $default);
}

function filled(mixed $v): bool
{
    return null !== $v && '' !== trim((string) (is_array($v) ? implode('', $v) : $v)) && !str_starts_with(trim((string) (is_array($v) ? '' : $v)), '[');
}

// ------------------------------------------------------------ Website: Name, Kontakt, Zeiten, Social (aus kits/fluid/functions.php)

function fluid_name(bool $short = false): string
{
    return \Klxm\Fluid\Settings::name($short);
}

function fluid_phone(): string
{
    return trim((string) setting('phone', ''));
}

/** tel:-Adresse aus einer Telefonnummer (+49 statt 0) oder null */
function fluid_phone_href(): ?string
{
    $p = preg_replace('~[^\d+]~', '', fluid_phone());
    if ('' === $p || strlen($p) < 4) {
        return null;
    }
    if (str_starts_with($p, '00')) {
        $p = '+' . substr($p, 2);
    } elseif (str_starts_with($p, '0')) {
        $p = '+49' . substr($p, 1);
    }

    return 'tel:' . $p;
}

function fluid_email(): string
{
    $m = trim((string) setting('email', ''));

    return filter_var($m, FILTER_VALIDATE_EMAIL) ? $m : '';
}

/** Adresse als Zeilen: [Straße, PLZ Ort, Land] – nur befüllte Teile */
function fluid_address_lines(): array
{
    $city = trim(trim((string) setting('zip', '')) . ' ' . trim((string) setting('city', '')));

    return array_values(array_filter([trim((string) setting('street', '')), $city, trim((string) setting('country', ''))], 'filled'));
}

function fluid_clock(string $t): string
{
    return preg_match('~^(\d{1,2}):(\d{2})~', $t, $m) ? ((int) $m[1]) . ('00' === $m[2] ? '' : ':' . $m[2]) : $t;
}

function fluid_time_range(array $r): string
{
    $seg = [];
    if (!empty($r['von']) && !empty($r['bis'])) {
        if (!empty($r['pause_von']) && !empty($r['pause_bis'])) {
            $seg[] = fluid_clock($r['von']) . '–' . fluid_clock($r['pause_von']);
            $seg[] = fluid_clock($r['pause_bis']) . '–' . fluid_clock($r['bis']);
        } else {
            $seg[] = fluid_clock($r['von']) . '–' . fluid_clock($r['bis']);
        }
    }
    $time = $seg ? implode(', ', $seg) . ' Uhr' : '';

    return implode(' · ', array_filter([$time, trim((string) ($r['notiz'] ?? ''))]));
}

function fluid_weekday(int $dow, bool $short = false): string
{
    $l = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'][$dow] ?? '';

    return $short ? mb_substr($l, 0, 2) : $l;
}

/** Öffnungszeiten Mo–So, aufeinanderfolgende Tage mit gleichen Zeiten zusammengefasst @return list<array{days: string, time: string}> */
function fluid_hours(): array
{
    $byDay = [];
    foreach ((array) setting('hours', []) as $r) {
        $dow = (int) ($r['tag'] ?? -1);
        if ($dow < 0 || $dow > 6) {
            continue;
        }
        $t = fluid_time_range($r);
        if ('' !== $t) {
            $byDay[$dow][] = $t;
        }
    }
    $groups = [];
    foreach ([1, 2, 3, 4, 5, 6, 0] as $dow) {
        if (!isset($byDay[$dow])) {
            $groups[] = null;
            continue;
        }
        $time = implode(' · ', $byDay[$dow]);
        $last = $groups ? $groups[array_key_last($groups)] : null;
        if ($last && $last['time'] === $time) {
            $groups[array_key_last($groups)]['to'] = $dow;
        } else {
            $groups[] = ['from' => $dow, 'to' => $dow, 'time' => $time];
        }
    }
    $out = [];
    foreach (array_filter($groups) as $g) {
        $out[] = ['days' => fluid_weekday($g['from'], $g['to'] !== $g['from']) . ($g['to'] !== $g['from'] ? '–' . fluid_weekday($g['to'], true) : ''), 'time' => $g['time']];
    }

    return $out;
}

function fluid_social(): array
{
    return array_values(array_filter((array) setting('social', []), static fn ($s) => '' !== trim((string) ($s['label'] ?? '')) && preg_match('~^https?://~i', (string) ($s['url'] ?? ''))));
}

function fluid_quick_contact(string $class = 'quick'): string
{
    $phone = fluid_phone();
    $tel = fluid_phone_href();
    $email = fluid_email();
    if (!($tel && filled($phone)) && '' === $email) {
        return '';
    }
    $h = '<ul class="' . e($class) . '" role="list">';
    if ($tel && filled($phone)) {
        $h .= '<li><a href="' . e($tel) . '">' . icon('phone') . '<span>' . e($phone) . '</span></a></li>';
    }
    if ('' !== $email) {
        $h .= '<li><a href="mailto:' . e($email) . '">' . icon('envelope-simple') . '<span>' . e($email) . '</span></a></li>';
    }

    return $h . '</ul>';
}

/** Impressum, Datenschutz, Barrierefreiheit (Website → Recht; Klxm\Fluid\Legal\Legal) @return list<array{label: string, href: string}> */
function fluid_legal_links(): array
{
    return \Klxm\Fluid\Legal\Legal::links();
}

function fluid_header_cta(): ?array
{
    $label = trim((string) setting('header_cta_label', ''));
    $link = trim((string) setting('header_cta_link', ''));

    return '' !== $label && '' !== $link ? ['label' => $label, 'link' => $link] : null;
}

/** Symbol aus den Phosphor-Sprites (MIT) des Kits */
function icon(?string $name, array $opt = []): string
{
    $name = trim((string) $name);
    if ('' === $name) {
        return '';
    }
    static $map = null, $core = null;
    $map ??= json_decode((string) file_get_contents(FluidKit::instance()->assetPath('icons/icons-map.json')), true) ?: [];
    $core ??= (string) file_get_contents(FluidKit::instance()->assetPath('icons/core.svg'));
    $sprite = str_contains($core, 'id="i-' . $name . '"') ? 'core' : ($map[$name] ?? 'icons');
    $cls = trim('ico ' . ($opt['class'] ?? ''));
    $label = trim((string) ($opt['label'] ?? ''));
    $a11y = '' !== $label ? ' role="img" aria-label="' . e($label) . '"' : ' aria-hidden="true"';

    return '<svg class="' . e($cls) . '"' . $a11y . ' focusable="false" width="1em" height="1em" fill="currentColor">'
        . '<use href="' . e(FluidKit::instance()->assetUrl('icons/' . $sprite . '.svg')) . '#i-' . e($name) . '"/></svg>';
}

/** Medium aus dem Medienpool (Dateiname) */
function media(?string $name): ?array
{
    $m = $name ? Media::get($name) : null;

    return $m ? ['file' => $m->fileName, 'mime' => (string) $m->type, 'title' => $m->title, 'width' => $m->width, 'height' => $m->height, 'media' => $m] : null;
}

function media_url(array|string|null $m, ?int $width = null): string
{
    $name = is_array($m) ? (string) ($m['file'] ?? '') : (string) $m;
    $media = '' !== $name ? Media::get($name) : null;

    return $media ? $media->getUrl() : '';
}

/**
 * Bild aus dem Medienpool. Mit dem Addon image_editor: bearbeitete Fassung, responsiv (srcset, AVIF/WebP über den Media Manager)
 * und im Bearbeiten-Modus der Knopf „Bild bearbeiten“ (data-ie-file). Alt-Text nach der Regel von klxm_meta: Feld
 * „Alternativtext“ des Medienpools (je Sprache), dekorativ = alt="", sonst Titel; $opt['alt'] überschreibt.
 */
function img(?string $name, string $sizes = '100vw', array $opt = []): string
{
    $m = $name ? Media::get($name) : null;
    if (!$m) {
        return '';
    }
    $alt = \Klxm\Meta\Alt::attribute($m, $opt['alt'] ?? null);
    // ohne Angabe: erstes Bild im ersten Block der Seite sofort und mit Vorrang (Einstieg), sonst lazy
    $eager = \array_key_exists('eager', $opt) ? !empty($opt['eager']) : \Klxm\Fluid\Block::firstImage();
    $editor = class_exists(\Klxm\ImageEditor\ImageEditor::class) ? \Klxm\ImageEditor\ImageEditor::class : null;
    $editable = $editor && null === $editor::editable($m);
    // Bildformat (16:9 …): Ausschnitt je Format aus image_editor – eigener Ausschnitt oder automatisch um den Fokuspunkt.
    // Nicht bei „Einpassen“ (contain): dann zeigt der Rahmen das ganze Bild.
    $ratio = (string) ($opt['ratio'] ?? '');
    if (!$editable || !\defined($editor . '::RATIOS') || !\in_array($ratio, $editor::RATIOS, true) || str_contains($editor::imgStyle($m->fileName), 'contain')) {
        $ratio = '';
    }
    if ($editable) {
        [$w, $h] = $editor::size($m->fileName, '' !== $ratio ? $ratio : null);
        $src = $editor::url($m->fileName, 1280, '' !== $ratio ? $ratio : null);
        $srcset = ' srcset="' . e($editor::srcset($m->fileName, '' !== $ratio ? $ratio : null)) . '" sizes="' . e($sizes) . '"';
    } else {
        [$w, $h] = [(int) $m->width, (int) $m->height];
        $src = $m->getUrl();
        $srcset = '';
    }
    $wh = $w && $h ? ' width="' . $w . '" height="' . $h . '"' : '';
    $ie = $editable && is_editing() ? ' data-ie-file="' . e($m->fileName) . '"' . ('' !== $ratio ? ' data-ie-ratio="' . e($ratio) . '"' : '') : '';
    // Fokuspunkt und Einpassen (image_editor): object-position / object-fit am Bild
    $style = $editable ? $editor::imgStyle($m->fileName) : '';

    return '<img src="' . e($src) . '"' . $srcset . ' alt="' . e($alt) . '"' . $wh . ($eager ? ' fetchpriority="high"' : ' loading="lazy"') . ' decoding="async"'
        . \Klxm\Fluid\InlineStyle::attr($style, (string) ($opt['class'] ?? '')) . $ie . '>';   // Fokuspunkt als Klasse (CSP: keine style-Attribute)
}

/** Rich Text sicher ausgeben (Klassenvertrag der Formatierungsleiste, RichText) */
function rich(?string $html): string
{
    return \Klxm\Fluid\RichText::html($html);
}

/** Link-Wert eines Feldes → Adresse: redaxo://12, page:12, 12, https://…, #anker, tel:, mailto: */
function link_href(?string $link): string
{
    $l = trim((string) $link);
    if ('' === $l) {
        return '#';
    }
    // Sonderwerte aus „Website“: phone, email
    if ('phone' === $l) {
        return fluid_phone_href() ?? '#';
    }
    if ('email' === $l) {
        return '' !== fluid_email() ? 'mailto:' . fluid_email() : '#';
    }
    if (preg_match('~^(?:redaxo://|page:)?(\d+)(#[\w-]+)?$~', $l, $m)) {
        $art = Article::get((int) $m[1]);

        return ($art ? $art->getUrl() : '#') . ($m[2] ?? '');
    }

    return $l;
}

function is_external(string $href): bool
{
    return (bool) preg_match('~^https?://~i', $href) && !str_contains($href, (string) ($_SERVER['HTTP_HOST'] ?? '#'));
}

function ext_attrs(string $href): string
{
    return is_external($href) ? ' target="_blank" rel="noopener"' : '';
}

// ------------------------------------------------------------ aus kits/fluid/functions.php (KLXM Studio)

function fluid_link(?string $link): string
{
    return link_href($link);
}

function fluid_link_attrs(?string $link): string
{
    $href = fluid_link($link);

    return 'href="' . e($href) . '"' . ext_attrs($href);
}

function fluid_ext_note(string $href): string
{
    return is_external($href) ? '<span class="sr-only"> ' . e(lt('(öffnet in neuem Tab)')) . '</span>' : '';
}

function fluid_buttons(Block $b, string $class = '', array $data = [], string $prefix = ''): string
{
    $d = $data ?: $b->data;
    $h = '';
    foreach (['button' => 'btn--primary', 'button2' => 'btn--secondary'] as $k => $style) {
        $label = trim((string) ($d[$k . '_label'] ?? ''));
        $link = trim((string) ($d[$k . '_link'] ?? ''));
        if ('' === $label || ('' === $link && !is_editing())) {
            continue;
        }
        $href = fluid_link($link);
        $h .= '<a class="btn ' . $style . '" ' . fluid_link_attrs($link) . '><span' . $b->edit($prefix . $k . '_label') . '>' . e($label) . '</span>' . fluid_ext_note($href) . '</a>';
    }

    return '' !== $h ? '<div class="btn-row' . ('' !== $class ? ' ' . e($class) : '') . '">' . $h . '</div>' : '';
}

function fluid_lines(?string $text): array
{
    return array_values(array_filter(array_map('trim', preg_split('~\R~', (string) $text)), fn ($l) => '' !== $l));
}

function fluid_checks(array $lines, string $class = ''): string
{
    if (!$lines) {
        return '';
    }
    $h = '<ul class="checks' . ('' !== $class ? ' ' . e($class) : '') . '" role="list">';
    foreach ($lines as $l) {
        $h .= '<li>' . icon('check-circle', ['class' => 'checks__ico']) . '<span>' . e($l) . '</span></li>';
    }

    return $h . '</ul>';
}

function fluid_head(Block $b, string $class = '', string $tag = 'h2'): string
{
    $d = $b->data;
    $eyebrow = trim((string) ($d['eyebrow'] ?? ''));
    $title = trim((string) ($d['title'] ?? ''));
    $intro = trim((string) ($d['intro'] ?? ''));
    if ('' === $eyebrow && '' === $title && '' === $intro && !is_editing()) {
        return '';
    }
    $h = '<header class="sec-head' . ('' !== $class ? ' ' . e($class) : '') . '">';
    if ('' !== $eyebrow || is_editing()) {
        $h .= '<p class="eyebrow"' . $b->edit('eyebrow') . '>' . e($eyebrow) . '</p>';
    }
    if ('' !== $title || is_editing()) {
        $h .= '<' . $tag . ' id="' . e($b->titleId()) . '" class="h2"' . $b->edit('title') . '>' . e($title) . '</' . $tag . '>';
    }
    if ('' !== $intro || (is_editing() && array_key_exists('intro', $d))) {
        $h .= '<p class="lead"' . $b->edit('intro', 'lines') . '>' . nl2br(e($intro), false) . '</p>';
    }

    return $h . '</header>';
}

function fluid_htag(array $d): string
{
    return '' !== trim((string) ($d['title'] ?? '')) ? 'h3' : 'h2';
}

function fluid_min(array $d, string $key = 'size'): string
{
    return 'min-' . (in_array($d[$key] ?? '', ['s', 'm', 'l'], true) ? $d[$key] : 'm');
}

function fluid_image(?string $id, string $sizes, string $ratio, string $class = '', array $opt = []): string
{
    // Einpassen „Originalformat“ (image_editor): Rahmen ohne festes Seitenverhältnis
    if ($id && class_exists(\Klxm\ImageEditor\ImageEditor::class) && 'original' === (\Klxm\ImageEditor\ImageEditor::stored($id)['fit'] ?? '')) {
        $ratio = '';
    }
    $pic = img($id, $sizes, ('' !== $ratio ? ['ratio' => $ratio] : []) + $opt);
    $cls = 'frame' . ('' !== $ratio ? ' r-' . str_replace(':', '-', $ratio) : '') . ('' !== $class ? ' ' . $class : '');
    if ('' !== $pic) {
        return '<div class="' . e($cls) . '">' . $pic . '</div>';
    }

    return is_editing() ? '<div class="' . e($cls) . ' frame--empty"><span>' . e('Bild' . ('' !== $ratio ? ' · ' . $ratio : '')) . '</span></div>' : '';
}

function fluid_title(string $text): string
{
    // Bearbeiten: Hervorhebung sichtbar, Sternchen dezent – beim Auslesen (innerText) bleibt „*Wort*“ erhalten
    if (is_editing()) {
        return preg_replace('~\*([^*]+)\*~u', '<span class="hl-mark">*</span><em class="hl">$1</em><span class="hl-mark">*</span>', e($text)) ?? e($text);
    }

    return preg_replace('~\*([^*]+)\*~u', '<em class="hl">$1</em>', e($text)) ?? e($text);
}

function fluid_toc(string $html, string $prefix): array
{
    $toc = [];
    $used = [];
    $html = preg_replace_callback('~<(h[34])([^>]*)>(.*?)</\1>~is', static function ($m) use (&$toc, &$used, $prefix) {
        $label = trim(html_entity_decode(strip_tags($m[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ('' === $label) {
            return $m[0];
        }
        if (preg_match('~\sid="([^"]+)"~', $m[2], $idm)) {
            $id = $idm[1];
            $attrs = $m[2];
        } else {
            $base = $prefix . '-' . (trim((string) preg_replace('~[^a-z0-9]+~', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $label) ?: '')), '-') ?: 'abschnitt');
            $id = $base;
            for ($i = 2; isset($used[$id]); ++$i) {
                $id = $base . '-' . $i;
            }
            $attrs = $m[2] . ' id="' . e($id) . '"';
        }
        $used[$id] = true;
        $toc[] = ['id' => $id, 'label' => $label, 'level' => 'h3' === strtolower($m[1]) ? 2 : 3];

        return '<' . $m[1] . $attrs . '>' . $m[3] . '</' . $m[1] . '>';
    }, $html) ?? $html;

    return [$html, $toc];
}

function fluid_reading_time(string $html): int
{
    return max(1, (int) round(str_word_count(strip_tags($html), 0, 'äöüÄÖÜß') / 220));
}

/** „Wert: Bezeichnung“ je Zeile (Hero „Fließende Skala“) */
function fluid_pairs(mixed $s): array
{
    return array_map(static function (string $l): array {
        [$a, $b] = str_contains($l, ':') ? array_map('trim', explode(':', $l, 2)) : ['', $l];

        return ['label' => $a, 'value' => $b];
    }, fluid_lines((string) $s));
}

/**
 * Vorher/Nachher (Block „Vorher/Nachher“, $d mit before, after, label_before, label_after): zwei Bilder, darüber ein
 * Regler (input range, js/compare.js setzt .is-ready und --hx-pos). Ohne Skript stehen die Bilder nebeneinander.
 * Leer, solange eines der Bilder fehlt.
 */
function fluid_compare(array $d, string $sizes, string $ratio, string $class): string
{
    $before = img((string) ($d['before'] ?? ''), $sizes, ['ratio' => $ratio]);
    $after = img((string) ($d['after'] ?? ''), $sizes, ['ratio' => $ratio]);
    if ('' === $before || '' === $after) {
        return '';
    }
    $lb = trim((string) ($d['label_before'] ?? '')) ?: lt('Vorher');
    $la = trim((string) ($d['label_after'] ?? '')) ?: lt('Nachher');

    return '<div class="' . e($class) . '" data-compare>'
        . '<figure class="' . e($class) . '__pane ' . e($class) . '__pane--before">' . $before . '<span class="' . e($class) . '__tag" aria-hidden="true">' . e($lb) . '</span></figure>'
        . '<figure class="' . e($class) . '__pane ' . e($class) . '__pane--after">' . $after . '<span class="' . e($class) . '__tag" aria-hidden="true">' . e($la) . '</span></figure>'
        . '<input class="' . e($class) . '__range" type="range" min="0" max="100" value="50" hidden aria-label="' . e(lt('Trennlinie verschieben: links {a}, rechts {b}', ['a' => $lb, 'b' => $la])) . '">'
        . '</div>';
}

/**
 * Hinweis nur beim Bearbeiten: Bild ohne Alternativtext und nicht als dekorativ markiert (klxm_meta). Für Bilder mit
 * Aussage (z. B. Vorher/Nachher) – Besucher sehen nichts davon.
 */
function fluid_alt_hint(?string $file): string
{
    $file = trim((string) $file);
    if (!is_editing() || '' === $file || !class_exists(\Klxm\Meta\Alt::class) || !Media::get($file)
        || null !== \Klxm\Meta\Alt::of($file) || \Klxm\Meta\Alt::isDecorative($file)) {
        return '';
    }

    return '<p class="empty-hint">' . e('Alternativtext fehlt für „' . $file . '“ – im Medienpool beschreiben, was zu sehen ist.') . '</p>';
}

/** Hinweis nur beim Bearbeiten: eigene Videodatei ohne Untertitel (Videos mit Sprache brauchen sie) */
function fluid_caption_hint(array $d): string
{
    if (!is_editing() || '' === trim((string) ($d['video_file'] ?? ''))) {
        return '';
    }
    foreach ((array) ($d['tracks'] ?? []) as $t) {
        if ('' !== trim((string) ($t['file'] ?? '')) && 'chapters' !== ($t['kind'] ?? 'subtitles')) {
            return '';
        }
    }

    return '<p class="empty-hint">Ohne Untertitel: Wird im Video gesprochen, bitte unter „Untertitel und Kapitel“ eine .vtt- oder .srt-Datei ergänzen.</p>';
}

/**
 * Filter für Besucher („Filter nach Kategorie“ bei Karten und Datenliste): aus den Kategorien je Eintrag (Komma trennt
 * mehrere) eine Leiste mit Optionsfeldern – gefiltert wird per CSS (:has(), Kategorien als Nummern 1–12, Markup der
 * Komponente filter von NativeCSS), js/filter.js ergänzt die Trefferzahl als Ansage. Leer, wenn weniger als zwei
 * Kategorien vorkommen oder beim Bearbeiten.
 *
 * @param list<string> $cats Kategorien je Eintrag (Index wie die Einträge)
 * @return array{bar: string, item: list<string>} Leiste und data-category je Eintrag
 */
function fluid_filter(\Klxm\Fluid\Block $b, array $cats): array
{
    $names = [];
    $item = [];
    foreach ($cats as $i => $raw) {
        $nums = [];
        foreach (array_filter(array_map('trim', explode(',', (string) $raw))) as $c) {
            $key = mb_strtolower($c);
            if (!isset($names[$key]) && count($names) < 12) {
                $names[$key] = ['n' => count($names) + 1, 'label' => $c];
            }
            if (isset($names[$key])) {
                $nums[] = $names[$key]['n'];
            }
        }
        $item[$i] = implode(' ', array_unique($nums));
    }
    if (count($names) < 2 || is_editing()) {
        return ['bar' => '', 'item' => []];
    }
    \Klxm\Fluid\Kit::active()->markUsed('@filter');
    $id = $b->domId() . '-f';
    $bar = '<fieldset class="ncss-filter-bar"><legend>' . e(lt('Filtern nach Kategorie')) . '</legend>'
        . '<input type="radio" name="' . e($id) . '" id="' . e($id) . '-0" value="" checked><label for="' . e($id) . '-0">' . e(lt('Alle')) . '</label>';
    foreach ($names as $c) {
        $bar .= '<input type="radio" name="' . e($id) . '" id="' . e($id) . '-' . $c['n'] . '" value="' . $c['n'] . '"><label for="' . e($id) . '-' . $c['n'] . '">' . e($c['label']) . '</label>';
    }

    return ['bar' => $bar . '</fieldset>', 'item' => $item];
}

/** Text ohne *Betonung*-Sternchen (Attribute, Screenreader) */
function strip_emphasis(string $text): string
{
    return (string) preg_replace('~\*([^*]+)\*~u', '$1', $text);
}

/** Datenschutzerklärung (Website → Recht, sonst Seite „Datenschutz“ der obersten Ebene) – oder '' */
function privacy_url(): string
{
    return \Klxm\Fluid\Legal\Legal::url('privacy');
}

/**
 * Video einbetten (aus KLXM Studio, Kern-Fragment video-embed – vereinfacht): YouTube/Vimeo mit Zwei-Klick-Lösung
 * (Player lädt erst nach Klick, js/embed.js; „künftig direkt laden“ im Browser), MP4 aus dem Medienpool als <video>.
 * Markup und Klassen (.vembed …) wie in Studio, damit css/b-video.css greift.
 */
/**
 * Video: eigene Datei (mit Vorschaubild, Untertiteln, Transkript) oder YouTube/Vimeo mit Zwei-Klick-Lösung.
 * $tracks: [['file' => 'film.de.vtt', 'lang' => 'de', 'kind' => 'subtitles', 'label' => ''], …]
 */
function fluid_video_embed(string $url, string $file, string $poster, string $ratio, string $label, array $tracks = [], bool $ccOn = false, bool $transcript = false): string
{
    $ratio = in_array($ratio, ['16-9', '4-3', '21-9'], true) ? $ratio : '16-9';
    $mp4 = '' !== $file ? Media::get($file) : null;
    if ($mp4 && str_starts_with((string) $mp4->type, 'video/')) {
        return fluid_video_file($mp4, $poster, $ratio, $label, $tracks, $ccOn, $transcript);
    }
    $u = trim($url);
    $v = null;
    if (preg_match('~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/))([\w-]{11})~', $u, $m)) {
        $v = ['provider' => 'youtube', 'label' => 'YouTube', 'company' => 'Google Ireland Ltd.',
            'src' => 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?autoplay=1&rel=0', 'watch' => 'https://www.youtube.com/watch?v=' . $m[1]];
    } elseif (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $u, $m)) {
        $v = ['provider' => 'vimeo', 'label' => 'Vimeo', 'company' => 'Vimeo.com Inc.',
            'src' => 'https://player.vimeo.com/video/' . $m[1] . '?autoplay=1&dnt=1', 'watch' => 'https://vimeo.com/' . $m[1]];
    }
    if (!$v) {
        return is_editing() ? '<div class="vembed vembed--empty r-' . e($ratio) . '"><p>YouTube-/Vimeo-Link oder MP4-Datei in der Seitenleiste eintragen.</p></div>' : '';
    }
    $title = '' !== $label ? $label : lt('Video');
    $privacy = privacy_url();
    $text = e(lt('Beim Abspielen lädt {provider} ({company}) das Video. Dabei werden Daten wie Ihre IP-Adresse an {provider} übertragen.', ['provider' => $v['label'], 'company' => $v['company']]))
        . ('' !== $privacy ? ' <a href="' . e($privacy) . '">' . e(lt('Datenschutz')) . '</a>' : '');
    $posterImg = '' !== $poster ? img($poster, '(min-width: 1080px) 1200px, 100vw', ['alt' => '', 'class' => 'vembed__poster']) : '';

    return '<div class="vembed r-' . e($ratio) . '" data-embed="' . e($v['provider']) . '" data-label="' . e($v['label']) . '" data-src="' . e($v['src']) . '" data-title="' . e($title) . '"'
        . ' data-t-revoke="' . e(lt('Freigabe widerrufen')) . '" data-t-direct="' . e(lt('{provider}-Videos werden auf dieser Website direkt geladen.', ['provider' => $v['label']])) . '">'
        . $posterImg
        . '<div class="vembed__gate"><div class="vembed__info"><p class="vembed__text">' . $text . '</p>'
        . '<p class="vembed__row"><label class="vembed__remember"><input type="checkbox" data-embed-remember> ' . e(lt('{provider}-Videos künftig direkt laden', ['provider' => $v['label']])) . '</label>'
        . '<a class="vembed__ext" href="' . e($v['watch']) . '" target="_blank" rel="noopener">' . e(lt('Auf {provider} ansehen', ['provider' => $v['label']])) . '<span class="sr-only"> ' . e(lt('(öffnet in neuem Tab)')) . '</span></a></p></div>'
        . '<button type="button" class="vembed__play" data-embed-play><span class="vembed__icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22"><path d="M8 5.5v13l11-6.5z" fill="currentColor"/></svg></span>'
        . '<span>' . e(lt('{provider}-Video laden', ['provider' => $v['label']])) . '<span class="sr-only">: ' . e($title) . '</span></span></button></div></div>';
}

/** Eigene Videodatei: Vorschaubild (gewählt oder Standbild per ffmpeg), Untertitel-Spuren, Transkript */
function fluid_video_file(Media $video, string $poster, string $ratio, string $label, array $tracks, bool $ccOn, bool $transcript): string
{
    $mm = \Redaxo\Core\MediaManager\MediaManager::class;
    // AddOn video_editor (optional): dort gewähltes Poster und Web-Fassungen (WebM, 480p/720p/1080p je nach Bildschirm) – ohne es das Original
    $ve = class_exists(\Klxm\VideoEditor\VideoEditor::class);
    $p = '' !== $poster ? Media::get($poster) : ($ve ? \Klxm\VideoEditor\VideoEditor::poster($video->fileName) : null);
    $posterUrl = $p ? $mm::getUrl('fluid_poster', $p) : (\Klxm\Fluid\Media\Ffmpeg::available() ? $mm::getUrl('fluid_poster', $video) : '');
    $sources = $ve ? \Klxm\VideoEditor\VideoEditor::sources($video->fileName) : [];
    $sources = $sources ?: [['url' => $video->getUrl(), 'type' => (string) $video->type]];
    // Untertitel aus dem Video-Editor, wenn im Block keine gewählt sind
    if ($ve && [] === $tracks) {
        $tracks = array_map(static fn (array $t) => ['file' => $t['file'], 'lang' => $t['lang'], 'kind' => $t['kind'], 'label' => $t['label']],
            \Klxm\VideoEditor\VideoEditor::tracks($video->fileName));
    }
    $lang = \Redaxo\Core\Language\Language::getCurrent()->code;
    $out = '';
    $first = null;
    $on = false;
    foreach ($tracks as $t) {
        $f = trim((string) ($t['file'] ?? ''));
        $code = strtolower(trim((string) ($t['lang'] ?? '')));
        $kind = in_array($t['kind'] ?? '', ['subtitles', 'captions', 'chapters'], true) ? $t['kind'] : 'subtitles';
        if ('' === $f || !preg_match('~^[a-z]{2,3}(-[a-z0-9]{2,8})?$~', $code) || !Media::get($f)) {
            continue;
        }
        $default = $ccOn && !$on && 'chapters' !== $kind && $code === $lang;
        $on = $on || $default;
        if ('chapters' !== $kind && (null === $first || $code === $lang && $first['lang'] !== $lang)) {
            $first = ['file' => $f, 'lang' => $code];
        }
        $out .= '<track kind="' . e($kind) . '" src="' . e(\Klxm\Fluid\Media\Tracks::url($f)) . '" srclang="' . e($code) . '" label="'
            . e(\Klxm\Fluid\Media\Tracks::label($kind, $code, (string) ($t['label'] ?? ''))) . '"' . ($default ? ' default' : '') . '>';
    }
    // Untertitel von derselben Adresse (crossorigin nötig, sobald Medien von einer anderen Domain kommen)
    $h = '<div class="vembed r-' . e($ratio) . '"><video controls preload="none" playsinline' . ('' !== $posterUrl ? ' poster="' . e($posterUrl) . '"' : '')
        . ('' !== $label ? ' aria-label="' . e($label) . '"' : '') . '>'
        . implode('', array_map(static fn (array $src) => '<source src="' . e($src['url']) . '" type="' . e($src['type']) . '"'
            . (($src['media'] ?? '') !== '' ? ' media="' . e($src['media']) . '"' : '') . '>', $sources)) . $out . '</video></div>';
    if ($transcript && $first) {
        $paras = \Klxm\Fluid\Media\Tracks::transcript(\Klxm\Fluid\Media\Tracks::cuesOf($first['file']));
        if ($paras) {
            $h .= '<details class="vtranscript"><summary>' . e(lt('Text zum Video')) . '</summary><div class="vtranscript__body" lang="' . e($first['lang']) . '">'
                . implode('', array_map(static fn ($t) => '<p>' . e($t) . '</p>', $paras)) . '</div></details>';
        }
    }

    return $h;
}

/**
 * Audiodateien des Blocks „Video & Audio“ (Darstellung Audio) für die Ausgabe: nur vorhandene Audiodateien, Titel aus dem
 * Block oder dem Medienpool, Kapitel als geprüftes WebVTT (/fluid-track/…).
 *
 * @return list<array{url: string, mime: string, title: string, artist: string, chapters: string}>
 */
function fluid_audio_tracks(array $items): array
{
    $out = [];
    foreach ($items as $it) {
        $m = media((string) ($it['file'] ?? ''));
        if (!$m || !str_starts_with($m['mime'], 'audio/')) {
            continue;
        }
        $ch = trim((string) ($it['chapters'] ?? ''));
        $title = trim((string) ($it['title'] ?? ''));
        $out[] = ['url' => media_url($m), 'mime' => $m['mime'], 'title' => '' !== $title ? $title : ((string) $m['title'] ?: (string) $m['file']),
            'artist' => trim((string) ($it['artist'] ?? '')), 'chapters' => '' !== $ch && Media::get($ch) ? \Klxm\Fluid\Media\Tracks::url($ch) : ''];
    }

    return $out;
}

/** Datum in der Sprache der Seite, z. B. „13. Oktober 2026“ / „October 13, 2026“ */
function fluid_date(\DateTimeInterface $d): string
{
    $code = \Redaxo\Core\Language\Language::getCurrent()->code;
    if (class_exists(\IntlDateFormatter::class)) {
        $f = new \IntlDateFormatter($code, \IntlDateFormatter::LONG, \IntlDateFormatter::NONE);
        $s = $f->format($d);
        if (is_string($s)) {
            return $s;
        }
    }

    return $d->format('de' === substr($code, 0, 2) ? 'd.m.Y' : 'Y-m-d');
}

/** Zeile „Datum · Autor“ eines Beitrags */
function fluid_post_meta(\Klxm\Fluid\Blog\Post $p, bool $author = true): string
{
    $h = '<time datetime="' . e($p->date->format('Y-m-d')) . '">' . e(fluid_date($p->date)) . '</time>';
    if ($author && '' !== $p->author) {
        $h .= ' · <span class="post-author">' . e($p->author) . '</span>';
    }

    return $h;
}

/** Kopf einer Beitragsseite (Template): Zurück zur Übersicht, Titel, Datum, Autor, Schlagworte, Bild */
function fluid_post_header(Article $a): string
{
    $blog = \Klxm\Fluid\Blog\Blog::blogOf($a);
    $p = $blog ? \Klxm\Fluid\Blog\Post::get($a->id, $a->languageId) : null;
    if (!$p) {
        return '';
    }
    $over = Article::get($blog, $a->languageId);
    $tags = '';
    foreach ($p->tags as $t) {
        $tags .= '<li><a href="' . e(($over?->getUrl() ?? '') . '?tag=' . rawurlencode(\Klxm\Fluid\Blog\Blog::tagSlug($t))) . '">' . e($t) . '</a></li>';
    }
    $img = '' !== $p->image ? '<div class="post-head__img ratio-16-9">' . img($p->image, '(min-width: 1280px) 1200px, 100vw', ['eager' => true]) . '</div>' : '';

    return '<header class="sec post-head"><div class="wrap">'
        . ($over ? '<p class="post-head__back"><a href="' . e($over->getUrl()) . '">← ' . e($over->name) . '</a></p>' : '')
        . '<h1 class="h1 post-head__title">' . e($a->name) . '</h1>'
        . '<p class="post-head__meta">' . fluid_post_meta($p) . '</p>'
        . ('' !== $tags ? '<ul class="post-tags" role="list">' . $tags . '</ul>' : '')
        . $img . '</div></header>';
}

/** Fuß einer Beitragsseite (Template): älterer / neuerer Beitrag */
function fluid_post_footer(Article $a): string
{
    [$older, $newer] = \Klxm\Fluid\Blog\Blog::neighbors($a);
    if (!$older && !$newer) {
        return '';
    }
    $l = static fn (?\Klxm\Fluid\Blog\Post $p, string $label, string $cls) => $p
        ? '<a class="post-nav__link ' . $cls . '" href="' . e($p->url) . '"><span class="post-nav__label">' . e(lt($label)) . '</span><span class="post-nav__title">' . e($p->article->name) . '</span></a>'
        : '<span></span>';

    return '<nav class="sec post-nav" aria-label="' . e(lt('Weitere Beiträge')) . '"><div class="wrap post-nav__grid">'
        . $l($older, 'Älterer Beitrag', 'post-nav__link--prev') . $l($newer, 'Neuerer Beitrag', 'post-nav__link--next') . '</div></nav>';
}

/**
 * Hauptmenü im Seitenblatt (Menü-Schaltfläche) – wie in KLXM Studio (Modus „overview“): Unterseiten als Akkordeon
 * (<details name> – je Ebene nur eins offen, ohne JavaScript), erster Eintrag „Übersicht: …“, aktiver Zweig offen.
 *
 * @param list<array{label: string, href: string, current: bool, active: bool, children: list<array<string, mixed>>}> $menu
 */
function fluid_nav_sheet(array $menu): string
{
    $cur = fn(array $m) => !empty($m['current']) ? ' aria-current="page"' : '';
    $chev = icon('caret-down', ['class' => 'mnav__chev']);
    $level = function (array $items, int $depth) use (&$level, $cur, $chev): string {
        $h = '<ul class="mnav__list mnav__list--' . $depth . '" role="list">';
        foreach ($items as $m) {
            if (empty($m['children'])) {
                $h .= '<li><a class="mnav__link" href="' . e($m['href']) . '"' . $cur($m) . '>' . e($m['label']) . '</a></li>';
                continue;
            }
            $h .= '<li><details class="mnav__acc" name="mnav-' . $depth . '"' . (!empty($m['active']) ? ' open' : '') . '>'
                . '<summary class="mnav__link"><span>' . e($m['label']) . '</span>' . $chev . '</summary>'
                . '<div class="mnav__sub"><a class="mnav__link mnav__link--parent" href="' . e($m['href']) . '"' . $cur($m) . '>' . e(lt('Übersicht: {name}', ['name' => $m['label']])) . '</a>'
                . $level($m['children'], $depth + 1) . '</div></details></li>';
        }

        return $h . '</ul>';
    };

    return $level($menu, 1);
}

/**
 * Bildergalerie und Slider (Kit-Blöcke gallery/slider, aus KLXM Studio – Core\MediaBlocks): Link zur großen Fassung für
 * die Lightbox (ohne JavaScript ein gewöhnlicher Link), Klasse für „Zeilen“ (Seitenverhältnis × 10) und Dialog/Skript.
 */
function fluid_lightbox_link(string $file, string $caption): string
{
    $m = Media::get($file);
    if (!$m) {
        return '';
    }
    $editor = class_exists(\Klxm\ImageEditor\ImageEditor::class) ? \Klxm\ImageEditor\ImageEditor::class : null;
    $editable = $editor && null === $editor::editable($m);

    return ' href="' . e($editable ? $editor::url($m->fileName, 1920) : $m->getUrl()) . '"'
        . ($editable ? ' data-srcset="' . e($editor::srcset($m->fileName)) . '"' : '')
        . ('' !== $caption ? ' data-caption="' . e($caption) . '"' : '');
}

function fluid_aspect_class(string $file): string
{
    $m = Media::get($file);
    $w = (int) ($m?->width ?? 0);
    $h = (int) ($m?->height ?? 0);

    return 'ar-' . ($w && $h ? max(5, min(24, (int) round($w / $h * 10))) : 13);
}

/** Lightbox-Dialog einmal je Seite (js/media.js befüllt ihn) – nicht im Bearbeiten-Modus */
function fluid_lightbox(): string
{
    static $done = false;
    if ($done || is_editing()) {
        return '';
    }
    $done = true;
    $svg = static fn (string $p) => '<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2"><path d="' . $p . '"/></svg>';

    return '<dialog class="cms-lb" aria-label="' . e(lt('Bildansicht')) . '" data-count="' . e(lt('Bild {n} von {total}')) . '">'
        . '<button type="button" class="cms-lb__btn cms-lb__close" aria-label="' . e(lt('Schließen')) . '">' . $svg('M6 6l12 12M18 6 6 18') . '</button>'
        . '<figure class="cms-lb__fig"><img class="cms-lb__img" alt="" sizes="100vw"><figcaption class="cms-lb__cap" aria-live="polite"><span></span><span class="cms-lb__count"></span></figcaption></figure>'
        . '<button type="button" class="cms-lb__btn cms-lb__prev" data-d="-1" aria-label="' . e(lt('Vorheriges Bild')) . '">' . $svg('M15 5l-7 7 7 7') . '</button>'
        . '<button type="button" class="cms-lb__btn cms-lb__next" data-d="1" aria-label="' . e(lt('Nächstes Bild')) . '">' . $svg('M9 5l7 7-7 7') . '</button></dialog>';
}
