<?php

namespace Klxm\Fluid\Mail;

use DateTimeImmutable;
use FilesystemIterator;
use Klxm\Fluid\Kit;
use Klxm\Fluid\Settings;
use Klxm\Routes\Router;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Redaxo\Core\Core;
use Redaxo\Core\Cronjob\AsCronjob;
use Redaxo\Core\Env;
use Redaxo\Core\Filesystem\File;
use Redaxo\Core\Log\LogFile;
use Redaxo\Core\Mailer\MailArchive;
use Redaxo\Core\Mailer\Mailer;
use Redaxo\Core\MediaPool\Media;
use Redaxo\Core\Translation\I18n;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Throwable;

use function array_slice;
use function count;
use function dirname;
use function function_exists;
use function is_dir;
use function is_file;
use function Redaxo\Core\View\escape;
use function strlen;

use const ICONV_MIME_DECODE_CONTINUE_ON_ERROR;

/**
 * Module „Mail“: everything fluid does with the core mailer (Redaxo\Core\Mailer, Symfony Mailer).
 *
 *  - status()   setup at a glance (status page): sending, sender, redirect, log, archive, last errors
 *  - test()     test mail to an address
 *  - failed()/resend()   resend unsent mails from the archive (not_sent_*.eml) – to X-Envelope-To
 *                        (including Bcc, since redaxo/core#6755), otherwise To and Cc
 *  - layout()   HTML mail in the style of the website (logo, accent color of the kit, contact in the footer) – for form mails
 *  - purgeArchive()   cronjob: delete archived mails after „Mails aufbewahren (Tage)“ (privacy)
 *
 * Self-contained (could later become its own AddOn): needs only the core and the website settings of fluid.
 */
final class Mail
{
    /** Default for „Mails aufbewahren (Tage)“ if nothing is set */
    public const int KEEP_DAYS = 90;

    /**
     * @return array{configured: bool, transport: string, from: string, detour: list<string>, log: string, archive: bool,
     *     error: string, errors: list<array{time: int, to: string, subject: string, error: string}>}
     */
    public static function status(): array
    {
        $out = ['configured' => false, 'transport' => '', 'from' => (string) Env::get('REX_MAILER_FROM'), 'detour' => [],
            'log' => (string) (Env::get('REX_MAILER_LOG') ?? 'errors'), 'archive' => Env::getBool('REX_MAILER_ARCHIVE'), 'error' => '', 'errors' => []];
        try {
            $mailer = Core::getMailer();
            // without credentials: SMTP transports only output scheme, host and port
            $out['transport'] = (string) $mailer->transport;
            $out['configured'] = !str_starts_with($out['transport'], 'null://');
            $out['from'] = $mailer->defaultFrom?->toString() ?? '';
            $out['detour'] = array_map(static fn (Address $a) => $a->getAddress(), $mailer->detourRecipients);
        } catch (Throwable $e) {
            $out['error'] = $e->getMessage();
        }
        if (is_file(Mailer::logFile())) {
            foreach (LogFile::factory(Mailer::logFile()) as $entry) {
                $d = $entry->getData();
                if ('ERROR' === ($d[0] ?? '')) {
                    $out['errors'][] = ['time' => $entry->getTimestamp(), 'to' => (string) ($d[2] ?? ''), 'subject' => (string) ($d[3] ?? ''), 'error' => (string) ($d[4] ?? '')];
                    if (count($out['errors']) >= 5) {
                        break;
                    }
                }
            }
        }

        return $out;
    }

    /** Test mail; returns '' on success, otherwise the error message */
    public static function test(string $to): string
    {
        try {
            $name = Settings::name();
            Core::getMailer()->send(new Email()->to($to)->subject(I18n::rawMsg('fluid_mail_test_subject', $name, date('d.m.Y H:i')))
                ->text(I18n::rawMsg('fluid_mail_test_text', $name))
                ->html(self::layout(I18n::rawMsg('fluid_mail_test_title'), '<p>' . I18n::msg('fluid_mail_test_html', $name) . '</p>')), archive: false);

            return '';
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }

    // ------------------------------------------------------------------ unsent mails

    /**
     * Unsent mails in the archive (REX_MAILER_ARCHIVE), newest first.
     *
     * @return list<array{file: string, time: int, to: string, subject: string}>
     */
    public static function failed(int $limit = 20): array
    {
        $dir = MailArchive::folder();
        if (!is_dir($dir)) {
            return [];
        }
        $files = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $f) {
            if ($f->isFile() && str_starts_with($f->getFilename(), 'not_sent_') && str_ends_with($f->getFilename(), '.eml')) {
                $files[$f->getPathname()] = $f->getMTime();
            }
        }
        arsort($files);
        $out = [];
        foreach (array_slice($files, 0, $limit, true) as $file => $time) {
            $h = self::headers((string) File::get($file));
            $out[] = ['file' => self::relative($file), 'time' => $time, 'to' => $h['to'], 'subject' => $h['subject']];
        }

        return $out;
    }

    /**
     * Resend an unsent mail. Recipients from To, Cc and Bcc; the Bcc header is removed before sending
     * (otherwise all recipients would see the Bcc addresses). On success the file loses the prefix „not_sent_“.
     * Returns '' on success, otherwise the error message.
     */
    public static function resend(string $relative): string
    {
        $file = self::absolute($relative);
        if (null === $file) {
            return I18n::rawMsg('fluid_mail_file_not_found');
        }
        $raw = (string) File::get($file);
        [$head, $body] = array_pad(preg_split("~\r?\n\r?\n~", $raw, 2) ?: [], 2, '');
        $headers = self::unfold($head);
        // recipients: X-Envelope-To (noted by the core since redaxo/core#6755, including Bcc), otherwise To, Cc, Bcc
        $recipients = [];
        foreach (isset($headers['x-envelope-to']) ? ['x-envelope-to'] : ['to', 'cc', 'bcc'] as $name) {
            foreach (self::addresses($headers[$name] ?? '') as $a) {
                $recipients[$a] = new Address($a);
            }
        }
        $from = self::addresses($headers['from'] ?? '')[0] ?? null;
        if (!$recipients || null === $from) {
            return I18n::rawMsg('fluid_mail_no_address');
        }
        // remove Bcc and X-Envelope-To (including continuation lines) from the header – otherwise everyone would see the Bcc addresses
        $cleanHead = (string) preg_replace('~^(?:Bcc|X-Envelope-To):.*(?:\r?\n[ \t].*)*\r?\n?~mi', '', $head);
        try {
            Core::getMailer()->send(new RawMessage($cleanHead . "\r\n\r\n" . $body), new Envelope(new Address($from), array_values($recipients)), archive: false);
        } catch (Throwable $e) {
            return $e->getMessage();
        }
        File::move($file, dirname($file) . '/' . substr(basename($file), strlen('not_sent_')));

        return '';
    }

    /** @return array{to: string, subject: string} */
    private static function headers(string $raw): array
    {
        $h = self::unfold((string) (preg_split("~\r?\n\r?\n~", $raw, 2)[0] ?? ''));
        $subject = (string) (iconv_mime_decode((string) ($h['subject'] ?? ''), ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8') ?: ($h['subject'] ?? ''));

        return ['to' => implode(', ', self::addresses($h['x-envelope-to'] ?? (($h['to'] ?? '') . ',' . ($h['cc'] ?? '')))), 'subject' => $subject];
    }

    /** @return array<string, string> header lines (lowercase), continuation lines joined */
    private static function unfold(string $head): array
    {
        $out = [];
        foreach (explode("\n", (string) preg_replace("~\r?\n[ \t]+~", ' ', $head)) as $line) {
            if (preg_match('~^([A-Za-z-]+):\s*(.*)$~', rtrim($line), $m)) {
                $out[strtolower($m[1])] = $m[2];
            }
        }

        return $out;
    }

    /** @return list<string> email addresses from an address header */
    private static function addresses(string $value): array
    {
        preg_match_all('~[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}~', $value, $m);

        return array_values(array_unique($m[0]));
    }

    private static function relative(string $file): string
    {
        return ltrim(substr($file, strlen(MailArchive::folder())), '/');
    }

    /** Path in the archive – only real not_sent_*.eml below the archive */
    private static function absolute(string $relative): ?string
    {
        if (!preg_match('~^\d{4}/\d{2}/not_sent_[\w-]+\.eml$~', $relative)) {
            return null;
        }
        $file = MailArchive::folder() . '/' . $relative;

        return is_file($file) ? $file : null;
    }

    // ------------------------------------------------------------------ Layout

    /**
     * HTML mail in the style of the website: logo or name, accent color from the kit design, content, footer with address and
     * imprint. Tables and inline styles, so that it holds up in Outlook and webmail too.
     */
    public static function layout(string $title, string $contentHtml): string
    {
        $design = Kit::active()->design();
        $accent = self::hex((string) $design->get('accent'), '#4338CA');
        $onAccent = self::hex((string) $design->get('on_accent'), '#FFFFFF');
        $ink = self::hex((string) $design->get('ink'), '#0B0D17');
        $text = self::hex((string) $design->get('text'), '#2B2F3A');
        $surface = self::hex((string) $design->get('surface'), '#F4F5F8');
        $name = Settings::name();
        $logo = (string) Settings::get('logo', '');
        // Gmail and Outlook don't show SVG in mails – then the name as lettering
        $logoUrl = '' !== $logo && !str_ends_with(strtolower($logo), '.svg') && ($m = Media::get($logo)) ? Router::absolute($m->getUrl()) : '';
        $brand = '' !== $logoUrl
            ? '<img src="' . escape($logoUrl) . '" alt="' . escape($name) . '" height="40" style="display:block;height:40px;width:auto;border:0">'
            : '<span style="font:700 20px/1.2 Arial,Helvetica,sans-serif;color:' . $ink . '">' . escape($name) . '</span>';
        $address = implode(' · ', array_filter([trim((string) Settings::get('street', '')), trim(Settings::get('zip', '') . ' ' . Settings::get('city', '')), trim((string) Settings::get('phone', '')), trim((string) Settings::get('email', ''))]));
        $imprint = trim((string) Settings::get('imprint_page', ''));
        $imprintUrl = '' !== $imprint && function_exists('link_href') ? Router::absolute(link_href($imprint)) : '';
        $font = 'font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif';

        // no <title>: the text part is created from the HTML (strip_tags) and would contain it twice
        return '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"></head>'
            . '<body style="margin:0;padding:0;background:' . $surface . '">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:' . $surface . '"><tr><td align="center" style="padding:24px 12px">'
            . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#FFFFFF;border-radius:10px;overflow:hidden">'
            . '<tr><td style="padding:24px 28px;border-bottom:4px solid ' . $accent . '"><div>' . $brand . '</div></td></tr>'
            . '<tr><td style="padding:28px;' . $font . ';font-size:16px;line-height:1.55;color:' . $text . '">'
            . '<h1 style="margin:0 0 16px;font-size:22px;line-height:1.25;color:' . $ink . '">' . escape($title) . '</h1>' . $contentHtml . '</td></tr>'
            . '<tr><td style="padding:18px 28px;background:' . $accent . ';' . $font . ';font-size:13px;line-height:1.5;color:' . $onAccent . '">'
            . escape($name) . ('' !== $address ? '<br>' . escape($address) : '')
            . ('' !== $imprintUrl ? '<br><a href="' . escape($imprintUrl) . '" style="color:' . $onAccent . '">Impressum</a>' : '')
            . '</td></tr></table></td></tr></table></body></html>';
    }

    private static function hex(string $value, string $fallback): string
    {
        return preg_match('~^#[0-9A-Fa-f]{3,8}$~', $value) ? $value : $fallback;
    }

    // ------------------------------------------------------------------ retention

    /** Cronjob: delete archived mails older than „Mails aufbewahren (Tage)“ (0 = never delete) */
    #[AsCronjob('fluid_mail_archive_purge', '@daily')]
    public static function purgeArchive(): string
    {
        $raw = trim((string) Settings::get('mail_keep_days', ''));
        $days = '' === $raw ? self::KEEP_DAYS : (int) $raw;
        if ($days <= 0) {
            return I18n::rawMsg('fluid_mail_purge_never');
        }

        return I18n::rawMsg('fluid_mail_purged', MailArchive::purge(new DateTimeImmutable('-' . $days . ' days')), $days);
    }
}
