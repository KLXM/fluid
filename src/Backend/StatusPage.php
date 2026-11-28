<?php

namespace Klxm\Fluid\Backend;

use Closure;
use FilesystemIterator;
use Klxm\Fluid\Kit;
use Klxm\Fluid\Mail\Mail;
use Klxm\Fluid\Search\Search;
use Klxm\Fluid\Settings;
use Klxm\ImageEditor\ImageEditorAddon;
use Klxm\VideoEditor\Ffmpeg as VideoFfmpeg;
use Klxm\VideoEditor\VideoEditor;
use Klxm\Routes\Redirects;
use Klxm\Routes\Routes;
use Klxm\Routes\Sites;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Redaxo\Core\Addon\Addon;
use Redaxo\Core\Content\Article;
use Redaxo\Core\Core;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Filesystem\Dir;
use Redaxo\Core\Filesystem\Path;
use Redaxo\Core\Filesystem\Url;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Language\Language;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Component\Section;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\Message;

use function count;
use function extension_loaded;
use function Redaxo\Core\View\escape;

use const FILTER_VALIDATE_EMAIL;

/**
 * Backend → AddOns → fluid → Status: environment check and maintenance (URLs, search index, map cache).
 *
 * @internal
 */
final class StatusPage
{
    private const string CSRF = 'fluid_status';

    public static function run(): string
    {
        $out = self::action();
        $start = Article::getSiteStartArticle();
        $kit = Kit::active();
        $routes = (int) (Sql::factory()->getArray('SELECT COUNT(*) AS n FROM ' . Routes::TABLE)[0]['n'] ?? 0);
        $n404 = (int) (Sql::factory()->getArray('SELECT COUNT(*) AS n FROM ' . Redirects::TABLE_404 . ' WHERE last_seen > NOW() - INTERVAL 30 DAY')[0]['n'] ?? 0);
        $primary = Sites::primary(Sites::main());
        $mail = Mail::status();
        $site = $start ? $start->getUrl(['fluid_edit' => 1]) : Url::frontend();

        // [status, area, text]; status: ok | warn | info
        $video = class_exists(VideoEditor::class);
        $rows = [
            [$start ? 'ok' : 'warn', I18n::rawMsg('fluid_backend_status_start'), $start ? I18n::rawMsg('fluid_backend_status_start_ok', escape($start->name), escape((string) $start->templateKey)) . ('fluid' === $start->templateKey ? '' : ' ' . I18n::msg('fluid_backend_status_start_not_fluid')) : I18n::msg('fluid_backend_status_start_none')],
            ['ok', I18n::rawMsg('fluid_backend_status_kit'), escape($kit->label()) . ' (' . escape($kit->key()) . ')' . (null !== ($parent = $kit->parent()) ? ' – ' . I18n::rawMsg('fluid_backend_status_kit_inherits', escape($parent->label())) : '')
                . (count(Kit::all()) > 1 ? ' · ' . I18n::msg('fluid_backend_status_kit_available', count(Kit::all())) : '')],
            [Core::getConfig('article_work_version') ? 'ok' : 'warn', I18n::rawMsg('fluid_backend_status_work_version'), Core::getConfig('article_work_version') ? I18n::msg('fluid_backend_status_work_version_on') : I18n::msg('fluid_backend_status_work_version_off')],
            [$routes > 0 ? 'ok' : 'warn', I18n::rawMsg('fluid_backend_status_routes'), I18n::rawMsg('fluid_backend_status_routes_text', $routes, '<a href="' . escape(Url::frontend() . 'sitemap.xml') . '" target="_blank" rel="noopener">sitemap.xml</a>')],
            [$primary ? 'ok' : 'info', I18n::rawMsg('fluid_backend_status_primary'), $primary ? escape($primary->host) : I18n::msg('fluid_backend_status_primary_none')],
            ['info', I18n::rawMsg('fluid_backend_status_languages'), count(Language::getAll()) . ' (' . escape(implode(', ', array_map(static fn (Language $l) => $l->code . ($l->isOnline() ? '' : ' offline'), Language::getAll()))) . ')'],
            [$mail['configured'] && '' !== $mail['from'] ? 'ok' : 'warn', I18n::rawMsg('fluid_backend_status_mail'), '' !== $mail['error']
                ? I18n::rawMsg('fluid_backend_status_mail_error', escape($mail['error']))
                : ($mail['configured'] ? escape($mail['transport']) . ('' !== $mail['from'] ? ' · ' . I18n::rawMsg('fluid_backend_status_mail_from', escape($mail['from'])) : ' · ' . I18n::msg('fluid_backend_status_mail_from_missing')) : I18n::msg('fluid_backend_status_mail_null'))
                . ($mail['detour'] ? ' · ' . I18n::rawMsg('fluid_backend_status_mail_detour', escape(implode(', ', $mail['detour']))) : '') . ' – ' . I18n::msg('fluid_backend_status_mail_details')],
            [extension_loaded('pdo_sqlite') ? 'ok' : 'warn', I18n::rawMsg('fluid_backend_status_search'), extension_loaded('pdo_sqlite') ? I18n::msg('fluid_backend_status_search_ok') : I18n::msg('fluid_backend_status_search_missing')],
            [$video ? 'ok' : 'info', I18n::rawMsg('fluid_backend_status_video'), $video ? I18n::msg('fluid_backend_status_video_ok') . (VideoFfmpeg::available() ? '' : ' · ' . I18n::msg('fluid_backend_status_video_no_ffmpeg')) : I18n::msg('fluid_backend_status_video_missing')],
            [Addon::exists('yform') ? 'ok' : 'info', 'YForm', Addon::exists('yform') ? I18n::msg('fluid_backend_status_yform_ok') : I18n::msg('fluid_backend_status_yform_missing')],
            [class_exists(ImageEditorAddon::class) ? 'ok' : 'info', I18n::rawMsg('fluid_backend_status_image_editor'), class_exists(ImageEditorAddon::class) ? I18n::msg('fluid_backend_status_image_editor_ok') : I18n::msg('fluid_backend_status_image_editor_missing')],
            [$n404 > 0 ? 'info' : 'ok', I18n::rawMsg('fluid_backend_status_404'), I18n::msg('fluid_backend_status_404_text', $n404)],
        ];
        // Header: summary (traffic light) and link to the website
        $count = array_count_values(array_column($rows, 0)) + ['ok' => 0, 'warn' => 0, 'info' => 0];
        $state = $count['warn'] > 0 ? 'warn' : 'ok';
        $out .= '<div class="fluid-status-hero" data-state="' . $state . '">'
            . '<div class="fluid-status-hero__icon" aria-hidden="true">' . self::icon($state) . '</div>'
            . '<div class="fluid-status-hero__text"><h2>' . ('warn' === $state ? I18n::msg(1 === $count['warn'] ? 'fluid_backend_status_attention_one' : 'fluid_backend_status_attention_many', $count['warn']) : I18n::msg('fluid_backend_status_all_ready')) . '</h2>'
            . '<p>' . I18n::msg('fluid_backend_status_summary_ok', $count['ok']) . ' · ' . I18n::msg(1 === $count['info'] ? 'fluid_backend_status_summary_info_one' : 'fluid_backend_status_summary_info_many', $count['info']) . ($count['warn'] ? ' · ' . I18n::msg('fluid_backend_status_summary_warn', $count['warn']) : '') . '</p></div>'
            . '<a class="rex-button" data-variant="primary" href="' . escape($site) . '"><i class="rex-icon fa-solid fa-pen-to-square" aria-hidden="true"></i> ' . I18n::msg('fluid_backend_edit_on_website') . '</a>'
            . '</div>';

        // Checks as tiles, warnings first
        $order = ['warn' => 0, 'info' => 1, 'ok' => 2];
        usort($rows, static fn (array $a, array $b) => $order[$a[0]] <=> $order[$b[0]]);
        $label = ['ok' => I18n::msg('fluid_backend_status_state_ok'), 'warn' => I18n::msg('fluid_backend_status_state_warn'), 'info' => I18n::msg('fluid_backend_status_state_info')];
        $tiles = '<ul class="fluid-status-checks">';
        foreach ($rows as [$st, $name, $text]) {
            $tiles .= '<li class="fluid-status-check" data-state="' . $st . '">'
                . '<span class="fluid-status-check__icon">' . self::icon($st) . '<span class="rex-u-visually-hidden">' . $label[$st] . ': </span></span>'
                . '<div><h3>' . escape($name) . '</h3><p>' . $text . '</p></div></li>';
        }
        $tiles .= '</ul>';
        $out .= new Section(title: I18n::rawMsg('fluid_backend_status_environment'), body: Html::raw($tiles))->render();

        $csrf = CsrfToken::factory(self::CSRF)->getHiddenField();
        $out .= self::mailSection($mail, $csrf);

        // Maintenance: one tile with explanation per task
        $task = static fn (string $do, string $icon, string $title, string $help, string $button) => '<form method="post" class="fluid-status-task">' . $csrf
            . '<span class="fluid-status-task__icon" aria-hidden="true"><i class="rex-icon fa-solid ' . $icon . '"></i></span><h3>' . escape($title) . '</h3><p>' . escape($help) . '</p>'
            . '<p class="fluid-status-task__action"><button class="rex-button" data-size="sm" name="fluid_do" value="' . $do . '">' . escape($button) . '</button></p></form>';
        $out .= new Section(
            title: I18n::rawMsg('fluid_backend_status_maintenance'),
            body: Html::raw('<p class="rex-u-text-muted">' . I18n::msg('fluid_backend_status_maintenance_intro') . '</p><div class="fluid-status-tasks">'
                . $task('routes', 'fa-route', I18n::rawMsg('fluid_backend_status_task_routes'), I18n::rawMsg('fluid_backend_status_task_routes_help'), I18n::rawMsg('fluid_backend_status_rebuild'))
                . $task('search', 'fa-magnifying-glass', I18n::rawMsg('fluid_backend_status_task_search'), I18n::rawMsg('fluid_backend_status_task_search_help'), I18n::rawMsg('fluid_backend_status_rebuild'))
                . $task('proxy', 'fa-map-location-dot', I18n::rawMsg('fluid_backend_status_task_proxy'), I18n::rawMsg('fluid_backend_status_task_proxy_help', self::size(Path::addonCache('fluid', 'proxy'))), I18n::rawMsg('fluid_backend_status_task_proxy_button'))
                . '</div>'),
        )->render();

        return $out;
    }

    private static function action(): string
    {
        $do = Request::post('fluid_do', 'string', '');
        if ('' === $do) {
            return '';
        }
        if (!CsrfToken::factory(self::CSRF)->isValid()) {
            return Message::error(I18n::msg('fluid_backend_csrf_expired'));
        }

        return match ($do) {
            'routes' => (static function () {
                Routes::rebuild();

                return Message::success(I18n::msg('fluid_backend_status_routes_rebuilt'));
            })(),
            'search' => Message::success(I18n::msg('fluid_backend_status_search_rebuilt', Search::rebuild())),
            'mailtest' => (static function () {
                $to = trim(Request::post('mail_to', 'string', ''));
                if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                    return Message::error(I18n::msg('fluid_backend_status_mail_invalid'));
                }
                $err = Mail::test($to);

                return '' === $err ? Message::success(I18n::msg('fluid_backend_status_mail_sent', $to)) : Message::error(I18n::msg('fluid_backend_status_mail_not_sent', $err));
            })(),
            'resend' => (static function () {
                $err = Mail::resend(Request::post('mail_file', 'string', ''));

                return '' === $err ? Message::success(I18n::msg('fluid_backend_status_mail_resent')) : Message::error(I18n::msg('fluid_backend_status_mail_resend_failed', $err));
            })(),
            'proxy' => (static function () {
                Dir::delete(Path::addonCache('fluid', 'proxy'));

                return Message::success(I18n::msg('fluid_backend_status_proxy_cleared'));
            })(),
            default => '',
        };
    }

    /**
     * Section „E-Mail“: setup, test mail, recent errors, unsent mails (resend).
     *
     * @param array<string, mixed> $mail
     */
    private static function mailSection(array $mail, string $csrf): string
    {
        $keep = trim((string) Settings::get('mail_keep_days', ''));
        $facts = [
            I18n::rawMsg('fluid_backend_mail_transport') => '' !== $mail['error'] ? '<span class="rex-u-text-danger">' . escape($mail['error']) . '</span>' : '<code>' . escape($mail['transport']) . '</code>',
            I18n::rawMsg('fluid_backend_mail_from') => '' !== $mail['from'] ? escape($mail['from']) : '<span class="rex-u-text-warning">' . I18n::msg('fluid_backend_mail_from_missing') . '</span>',
            I18n::rawMsg('fluid_backend_mail_detour') => $mail['detour'] ? I18n::rawMsg('fluid_backend_mail_detour_on', escape(implode(', ', $mail['detour']))) : I18n::msg('fluid_backend_mail_detour_off'),
            I18n::rawMsg('fluid_backend_mail_log') => escape($mail['log']) . ' <span class="rex-u-text-muted">(REX_MAILER_LOG: none, errors, all)</span>',
            I18n::rawMsg('fluid_backend_mail_archive') => $mail['archive']
                ? I18n::msg('fluid_backend_mail_archive_on', '' === $keep ? I18n::rawMsg('fluid_backend_mail_keep_default', Mail::KEEP_DAYS) : ('0' === $keep ? I18n::rawMsg('fluid_backend_mail_keep_unlimited') : I18n::rawMsg('fluid_backend_mail_keep_days', (int) $keep)))
                : I18n::msg('fluid_backend_mail_archive_off') . ' <span class="rex-u-text-muted">(' . I18n::msg('fluid_backend_mail_archive_off_note') . ')</span>',
        ];
        $body = '<dl class="fluid-status-facts">';
        foreach ($facts as $k => $v) {
            $body .= '<dt>' . escape($k) . '</dt><dd>' . $v . '</dd>';
        }
        $body .= '</dl>';

        $me = (string) (Core::getUser()->email ?? '');
        $body .= '<form method="post" class="fluid-status-mailtest">' . $csrf
            . '<label class="rex-field-label" for="fluid-mail-to">' . I18n::msg('fluid_backend_mail_test_to') . '</label>'
            . '<div class="rex-input-group"><input id="fluid-mail-to" class="rex-input" type="email" name="mail_to" required value="' . escape($me) . '" placeholder="name@example.org">'
            . '<span class="rex-input-group-actions"><button class="rex-button" data-variant="field" name="fluid_do" value="mailtest"><i class="rex-icon fa-solid fa-paper-plane" aria-hidden="true"></i> ' . I18n::msg('fluid_backend_mail_send') . '</button></span></div></form>';

        $time = I18n::msg('fluid_backend_mail_time');
        $to = I18n::msg('fluid_backend_mail_to');
        $subject = I18n::msg('fluid_backend_mail_subject');
        $table = static function (array $items, string $last, ?Closure $action = null) use ($time, $to, $subject): string {
            $h = '<table class="rex-table" data-density="compact" data-hover><thead><tr><th class="rex-table-date">' . $time . '</th><th>' . $to . '</th><th>' . $subject . '</th><th>' . $last . '</th></tr></thead><tbody>';
            foreach ($items as $m) {
                $h .= '<tr><td class="rex-table-date rex-u-nowrap" data-title="' . $time . '">' . escape(date('d.m.Y H:i', $m['time'])) . '</td>'
                    . '<td data-title="' . $to . '">' . escape($m['to']) . '</td><td data-title="' . $subject . '">' . escape($m['subject']) . '</td>'
                    . '<td data-title="' . $last . '">' . ($action ? $action($m) : '<span class="rex-u-text-danger rex-u-small">' . escape($m['error']) . '</span>') . '</td></tr>';
            }

            return $h . '</tbody></table>';
        };
        if ($mail['errors']) {
            $body .= '<h3 class="fluid-status-sub">' . I18n::msg('fluid_backend_mail_last_errors') . '</h3>' . $table($mail['errors'], I18n::msg('fluid_backend_mail_error'));
        }
        $failed = Mail::failed();
        if ($failed) {
            $body .= '<h3 class="fluid-status-sub">' . I18n::msg('fluid_backend_mail_failed') . '</h3><p class="rex-field-note">' . I18n::msg('fluid_backend_mail_failed_note') . '</p>'
                . $table($failed, I18n::msg('fluid_backend_mail_action'), static fn (array $m) => '<form method="post">' . $csrf . '<input type="hidden" name="mail_file" value="' . escape($m['file']) . '">'
                    . '<button class="rex-button" data-size="xs" name="fluid_do" value="resend">' . I18n::msg('fluid_backend_mail_resend') . '</button></form>');
        }

        return (string) new Section(title: I18n::rawMsg('fluid_backend_mail_section'), body: Html::raw($body))->render();
    }

    /** Icon per state (ok, warn, info) */
    private static function icon(string $state): string
    {
        return '<i class="rex-icon fa-solid ' . ['ok' => 'fa-circle-check', 'warn' => 'fa-triangle-exclamation', 'info' => 'fa-circle-info'][$state] . '" aria-hidden="true"></i>';
    }

    private static function size(string $dir): string
    {
        $b = 0;
        if (is_dir($dir)) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
                $b += $file->getSize();
            }
        }

        return $b > 1048576 ? round($b / 1048576, 1) . ' MB' : round($b / 1024) . ' KB';
    }
}
