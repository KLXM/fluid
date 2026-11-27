<?php

namespace Klxm\Fluid\Field;

use Redaxo\Core\Form\Reference;

use function is_string;
use function preg_match_all;

/** @internal References to pages (redaxo://ID) in link and rich text fields – for the core's usage list */
final class Links
{
    /** @return list<Reference> */
    public static function references(mixed $value): array
    {
        if (!is_string($value) || !preg_match_all('~redaxo://(\d+)~', $value, $m)) {
            return [];
        }
        $out = [];
        foreach ($m[1] as $id) {
            $out['article:' . $id] = Reference::article((int) $id);
        }

        return array_values($out);
    }
}
