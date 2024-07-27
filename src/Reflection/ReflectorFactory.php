<?php

declare(strict_types=1);

namespace Buildotter\MakerStandalone\Reflection;

use Buildotter\MakerStandalone\Exception\InvalidClassLoaderException;
use Composer\Autoload\ClassLoader;
use Roave\BetterReflection\BetterReflection;
use Roave\BetterReflection\Reflector\DefaultReflector;
use Roave\BetterReflection\Reflector\Reflector;
use Roave\BetterReflection\SourceLocator\Type\ComposerSourceLocator;

final class ReflectorFactory
{
    public static function createFromAutoloader(string $autoloaderPath): Reflector
    {
        $classLoader = require $autoloaderPath;

        if (false === ($classLoader instanceof ClassLoader)) {
            throw new InvalidClassLoaderException('The autoloader must be an instance of Composer\Autoload\ClassLoader');
        }

        $astLocator = (new BetterReflection())->astLocator();
        return new DefaultReflector(new ComposerSourceLocator($classLoader, $astLocator));
    }
}
