<?php

declare(strict_types=1);

// jetbrains/phpstorm-stubs is a dependency of roave/better-reflection.
// It conflicts with php functions like "assert" and defining a custom assert() function is not allowed by php engine.
// It has been greatly inspired by https://github.com/humbug/php-scoper/blob/32bd12e47018d556f40a2797427fdd798077e929/res/get-scoper-phpstorm-stubs.php.
$jetBrainStubs = static function (string $stubsDir = __DIR__ . '/vendor/jetbrains/phpstorm-stubs'): array {
    $ignoredDirectories = [
        $stubsDir . '/tests',
        $stubsDir . '/meta',
    ];
    $files = [];

    $collectFiles = static function (RecursiveIteratorIterator $iterator) use (&$files, $ignoredDirectories): void {
        foreach ($iterator as $fileInfo) {
            if (false === ($fileInfo instanceof \SplFileInfo)) {
                continue;
            }

            if (true === \str_starts_with($fileInfo->getFilename(), '.')
                || true === $fileInfo->isDir()
                || false === $fileInfo->isReadable()
                || 'php' !== $fileInfo->getExtension()
                || 'PhpStormStubsMap.php' === $fileInfo->getFilename()
                // The map needs to be excluded from "exclude-files" as otherwise its namespace cannot be corrected
                // via a patcher
            ) {
                continue;
            }

            foreach ($ignoredDirectories as $ignoredDirectory) {
                if (\str_starts_with($fileInfo->getPathname(), $ignoredDirectory)) {
                    continue 2;
                }
            }

            $files[] = $fileInfo->getPathName();
        }
    };

    $collectFiles(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($stubsDir)));

    return $files;
};

return [
    'expose-global-constants' => false,
    'expose-global-functions' => false,
    'expose-global-classes' => false,
    'exclude-constants' => [
        // Symfony global constants
        '/^SYMFONY\_[\p{L}_]+$/',
    ],
    'exclude-classes' => [
        'Composer\Autoload\ClassLoader',
    ],
    'exclude-namespaces' => [
        'Buildotter\Core',
        'Faker',
    ],
    'exclude-files' => $jetBrainStubs(),
    'patchers' => [
        // Patch the PhpStormStubsMap class to prefix its namespace
        // and avoid breaking the autoloading.
        // It has been greatly inspired by https://github.com/humbug/php-scoper/blob/32bd12e47018d556f40a2797427fdd798077e929/res/create-scoper-phpstorm-stubs-map-patcher.php.
        (static function (): Closure {
            $stubsMapVendorPath = 'vendor/jetbrains/phpstorm-stubs/PhpStormStubsMap.php';
            $stubsMapPath = __DIR__ . '/' . $stubsMapVendorPath;

            $stubsMapOriginalContent = \file_get_contents($stubsMapPath);
            if (false === $stubsMapOriginalContent) {
                throw new \InvalidArgumentException('Could not read the PhpStormStubsMap original content.');
            }

            if (1 !== \preg_match('/class PhpStormStubsMap(?<content>[\s\S]+)/', $stubsMapOriginalContent, $matches)) {
                throw new \InvalidArgumentException('Could not capture the PhpStormStubsMap original content.');
            }

            $stubsMapClassOriginalContent = $matches['content'] ?? throw new \InvalidArgumentException('Undefined content for PhpStormStubsMap.');

            return static function (string $filePath, string $prefix, string $contents) use (
                $stubsMapVendorPath,
                $stubsMapClassOriginalContent,
            ): string {
                if ($filePath !== $stubsMapVendorPath) {
                    return $contents;
                }

                return \preg_replace(
                    '/class PhpStormStubsMap([\s\S]+)/',
                    'class PhpStormStubsMap' . $stubsMapClassOriginalContent,
                    $contents,
                );
            };
        })(),
    ],
];
