<?php

namespace Klxm\Fluid\Data;

use Klxm\Fluid\BlockData;
use Klxm\Fluid\Form\FieldForm;
use Override;
use Redaxo\Core\ApiFunction\ApiFunction;
use Redaxo\Core\ApiFunction\AsApiFunction;
use Redaxo\Core\ApiFunction\Result;
use Redaxo\Core\Content\ModulePermission;
use Redaxo\Core\Http\Request;
use Redaxo\Core\Http\Response;
use Redaxo\Core\Security\BackendLogin;
use Redaxo\Core\Translation\I18n;
use RuntimeException;
use Throwable;

use function is_array;

/**
 * YForm field „Fluid-Blöcke“ in the form: load a block's form (action=form) and convert the input into block data
 * (action=parse) – the same fields as on the website (FieldForm). Only with a backend session and module permission.
 *
 * @internal
 */
#[AsApiFunction('fluid_blocks')]
final class BlocksApi extends ApiFunction
{
    #[Override]
    public function execute(): Result
    {
        try {
            $user = BackendLogin::createUser() ?? throw new RuntimeException(I18n::rawMsg('fluid_error_login'), 403);
            $key = Request::post('module', 'string', '');
            $module = BlockData::module($key) ?? throw new RuntimeException(I18n::rawMsg('fluid_data_unknown_block'), 404);
            $perm = $user->getComplexPerm('modules');
            if (!$user->admin && (!$perm instanceof ModulePermission || !$perm->hasPerm($key))) {
                throw new RuntimeException(I18n::rawMsg('fluid_data_block_perm'), 403);
            }
            if ('parse' === Request::post('action', 'string', 'form')) {
                $f = Request::post('fb', 'array', []);
                $data = self::clean(is_array($f[1] ?? null) ? $f[1] : []);
                $tunes = self::clean(is_array($f[2] ?? null) ? $f[2] : []);
                $item = ['module' => $key, 'data' => FieldForm::withDefaults($module::fields(), $data, $module::variants()), 'tunes' => $tunes];
                self::send(['ok' => true, 'item' => $item, 'label' => BlockData::label($item)]);
            }
            $data = json_decode(Request::post('data', 'string', ''), true);
            $tunes = json_decode(Request::post('tunes', 'string', ''), true);
            self::send(['ok' => true, 'label' => $module::label(),
                'html' => FieldForm::render($module, is_array($data) ? $data : [], is_array($tunes) ? $tunes : [], 'fb', true, lockable: false)]);
        } catch (Throwable $e) {
            $code = (int) $e->getCode();
            self::send(['ok' => false, 'error' => $e->getMessage()], $code >= 400 && $code < 600 ? $code : 400);
        }
    }

    /** Turn lists from forms (0,1,2 …) back into lists */
    private static function clean(array $a): array
    {
        foreach ($a as $k => $v) {
            if (is_array($v)) {
                $v = self::clean($v);
                $a[$k] = [] !== $v && array_filter(array_keys($v), is_int(...)) === array_keys($v) ? array_values($v) : $v;
            }
        }

        return $a;
    }

    private static function send(array $data, int $status = 200): never
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
        http_response_code($status);
        Response::sendJson($data);
        exit;
    }
}
