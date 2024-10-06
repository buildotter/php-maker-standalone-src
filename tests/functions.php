<?php

declare(strict_types=1);

/**
 * @return non-empty-string
 */
function generatedBuildotterTestBasePath(): string
{
    $path = $_ENV['BUILDOTTER_TEST_BASE_PATH'] ?? \sys_get_temp_dir();
    \assert(\is_string($path));
    \assert('' !== $path);

    return $path;
}
