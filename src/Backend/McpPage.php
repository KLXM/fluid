<?php

namespace Klxm\Fluid\Backend;

use Klxm\Fluid\Data\YFormBridge;
use Klxm\Fluid\FluidAddon;
use Klxm\Fluid\Mcp\Access;
use Klxm\Fluid\Mcp\Server;
use Klxm\Fluid\Mcp\Tokens;
use Redaxo\Core\Database\Sql;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Security\CsrfToken;
use Redaxo\Core\Translation\I18n;
use Redaxo\Core\View\Component\Section;
use Redaxo\Core\View\Html;
use Redaxo\Core\View\Message;

use function in_array;
use function is_array;
use function Redaxo\Core\View\escape;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;

/**
 * Backend → AddOns → fluid → MCP: enable the server, define public access, manage access keys.
 * Everything is off by default; the admin decides what is public.
 *
 * @internal
 */
final class McpPage
{
    private const string CSRF = 'fluid_mcp';

    public static function run(): string
    {
        $out = self::action();
        $cfg = Access::config();
        $csrf = CsrfToken::factory(self::CSRF)->getHiddenField();
        $tables = YFormBridge::tables();

        // ---------------------------------------------------------------- Settings (core markup and components)
        $field = static fn (string $label, string $control, string $note = '', string $for = '') => '<div class="rex-field"><' . ('' !== $for ? 'label for="' . $for . '"' : 'span') . ' class="rex-field-label">' . $label . '</' . ('' !== $for ? 'label' : 'span') . '>'
            . '<div class="rex-field-control">' . $control . ('' !== $note ? '<p class="rex-field-note">' . $note . '</p>' : '') . '</div></div>';
        $check = static fn (string $name, bool $on, string $label) => '<div class="rex-check"><label><input type="checkbox" name="mcp[' . $name . ']" value="1"' . ($on ? ' checked' : '') . '> ' . $label . '</label></div>';
        $body = '<form method="post">' . $csrf . '<input type="hidden" name="mcp_do" value="settings">'
            . $field(I18n::msg('fluid_backend_mcp_server'), $check('enabled', (bool) $cfg['enabled'], I18n::msg('fluid_backend_mcp_enable')), I18n::rawMsg('fluid_backend_mcp_server_note', '<code>' . escape(Server::url()) . '</code>'))
            . $field(I18n::msg('fluid_backend_mcp_public'), $check('public', (bool) $cfg['public'], I18n::msg('fluid_backend_mcp_public_enable'))
                . '<div class="fluid-mcp-sub">' . $check('public_pages', (bool) $cfg['public_pages'], I18n::msg('fluid_backend_mcp_public_pages'))
                . $check('public_search', (bool) $cfg['public_search'], I18n::msg('fluid_backend_mcp_public_search')) . $check('public_blog', (bool) $cfg['public_blog'], I18n::msg('fluid_backend_mcp_public_blog')) . '</div>',
                I18n::msg('fluid_backend_mcp_public_note'));
        if ([] !== $tables) {
            $pub = '';
            foreach ($tables as $name => $label) {
                $pub .= '<div class="rex-check"><label><input type="checkbox" name="mcp[public_tables][]" value="' . escape($name) . '"' . (in_array($name, (array) $cfg['public_tables'], true) ? ' checked' : '') . '> ' . escape($label) . '</label></div>';
            }
            $body .= $field(I18n::msg('fluid_backend_mcp_public_tables'), $pub, I18n::msg('fluid_backend_mcp_public_tables_note'));
            foreach ($tables as $name => $label) {
                $mode = ((array) $cfg['tables'])[$name] ?? '';
                $id = 'mcp-t-' . md5($name);
                $sel = '<select class="rex-input" id="' . $id . '" name="mcp[tables][' . escape($name) . ']">';
                foreach (['' => I18n::msg('fluid_backend_mcp_table_none'), 'read' => I18n::msg('fluid_backend_mcp_table_read'), 'write' => I18n::msg('fluid_backend_mcp_table_write')] as $v => $l) {
                    $sel .= '<option value="' . $v . '"' . ($v === $mode ? ' selected' : '') . '>' . $l . '</option>';
                }
                $body .= $field(I18n::rawMsg('fluid_backend_mcp_table_key', escape($label)), $sel . '</select>', '', $id);
            }
            $body .= '<p class="rex-field-note">' . I18n::msg('fluid_backend_mcp_tables_note') . '</p>';
        }
        $body .= $field(I18n::msg('fluid_backend_mcp_instructions'), '<textarea class="rex-input" id="mcp-instr" name="mcp[instructions]" rows="2" placeholder="' . I18n::msg('fluid_backend_mcp_instructions_placeholder') . '">' . escape((string) $cfg['instructions']) . '</textarea>', I18n::msg('fluid_backend_mcp_optional'), 'mcp-instr')
            . '<div class="rex-button-toolbar"><button type="submit" class="rex-button" data-variant="primary">' . I18n::msg('fluid_backend_mcp_save') . '</button></div></form>';
        $out .= new Section(title: I18n::rawMsg('fluid_backend_mcp_server'), body: Html::raw($body))->render();

        // ---------------------------------------------------------------- Keys
        $rows = '';
        foreach (Tokens::all() as $t) {
            $rows .= '<tr><td><strong>' . escape((string) $t['name']) . '</strong><br><code>' . escape((string) $t['prefix']) . '…</code></td>'
                . '<td>' . escape((string) ($t['login'] ?? '–')) . '</td>'
                . '<td>' . (isset(Tokens::SCOPES[$t['scope']]) ? I18n::msg('fluid_backend_mcp_scope_' . $t['scope']) : escape((string) $t['scope'])) . ($t['publish'] ? '<br><small>+ ' . I18n::msg('fluid_backend_mcp_may_publish_short') . '</small>' : '') . '</td>'
                . '<td><small>' . I18n::msg('fluid_backend_mcp_created', (string) $t['created']) . '<br>' . I18n::msg('fluid_backend_mcp_last_used', (string) ($t['last_used'] ?: '–')) . ($t['client'] ? '<br>' . escape((string) $t['client']) : '') . ($t['expires'] ? '<br>' . I18n::msg('fluid_backend_mcp_valid_until', (string) $t['expires']) : '') . '</small></td>'
                . '<td><form method="post">' . $csrf
                . '<input type="hidden" name="mcp_do" value="revoke"><input type="hidden" name="id" value="' . (int) $t['id'] . '">'
                . '<button type="submit" class="rex-button" data-variant="danger" data-size="xs" data-confirm="' . I18n::msg('fluid_backend_mcp_revoke_confirm') . '">' . I18n::msg('fluid_backend_mcp_revoke') . '</button></form></td></tr>';
        }
        $out .= new Section(
            title: I18n::rawMsg('fluid_backend_mcp_keys'),
            body: '' === $rows ? Html::raw('<p>' . I18n::msg('fluid_backend_mcp_no_keys') . '</p>') : '',
            content: '' !== $rows ? Html::raw('<table class="rex-table" data-hover><thead><tr><th>' . I18n::msg('fluid_backend_mcp_name') . '</th><th>' . I18n::msg('fluid_backend_mcp_user') . '</th><th>' . I18n::msg('fluid_backend_mcp_scope') . '</th><th>' . I18n::msg('fluid_backend_mcp_usage') . '</th><th></th></tr></thead><tbody>' . $rows . '</tbody></table>') : '',
        )->render();

        $users = '';
        foreach (Sql::factory()->getArray('SELECT id, login, name FROM rex_user WHERE status = 1 ORDER BY login') as $u) {
            $users .= '<option value="' . (int) $u['id'] . '">' . escape($u['login'] . ($u['name'] ? ' (' . $u['name'] . ')' : '')) . '</option>';
        }
        $body = '<form method="post">' . $csrf . '<input type="hidden" name="mcp_do" value="create">'
            . $field(I18n::msg('fluid_backend_mcp_name'), '<input class="rex-input" id="mcp-name" name="name" required placeholder="' . I18n::msg('fluid_backend_mcp_name_placeholder') . '">', '', 'mcp-name')
            . $field(I18n::msg('fluid_backend_mcp_acts_as'), '<select class="rex-input" id="mcp-user" name="user">' . $users . '</select>', I18n::msg('fluid_backend_mcp_acts_as_note'), 'mcp-user')
            . $field(I18n::msg('fluid_backend_mcp_scope'), '<select class="rex-input" id="mcp-scope" name="scope"><option value="read">' . I18n::msg('fluid_backend_mcp_scope_read_only') . '</option><option value="write">' . I18n::msg('fluid_backend_mcp_table_write') . '</option></select>', '', 'mcp-scope')
            . $field(I18n::msg('fluid_backend_mcp_expires'), '<input class="rex-input" type="date" id="mcp-expires" name="expires">', I18n::msg('fluid_backend_mcp_optional'), 'mcp-expires')
            . $field(I18n::msg('fluid_backend_mcp_publish'), '<div class="rex-check"><label><input type="checkbox" name="publish" value="1"> ' . I18n::msg('fluid_backend_mcp_may_publish') . '</label></div>')
            . '<div class="rex-button-toolbar"><button type="submit" class="rex-button" data-variant="primary">' . I18n::msg('fluid_backend_mcp_create') . '</button><span class="rex-u-text-muted">' . I18n::msg('fluid_backend_mcp_create_note') . '</span></div></form>';
        $out .= new Section(title: I18n::rawMsg('fluid_backend_mcp_new_key'), body: Html::raw($body))->render();

        return $out;
    }

    private static function action(): string
    {
        $do = Request::post('mcp_do', 'string', '');
        if ('' === $do) {
            return '';
        }
        if (!CsrfToken::factory(self::CSRF)->isValid()) {
            return Message::error(I18n::msg('fluid_backend_csrf_expired'));
        }
        $addon = FluidAddon::instance();

        return match ($do) {
            'settings' => (static function () use ($addon): string {
                $in = Request::post('mcp', 'array', []);
                $known = array_keys(YFormBridge::tables());
                $tables = [];
                foreach ((array) ($in['tables'] ?? []) as $name => $mode) {
                    if (in_array($name, $known, true) && in_array($mode, ['read', 'write'], true)) {
                        $tables[$name] = $mode;
                    }
                }
                $addon->setConfig('mcp', [
                    'enabled' => !empty($in['enabled']),
                    'public' => !empty($in['public']),
                    'public_pages' => !empty($in['public_pages']),
                    'public_search' => !empty($in['public_search']),
                    'public_blog' => !empty($in['public_blog']),
                    'public_tables' => array_values(array_intersect(is_array($in['public_tables'] ?? null) ? $in['public_tables'] : [], $known)),
                    'tables' => $tables,
                    'instructions' => mb_substr(trim((string) ($in['instructions'] ?? '')), 0, 1000),
                ]);

                return Message::success(I18n::msg('fluid_backend_mcp_saved'));
            })(),
            'create' => (static function (): string {
                $user = Request::post('user', 'int', 0);
                if ($user <= 0) {
                    return Message::error(I18n::msg('fluid_backend_mcp_choose_user'));
                }
                $expires = Request::post('expires', 'string', '');
                $t = Tokens::create(Request::post('name', 'string', ''), Request::post('scope', 'string', 'read'), $user, '1' === Request::post('publish', 'string', ''), 1 === preg_match('/^\d{4}-\d{2}-\d{2}$/', $expires) ? $expires : null);
                $url = Server::url();
                $cli = 'claude mcp add --transport http fluid ' . $url . ' --header "Authorization: Bearer ' . $t['token'] . '"';
                $json = json_encode(['mcpServers' => ['fluid' => ['type' => 'http', 'url' => $url, 'headers' => ['Authorization' => 'Bearer ' . $t['token']]]]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

                return Message::success('<p><strong>' . I18n::msg('fluid_backend_mcp_created_copy') . '</strong></p>'
                    . '<pre class="fluid-mcp-secret">' . escape($t['token']) . '</pre>'
                    . '<p>Claude Code:</p><pre class="fluid-mcp-secret fluid-mcp-secret--wrap">' . escape($cli) . '</pre>'
                    . '<p>' . I18n::msg('fluid_backend_mcp_other_clients') . '</p><pre class="fluid-mcp-secret">' . escape((string) $json) . '</pre>'
                    . (Access::config()['enabled'] ? '' : '<p>' . I18n::msg('fluid_backend_mcp_still_off') . '</p>'));
            })(),
            'revoke' => (static function (): string {
                Tokens::revoke(Request::post('id', 'int', 0));

                return Message::success(I18n::msg('fluid_backend_mcp_revoked'));
            })(),
            default => '',
        };
    }
}
