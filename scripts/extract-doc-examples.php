<?php

/**
 * Extracts the fenced `php` blocks of README.md and docs/*.md into build/docs-examples/, one file
 * per block, so they can be analysed by PHPStan (see phpstan-docs.neon and `make lint-docs`).
 *
 * A block is skipped when the line before its fence is `<!-- skip-check -->`.
 */

$root = dirname(__DIR__);
$target = "$root/build/docs-examples";

if (is_dir($target)) {
    foreach (glob("$target/*.php") as $file) {
        unlink($file);
    }
} else {
    mkdir($target, 0777, true);
}

$files = [ "$root/README.md", ...glob("$root/docs/*.md") ];
$count = 0;

foreach ($files as $file) {
    $lines = file($file, FILE_IGNORE_NEW_LINES);
    $name = basename($file, '.md');
    $block = null;
    $start = 0;
    $skip = false;

    foreach ($lines as $i => $line) {
        if ($block === null) {
            if (trim($line) === '```php') {
                $block = [];
                $start = $i + 2;
                $skip = ($lines[$i - 1] ?? '') === '<!-- skip-check -->';
            }

            continue;
        }

        if (trim($line) === '```') {
            if (!$skip) {
                $code = implode("\n", $block) . "\n";

                if (!str_starts_with($code, '<?php')) {
                    $code = "<?php\n\n" . $code;
                }

                file_put_contents(sprintf('%s/%s_L%d.php', $target, $name, $start), $code);
                $count++;
            }

            $block = null;

            continue;
        }

        $block[] = $line;
    }
}

echo "Extracted $count examples to build/docs-examples\n";
