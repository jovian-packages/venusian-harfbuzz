<?php

declare(strict_types=1);

if (! extension_loaded('harfbuzz')) {
    throw new RuntimeException('ext-harfbuzz is not loaded: build and enable php-io-extensions/harfbuzz for this PHP.');
}

/** A directory for the fonts one test writes, removed after the test. */
function fontDirectory(): string
{
    $directory = sys_get_temp_dir().'/venusian-harfbuzz-'.getmypid().'-'.bin2hex(random_bytes(4));
    mkdir($directory);
    register_shutdown_function(function () use ($directory): void {
        array_map('unlink', glob($directory.'/*') ?: []);
        @rmdir($directory);
    });

    return $directory;
}
