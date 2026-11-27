<?php

/*
 * Checks that classes based on optional packages (YForm) are not loaded without these packages – without REDAXO.
 *
 *   php tools/check-optional.php
 *
 * Background: the class discovery of REDAXO 6 (`ClassDiscovery`) loads every class below the addon paths. If one
 * of them extends a YForm class (or implements a YForm interface) and YForm is missing, e.g. `migrate` aborts with
 * „Class … not found“. Such files need the guard before the class
 *
 *     if (!class_exists(Elternklasse::class)) { return; }
 */

$optional = ['Yakamara\\YForm\\'];
$root = dirname(__DIR__);
$problems = [];

foreach (['src', 'kits/fluid/src', 'kits/native/src'] as $dir) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$root/$dir", FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ('php' !== $file->getExtension()) {
            continue;
        }
        $code = (string) file_get_contents((string) $file);
        $tokens = token_get_all($code, TOKEN_PARSE);
        $imports = [];
        $namespace = '';
        $guarded = false;
        $n = count($tokens);
        for ($i = 0; $i < $n; ++$i) {
            $t = $tokens[$i];
            if (!is_array($t)) {
                continue;
            }
            if (T_NAMESPACE === $t[0]) {
                $namespace = $tokens[$i + 2][1] ?? '';
            } elseif (T_USE === $t[0] && '' !== $namespace && ($tokens[$i + 2][0] ?? null) === T_NAME_QUALIFIED) {
                $name = $tokens[$i + 2][1];
                $alias = substr($name, (int) strrpos($name, '\\') + 1);
                if (($tokens[$i + 4][0] ?? null) === T_AS) {
                    $alias = $tokens[$i + 6][1];
                }
                $imports[$alias] = $name;
            } elseif (T_STRING === $t[0] && 'class_exists' === $t[1]) {
                $guarded = true;
            } elseif (in_array($t[0], [T_CLASS, T_INTERFACE, T_ENUM], true) && ($tokens[$i - 1][0] ?? null) !== T_DOUBLE_COLON) {
                // collect names after extends/implements up to the opening brace
                $parents = [];
                $resolve = static function (string $p) use ($imports): string {
                    $p = ltrim($p, '\\');
                    $first = explode('\\', $p)[0];

                    return isset($imports[$first]) ? $imports[$first] . substr($p, strlen($first)) : $p;
                };
                for ($j = $i + 1; $j < $n && '{' !== $tokens[$j]; ++$j) {
                    if (is_array($tokens[$j]) && in_array($tokens[$j][0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true) && $j > $i + 2) {
                        $parents[] = $resolve($tokens[$j][1]);
                    }
                }
                // traits in the class body (`use Trait;` – if the trait is missing, loading aborts just the same)
                for (; $j < $n; ++$j) {
                    if (is_array($tokens[$j]) && T_USE === $tokens[$j][0]) {
                        for ($k = $j + 1; $k < $n && ';' !== $tokens[$k] && '{' !== $tokens[$k]; ++$k) {
                            if (is_array($tokens[$k]) && in_array($tokens[$k][0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                                $parents[] = $resolve($tokens[$k][1]);
                            }
                        }
                    }
                }
                foreach ($parents as $p) {
                    foreach ($optional as $prefix) {
                        if (str_starts_with($p, $prefix) && !$guarded) {
                            $problems[] = substr((string) $file, strlen($root) + 1) . ": erbt von $p ohne class_exists-Guard";
                        }
                    }
                }
                break;
            }
        }
    }
}

if ([] !== $problems) {
    fwrite(STDERR, implode("\n", $problems) . "\n");
    exit(1);
}
echo "OK – alle Klassen auf Basis optionaler Pakete sind abgesichert.\n";
