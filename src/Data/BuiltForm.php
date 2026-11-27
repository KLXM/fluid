<?php

namespace Klxm\Fluid\Data;

use DateTimeImmutable;
use Klxm\Fluid\Mail\Mail;
use Klxm\Routes\Router;
use Klxm\Routes\Routes;
use Redaxo\Core\Content\Article;
use Yakamara\YForm\Attribute\AsForm;
use Yakamara\YForm\Data\Record;
use Yakamara\YForm\Field;
use Yakamara\YForm\Form\Action\Redirect;
use Yakamara\YForm\Form\Action\SaveRecord;
use Yakamara\YForm\Form\Action\SendMail;
use Yakamara\YForm\Form\Action\Submission;
use Yakamara\YForm\Form\Definition\FormDefinition;
use Yakamara\YForm\Form\Layout\Layout;
use Yakamara\YForm\Form\Layout\Tab;
use Yakamara\YForm\Validation\Rule\Length;

use function array_column;
use function array_filter;
use function array_map;
use function array_merge;
use function array_values;
use function count;
use function function_exists;
use function in_array;
use function is_array;
use function is_numeric;
use function ltrim;
use function preg_match;
use function preg_split;
use function Redaxo\Core\View\escape;
use function str_replace;
use function strtolower;
use function strtotime;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_UNICODE;

if (!class_exists(FormDefinition::class)) {
    return;
}

/**
 * Form that editors put together in the block „Formular“ on the website („Eigenes Formular“).
 *
 * A YForm form definition like any #[AsForm] class – only the fields, steps and actions come from the block
 * instead of code. Submit, validation, CSRF, honeypot, actions (SaveRecord, SendMail, Redirect) and the EP
 * YFORM_FORM_SUBMITTED run via YForm (FormDefinition::run). Steps = tabs of the YForm layout; the kit shows them
 * as steps with „Weiter/Zurück“, server validation errors open the right step (Form::activeTab).
 *
 * Field types: text, email, tel, url, textarea, number, date, select, radio, checkboxes, checkbox (consent),
 * file (YForm upload field), info (notice text), step (new step with title).
 *
 * „Mindestens/Höchstens“ become YForm rules: length for text types, value bounds for numbers and dates (dates also
 * relative, like `today` or `+1 year`). Uploaded files go to the team mail as attachments; a target table with an
 * upload field of the same name stores them, the inbox only lists their names (YForm removes unsaved uploads after a day).
 */
final class BuiltForm extends FormDefinition
{
    public const array TYPES = [
        'text' => 'Textzeile', 'email' => 'E-Mail', 'tel' => 'Telefon', 'url' => 'Internetadresse', 'textarea' => 'Mehrzeiliger Text',
        'number' => 'Zahl', 'decimal' => 'Kommazahl', 'date' => 'Datum', 'time' => 'Uhrzeit', 'datetime' => 'Datum und Uhrzeit', 'select' => 'Auswahlliste', 'radio' => 'Auswahl (eine)', 'checkboxes' => 'Auswahl (mehrere)',
        'file' => 'Datei hochladen', 'checkbox' => 'Häkchen (z. B. Zustimmung)', 'info' => 'Hinweistext (kein Eingabefeld)', 'step' => '— Neuer Schritt —',
    ];

    /** File types for uploads if the editor names none */
    public const array FILE_TYPES = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'heic', 'docx', 'xlsx', 'odt', 'ods', 'txt'];

    /** Never accepted, even if an editor lists them (could run on a server or in a browser) */
    private const array BLOCKED_TYPES = ['php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'php7', 'php8', 'pht', 'cgi', 'pl', 'py', 'sh', 'exe', 'bat',
        'cmd', 'com', 'js', 'mjs', 'html', 'htm', 'xhtml', 'svg', 'svgz', 'shtml', 'htaccess', 'jar', 'msi'];

    /** @var array<string, mixed> */
    private array $cfg = [];
    private ?Article $article = null;

    /** @param array<string, mixed> $cfg data of the block */
    public static function make(string $key, array $cfg, ?Article $article = null): self
    {
        $f = new self(new AsForm('fluid-' . $key, trim((string) ($cfg['form_name'] ?? '')) ?: 'Formular'));
        $f->cfg = $cfg;
        $f->article = $article;

        return $f;
    }

    /** @return list<array{type: string, name: string, label: string, item: array<string, mixed>}> */
    public function items(): array
    {
        $out = [];
        $used = [];
        foreach ((array) ($this->cfg['fields'] ?? []) as $i => $item) {
            if (!is_array($item) || !isset(self::TYPES[$item['type'] ?? ''])) {
                continue;
            }
            $label = trim((string) ($item['label'] ?? ''));
            $name = trim((string) ($item['name'] ?? '')) ?: str_replace('-', '_', substr(Routes::slugify($label), 0, 40));
            $name = preg_replace('~[^a-z0-9_]~', '', strtolower($name)) ?: 'feld';
            if (preg_match('~^\d~', $name)) {
                $name = 'f_' . $name;
            }
            $base = $name;
            for ($n = 2; isset($used[$name]); ++$n) {
                $name = $base . '_' . $n;
            }
            $used[$name] = true;
            $out[] = ['type' => (string) $item['type'], 'name' => 'step' === $item['type'] ? 'step_' . $i : $name, 'label' => $label, 'item' => $item];
        }

        return $out;
    }

    public function fields(): iterable
    {
        foreach ($this->items() as ['type' => $type, 'name' => $name, 'label' => $label, 'item' => $it]) {
            if ('step' === $type) {
                continue;
            }
            $req = !empty($it['required']);
            $help = trim((string) ($it['help'] ?? ''));
            $ph = trim((string) ($it['placeholder'] ?? ''));
            $options = [];
            foreach (preg_split('~\R~', (string) ($it['options'] ?? '')) ?: [] as $line) {
                if ('' !== ($line = trim($line))) {
                    $options[$line] = $line;
                }
            }
            $field = match ($type) {
                'email' => Field\Email::new($name),
                'tel' => Field\Text::new($name)->attributes(['type' => 'tel', 'autocomplete' => 'tel']),
                'url' => Field\Url::new($name),
                'textarea' => Field\Textarea::new($name),
                'number' => Field\Integer::new($name),
                'decimal' => Field\Decimal::new($name),
                'date' => Field\Date::new($name),
                'time' => Field\Time::new($name),
                'datetime' => Field\DateTime::new($name),
                'select' => Field\Choice::new($name)->options($options)->placeholder($ph ?: '–'),
                'radio' => Field\Choice::new($name)->options($options)->expanded(),
                'checkboxes' => Field\Choice::new($name)->options($options)->expanded()->multiple(),
                'checkbox' => Field\Checkbox::new($name),
                'file' => Field\Upload::new($name)->types(...self::fileTypes((string) ($it['file_types'] ?? '')))
                    ->maxSize(isset(YFormBridge::UPLOAD_SIZES[$it['max_size'] ?? '']) ? (string) $it['max_size'] : '5M'),
                'info' => Field\Markup::new($name)->html('<p>' . nl2br(htmlspecialchars($label), false) . '</p>'),
                default => Field\Text::new($name)->maxLength(500),
            };
            if ('info' === $type) {
                yield $field;
                continue;
            }
            $field->label($label ?: $name)->required($req);
            self::bounds($field, trim((string) ($it['min'] ?? '')), trim((string) ($it['max'] ?? '')));
            if ('' !== $help) {
                $field->notice($help);
            }
            // text, email, URL (Email and Url extend Text) support placeholder(); Textarea only as an attribute
            if ('' !== $ph && $field instanceof Field\Text) {
                $field->placeholder($ph);
            } elseif ('' !== $ph && 'textarea' === $type) {
                $field->attributes(['placeholder' => $ph]);
            }
            yield $field;
        }
    }

    /** Steps = tabs of the layout (only if there is a „Neuer Schritt“) */
    public function layout(Layout $layout): Layout
    {
        $steps = [];
        $cur = ['label' => trim((string) ($this->cfg['first_step'] ?? '')) ?: null, 'fields' => []];
        foreach ($this->items() as $it) {
            if ('step' === $it['type']) {
                if ($cur['fields']) {
                    $steps[] = $cur;
                }
                $cur = ['label' => $it['label'] ?: null, 'fields' => []];
                continue;
            }
            $cur['fields'][] = $it['name'];
        }
        if ($cur['fields']) {
            $steps[] = $cur;
        }
        if (count($steps) < 2) {
            return $layout;
        }
        foreach ($steps as $i => $s) {
            $layout->tab($s['label'] ?? ('Schritt ' . ($i + 1)), static function (Tab $t) use ($s): void {
                $t->fields(...$s['fields']);
            });
        }

        return $layout;
    }

    public function actions(): iterable
    {
        $mode = (string) ($this->cfg['mode'] ?? 'save_mail');
        $page = $this->article ? $this->article->name . ' (' . $this->article->getUrl() . ')' : '';
        // saved? Then a mail error does not discard the input (SafeMail) – otherwise duplicate entries on resubmit
        $saved = in_array($mode, ['save', 'save_mail'], true);
        if ($saved) {
            $target = trim((string) ($this->cfg['target'] ?? ''));
            yield '' !== $target && YFormBridge::table($target)
                ? new SaveRecord($target)
                : new SaveRecord(FormEntryTable::class, function (Record $r, Submission $s) use ($page): void {
                    $r->set('form', $this->definition->label ?? $this->definition->name);
                    $r->set('page', $page);
                    $r->set('summary', $this->summary($s));
                    $r->set('data', json_encode(array_filter($s->texts(), static fn ($v) => '' !== $v), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                });
        }
        $email = $this->emailField();
        if (in_array($mode, ['mail', 'save_mail'], true) && '' !== ($to = trim((string) ($this->cfg['mail_to'] ?? '')))) {
            $subject = trim((string) ($this->cfg['mail_subject'] ?? '')) ?: 'Neue Nachricht: ' . ($this->definition->label ?? 'Formular');
            $pageUrl = $this->article ? Router::absolute($this->article->getUrl()) : '';
            $files = array_column(array_filter($this->items(), static fn (array $it): bool => 'file' === $it['type']), 'name');
            yield new SafeMail(new SendMail(to: array_map('trim', explode(',', $to)), subject: $subject, html: true, replyTo: $email ? '{' . $email . '}' : null,
                attachments: static fn (Submission $s): array => array_merge([], ...array_map($s->files(...), $files)),
                body: fn (Submission $s) => Mail::layout($s->interpolate($subject), $this->summaryHtml($s)
                    . ('' !== $pageUrl ? '<p style="margin:20px 0 0;font-size:14px;color:#6b7280">Gesendet über <a href="' . escape($pageUrl) . '">' . escape((string) $this->article?->name) . '</a></p>' : ''))),
                SafeMail::TEAM, $saved);
        }
        if (!empty($this->cfg['confirm']) && $email) {
            $text = trim((string) ($this->cfg['confirm_text'] ?? '')) ?: 'Vielen Dank für Ihre Nachricht. Wir melden uns bald.';
            $confirmSubject = trim((string) ($this->cfg['confirm_subject'] ?? '')) ?: 'Ihre Nachricht ist angekommen';
            yield new SafeMail(new SendMail(to: '{' . $email . '}', subject: $confirmSubject, html: true,
                body: fn (Submission $s) => Mail::layout($s->interpolate($confirmSubject), '<p>' . nl2br(escape($s->interpolate($text)), false) . '</p>'
                    . ($this->cfg['confirm_copy'] ?? false ? '<h2 style="margin:24px 0 8px;font-size:17px">Ihre Angaben</h2>' . $this->summaryHtml($s) : ''))),
                SafeMail::CONFIRM, $saved);
        }
        $redirect = (string) ($this->cfg['redirect'] ?? '');
        if (preg_match('~^redaxo://(\d+)~', $redirect, $m)) {
            yield new Redirect((int) $m[1]);
        }
    }

    public function submitLabel(): string
    {
        return trim((string) ($this->cfg['submit'] ?? '')) ?: 'Absenden';
    }

    public function successMessage(): string
    {
        $msg = nl2br(htmlspecialchars(trim((string) ($this->cfg['success'] ?? '')) ?: 'Vielen Dank! Ihre Nachricht ist angekommen.'), false);
        // input saved but the confirmation was not sent: say so honestly, so that nobody submits again
        if (in_array(SafeMail::CONFIRM, SafeMail::$failed, true)) {
            $note = 'Die Bestätigung per E-Mail konnte gerade nicht gesendet werden – Ihre Angaben sind aber angekommen.';
            // one element: kit messages are often flex containers (otherwise the notice would sit next to it)
            $msg = '<span>' . $msg . '<br><small>' . htmlspecialchars(function_exists('lt') ? lt($note) : $note) . '</small></span>';
        }

        return $msg;
    }

    /**
     * Allowed extensions from the editor's list („pdf, jpg“), without blocked ones; empty list = FILE_TYPES.
     *
     * @return list<string>
     */
    public static function fileTypes(string $list): array
    {
        $types = [];
        foreach (preg_split('~[\s,;]+~', strtolower($list)) ?: [] as $type) {
            $type = ltrim($type, '.');
            if (preg_match('~^[a-z0-9]{1,8}$~', $type) && !in_array($type, self::BLOCKED_TYPES, true)) {
                $types[$type] = $type;
            }
        }

        return $types ? array_values($types) : self::FILE_TYPES;
    }

    /** „Mindestens/Höchstens“ of the block as YForm rules – values that do not fit the field type are ignored */
    private static function bounds(Field\Field $field, string $min, string $max): void
    {
        if ('' === $min && '' === $max) {
            return;
        }
        $int = static fn (string $v): ?int => preg_match('~^-?\d+$~', $v) ? (int) $v : null;
        $num = static fn (string $v): ?string => is_numeric($v = str_replace(',', '.', $v)) ? $v : null;
        // as a date object: YForm writes strings unchanged into the min/max attributes of the input („today“ is no valid value there)
        $date = static fn (string $v): ?DateTimeImmutable => '' !== $v && false !== strtotime($v) ? new DateTimeImmutable($v) : null;
        if ($field instanceof Field\Integer) {
            $field->min($int($min))->max($int($max));
        } elseif ($field instanceof Field\Decimal) {
            $field->min($num($min))->max($num($max));
        } elseif ($field instanceof Field\Date) {
            $field->min($date($min))->max($date($max));
        } elseif ($field instanceof Field\Text || $field instanceof Field\Textarea) {
            $lo = $int($min);
            $hi = $int($max);
            if ((null !== $lo && $lo > 0) || (null !== $hi && $hi > 0)) {
                $field->rule(new Length(null !== $lo && $lo > 0 ? $lo : null, null !== $hi && $hi > 0 ? $hi : null));
            }
        }
    }

    /** First email field (reply address, confirmation) */
    private function emailField(): ?string
    {
        foreach ($this->items() as $it) {
            if ('email' === $it['type']) {
                return $it['name'];
            }
        }

        return null;
    }

    /** „Label: value“ per line – for mail and inbox */
    /** Data as a table for HTML mails */
    private function summaryHtml(Submission $s): string
    {
        $rows = '';
        foreach ($this->items() as $it) {
            if (in_array($it['type'], ['step', 'info'], true) || '' === ($v = $s->text($it['name']))) {
                continue;
            }
            // „Label: “ and value in <div>: so the text part (YForm SendMail::plainText) gives „Label: value“ per line
            $rows .= '<tr><th align="left" valign="top" width="36%" style="width:36%;padding:8px 12px 8px 0;border-bottom:1px solid #e5e7eb;font-weight:600">'
                . escape(rtrim((string) ($it['label'] ?: $it['name']), '.:?! ')) . ': </th><td style="padding:8px 0;border-bottom:1px solid #e5e7eb"><div>' . nl2br(escape($v), false) . '</div></td></tr>';
        }

        return '' !== $rows ? '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:15px">' . $rows . '</table>' : '';
    }

    private function summary(Submission $s): string
    {
        $lines = [];
        foreach ($this->items() as $it) {
            if (in_array($it['type'], ['step', 'info'], true)) {
                continue;
            }
            $v = $s->text($it['name']);
            if ('' !== $v) {
                $lines[] = ($it['label'] ?: $it['name']) . ': ' . (str_contains($v, "\n") ? "\n" . $v : $v);
            }
        }

        return implode("\n", $lines);
    }
}
