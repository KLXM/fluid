<?php

namespace Klxm\Fluid\Data;

use DateTimeImmutable;
use DateTimeZone;
use Klxm\Routes\Attribute\AsRoutesEndpoint;
use Klxm\Routes\Router;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Language\Language;
use Throwable;
use Yakamara\YForm\Field\Date;
use Yakamara\YForm\Field\Text;
use Yakamara\YForm\Field\Textarea;

use function count;
use function html_entity_decode;
use function implode;
use function ord;
use function preg_match;
use function preg_replace;
use function str_replace;
use function strip_tags;
use function strlen;
use function substr;
use function trim;

use const ENT_HTML5;
use const ENT_QUOTES;
use const PHP_URL_HOST;

/**
 * Events: „In den Kalender“ as .ics per entry of a data list with detail view (modeled on KLXM Studio, event actions).
 *
 * Applies to YForm tables with a date field (YForm's Date or DateTime). The data list enables it (field
 * „In den Kalender anbieten“); then the detail view shows the link /fluid-ics/{page}-{entry}.ics (?lang=… with several
 * languages). The endpoint checks the same conditions as the detail view (data list filter, language) and returns
 * exactly one VEVENT:
 *  - Start: first date field (DateTime → with time in UTC, Date → all-day), end: date field with end/bis/until in its name,
 *    otherwise one all-day day or one hour.
 *  - Title: title field of the data list, description: text field (without HTML) and URL of the detail page, location:
 *    text field with place/ort/location/venue in its name.
 *
 * fluid doesn't support series (recurring events with individual occurrences) – each entry is one event.
 */
final class Ics
{
    public const string PATH = 'fluid-ics';

    /** URL of an entry's .ics file */
    public static function url(int $articleId, int $entryId, ?int $languageId = null): string
    {
        $languageId ??= Language::getCurrentId();

        return '/' . self::PATH . '/' . $articleId . '-' . $entryId . '.ics' . (count(Language::getAll()) > 1 ? '?lang=' . $languageId : '');
    }

    /** First date field of a table (Date or DateTime) – null if there is none */
    public static function dateField(object $table): ?object
    {
        foreach (YFormBridge::detailFields($table) as $f) {
            if ($f instanceof Date && !preg_match('~(end|bis|until)~i', $f->name)) {
                return $f;
            }
        }

        return null;
    }

    /** @internal Endpunkt /fluid-ics/{seite}-{eintrag}.ics */
    #[AsRoutesEndpoint('/' . self::PATH . '/*')]
    public static function serve(string $path, string $rest): void
    {
        if (!YFormBridge::available() || !preg_match('~^(\d+)-(\d+)\.ics$~', $rest, $m)) {
            return;
        }
        $lang = Request::get('lang', 'int', 0) ?: Language::getCurrentId();
        $ics = self::build((int) $m[1], (int) $m[2], $lang);
        if (null === $ics) {
            return;   // continue like an unknown URL (404)
        }
        Router::respond($ics, 'text/calendar; charset=utf-8', ['Content-Disposition: attachment; filename="termin-' . (int) $m[2] . '.ics"', 'Cache-Control: no-cache', 'X-Content-Type-Options: nosniff']);
    }

    /** Calendar file of an entry or null (no data list with calendar, entry not visible, no date) */
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
            if (null === $endF && $f instanceof Date && preg_match('~(end|bis|until)~i', $f->name)) {
                $endF = $f;
            }
            if (null === $placeF && $f instanceof Text && preg_match('~(place|ort|location|venue)~i', $f->name)) {
                $placeF = $f;
            }
        }
        $titleF = YFormBridge::field($t, (string) ($cfg['title_field'] ?? ''), [Text::class]);
        $textF = YFormBridge::field($t, (string) ($cfg['text_field'] ?? ''), [Textarea::class]);
        $title = $titleF ? YFormBridge::text($row, $titleF) : '#' . $entryId;
        $text = $textF ? trim((string) preg_replace('~\s+~u', ' ', html_entity_decode(strip_tags(YFormBridge::text($row, $textF)), ENT_QUOTES | ENT_HTML5, 'UTF-8'))) : '';
        $url = Router::absolute(Detail::url(Router::url($articleId, $languageId), $title, $entryId));

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
     * One VEVENT as a calendar file. $e: uid, start/end (Y-m-d all-day or ISO 8601 with time), title, text, place, url.
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
            // all-day: the end is exclusive (the day after)
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

    /** Escape text per RFC 5545 (backslash, semicolon, comma, line break) */
    private static function text(string $s): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\;', '\,', '\n', '\n', '\n'], $s);
    }

    /** Fold lines after 75 bytes (continuation lines: space + 74 bytes) without splitting UTF-8 characters */
    private static function fold(string $line): string
    {
        $out = [];
        $max = 75;
        while (strlen($line) > $max) {
            $cut = $max;
            while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) {
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
