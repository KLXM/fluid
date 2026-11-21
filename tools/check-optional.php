<?php
/*
 * Prüft, dass Klassen auf Basis optionaler Pakete (YForm) ohne diese Pakete nicht geladen werden – ohne REDAXO.
 *
 *   php tools/check-optional.php
 *
 * Hintergrund: Die Klassensuche von REDAXO 6 (`ClassDiscovery`) lädt jede Klasse unter den Addon-Pfaden. Erbt eine
 * davon von einer YForm-Klasse (oder implementiert ein YForm-Interface) und YForm fehlt, bricht z. B. `migrate` mit
 * „Class … not found“ ab. Solche Dateien brauchen vor der Klasse den Guard
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
                // Namen nach extends/implements bis zur geschweiften Klammer einsammeln
                $parents = [];
                for ($j = $i + 1; $j < $n && '{' !== $tokens[$j]; ++$j) {
                    if (is_array($tokens[$j]) && in_array($tokens[$j][0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true) && $j > $i + 2) {
                        $p = ltrim($tokens[$j][1], '\\');
                        $first = explode('\\', $p)[0];
                        $parents[] = isset($imports[$first]) ? $imports[$first] . substr($p, strlen($first)) : $p;
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
