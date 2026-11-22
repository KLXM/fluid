<?php

namespace Klxm\Fluid\Data;

use DateTimeImmutable;
use DateTimeZone;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Language\Language;
use Throwable;

use function count;
use function html_entity_decode;
use function implode;
use function preg_match;
use function preg_replace;
use function str_replace;
use function strip_tags;
use function strlen;
use function substr;
use function trim;

/**
 * Termine: „In den Kalender“ als .ics je Eintrag einer Datenliste mit Detailansicht (Vorbild KLXM Studio, Termin-Aktionen).
 *
 * Gilt für YForm-Tabellen mit einem Datumsfeld (Date bzw. DateTime von YForm). Die Datenliste schaltet es ein (Feld
 * „In den Kalender anbieten“); dann zeigt die Detailansicht den Link /fluid-ics/{seite}-{eintrag}.ics (?lang=… bei mehreren
 * Sprachen). Der Endpunkt prüft dieselben Bedingungen wie die Detailansicht (Filter der Datenliste, Sprache) und liefert
 * genau einen VEVENT:
 *  - Beginn: erstes Datumsfeld (DateTime → mit Uhrzeit in UTC, Date → ganztägig), Ende: Datumsfeld mit end/bis/until im Namen,
 *    sonst ganztägig ein Tag bzw. eine Stunde.
 *  - Titel: Titelfeld der Datenliste, Beschreibung: Textfeld (ohne HTML) und Adresse der Detailseite, Ort: Textfeld mit
 *    place/ort/location/venue im Namen.
 *
 * Serien (wiederkehrende Termine mit einzelnen Vorkommen) kennt fluid nicht – jeder Eintrag ist ein Termin.
 */
final class Ics
{
    public const string PATH = 'fluid-ics';

    /** Adresse der .ics-Datei eines Eintrags */
    public static function url(int $articleId, int $entryId, ?int $languageId = null): string
    {
        $languageId ??= Language::getCurrentId();

        return '/' . self::PATH . '/' . $articleId . '-' . $entryId . '.ics' . (count(Language::getAll()) > 1 ? '?lang=' . $languageId : '');
    }

    /** Erstes Datumsfeld einer Tabelle (Date oder DateTime) – null, wenn es keins gibt */
    public static function dateField(object $table): ?object
    {
        foreach (YFormBridge::detailFields($table) as $f) {
            if ($f instanceof \Yakamara\YForm\Field\Date && !preg_match('~(end|bis|until)~i', $f->name)) {
                return $f;
            }
        }

        return null;
    }

    /** @internal Endpunkt /fluid-ics/{seite}-{eintrag}.ics */
    #[\Klxm\Fluid\Routing\Attribute\AsEndpoint('/' . self::PATH . '/*')]
    public static function serve(string $path, string $rest): void
    {
        if (!YFormBridge::available() || !preg_match('~^(\d+)-(\d+)\.ics$~', $rest, $m)) {
            return;
        }
        $lang = Request::get('lang', 'int', 0) ?: Language::getCurrentId();
        $ics = self::build((int) $m[1], (int) $m[2], $lang);
        if (null === $ics) {
            return;   // weiter wie eine unbekannte Adresse (404)
        }
        \Klxm\Fluid\Routing\Router::respond($ics, 'text/calendar; charset=utf-8', ['Content-Disposition: attachment; filename="termin-' . (int) $m[2] . '.ics"', 'Cache-Control: no-cache', 'X-Content-Type-Options: nosniff']);
    }

    /** Kalenderdatei eines Eintrags oder null (keine Datenliste mit Kalender, Eintrag nicht sichtbar, kein Datum) */
    public static function build(int $articleId, int $entryId, int $languageId): ?string
    {
        $cfg = Detail::config($articleId, $languageId);
        if (null === $cfg || empty($cfg['ics'])) {
            return null;
        }
        $t = YFormBridge::table((string) $cfg['table']);
        if (null === $t) {
            return null;
        }
        $row = YFormBridge::row($t, $entryId, YFormBridge::field($t, (string) ($cfg['filter_field'] ?? '')), trim((string) ($cfg['filter_value'] ?? '')), $cfg['_language'] ?? null);
        $dateF = self::dateField($t);
        if (null === $row || null === $dateF) {
            return null;
        }
        $start = YFormBridge::sortValue($row, $dateF);
        if ('' === $start) {
            return null;
        }
        $endF = null;
        $placeF = null;
        foreach (YFormBridge::detailFields($t) as $f) {
            if (null === $endF && $f instanceof \Yakamara\YForm\Field\Date && preg_match('~(end|bis|until)~i', $f->name)) {
                $endF = $f;
            }
            if (null === $placeF && $f instanceof \Yakamara\YForm\Field\Text && preg_match('~(place|ort|location|venue)~i', $f->name)) {
                $placeF = $f;
            }
        }
        $titleF = YFormBridge::field($t, (string) ($cfg['title_field'] ?? ''), [\Yakamara\YForm\Field\Text::class]);
        $textF = YFormBridge::field($t, (string) ($cfg['text_field'] ?? ''), [\Yakamara\YForm\Field\Textarea::class]);
        $title = $titleF ? YFormBridge::text($row, $titleF) : '#' . $entryId;
        $text = $textF ? trim((string) preg_replace('~\s+~u', ' ', html_entity_decode(strip_tags(YFormBridge::text($row, $textF)), ENT_QUOTES | ENT_HTML5, 'UTF-8'))) : '';
        $url = \Klxm\Fluid\Routing\Router::absolute(Detail::url(\Klxm\Fluid\Routing\Router::url($articleId, $languageId), $title, $entryId));

        return self::event([
            'uid' => 'fluid-' . $t->definition->name . '-' . $entryId . '@' . (string) (parse_url($url, PHP_URL_HOST) ?: 'localhost'),
            'start' => $start,
            'end' => $endF ? YFormBridge::sortValue($row, $endF) : '',
            'title' => $title,
            'text' => $text,
            'place' => $placeF ? YFormBridge::text($row, $placeF) : '',
            'url' => $url,
        ]);
    }

    /**
     * Ein VEVENT als Kalenderdatei. $e: uid, start/end (Y-m-d ganztägig oder ISO 8601 mit Uhrzeit), title, text, place, url.
     *
     * @param array{uid: string, start: string, end?: string, title: string, text?: string, place?: string, url?: string} $e
     */
    public static function event(array $e): string
    {
        $allDay = (bool) preg_match('~^\d{4}-\d{2}-\d{2}$~', $e['start']);
        $utc = new DateTimeZone('UTC');
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//KLXM//fluid//DE', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH', 'BEGIN:VEVENT',
            'UID:' . self::text($e['uid']), 'DTSTAMP:' . new DateTimeImmutable('now', $utc)->format('Ymd\THis\Z')];
        try {
            $start = new DateTimeImmutable($e['start']);
            $end = '' !== (string) ($e['end'] ?? '') ? new DateTimeImmutable((string) $e['end']) : null;
        } catch (Throwable) {
            return '';
        }
        if ($allDay) {
            // ganztägig: Ende ist exklusiv (Tag danach)
            $endDay = null !== $end && $end >= $start ? $end->modify('+1 day') : $start->modify('+1 day');
            $lines[] = 'DTSTART;VALUE=DATE:' . $start->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:' . $endDay->format('Ymd');
        } else {
            $endTime = null !== $end && $end > $start ? $end : $start->modify('+1 hour');
            $lines[] = 'DTSTART:' . $start->setTimezone($utc)->format('Ymd\THis\Z');
            $lines[] = 'DTEND:' . $endTime->setTimezone($utc)->format('Ymd\THis\Z');
        }
        $lines[] = 'SUMMARY:' . self::text($e['title']);
        $desc = trim(implode("\n\n", array_filter([(string) ($e['text'] ?? ''), (string) ($e['url'] ?? '')])));
        if ('' !== $desc) {
            $lines[] = 'DESCRIPTION:' . self::text($desc);
        }
        if ('' !== trim((string) ($e['place'] ?? ''))) {
            $lines[] = 'LOCATION:' . self::text((string) $e['place']);
        }
        if ('' !== (string) ($e['url'] ?? '')) {
            $lines[] = 'URL:' . (string) $e['url'];
        }
        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(self::fold(...), $lines)) . "\r\n";
    }

    /** Text nach RFC 5545 maskieren (Backslash, Semikolon, Komma, Zeilenumbruch) */
    private static function text(string $s): string
    {
        return str_replace(["\\", ';', ',', "\r\n", "\n", "\r"], ["\\\\", '\;', '\,', '\n', '\n', '\n'], $s);
    }

    /** Zeilen nach 75 Bytes falten (Folgezeilen: Leerzeichen + 74 Bytes), ohne UTF-8-Zeichen zu teilen */
    private static function fold(string $line): string
    {
        $out = [];
        $max = 75;
        while (strlen($line) > $max) {
            $cut = $max;
            while ($cut > 0 && (\ord($line[$cut]) & 0xC0) === 0x80) {
                --$cut;
            }
            $out[] = substr($line, 0, $cut);
            $line = substr($line, $cut);
            $max = 74;
        }
        $out[] = $line;

        return implode("\r\n ", $out);
    }
}
