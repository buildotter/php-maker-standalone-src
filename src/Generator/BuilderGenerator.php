<?php

declare(strict_types=1);

namespace Buildotter\MakerStandalone\Generator;

use Buildotter\Core\BuildableWithArgUnpacking;
use Buildotter\Core\Buildatable;
use Nette\PhpGenerator\PhpFile;
use Roave\BetterReflection\Reflection\ReflectionClass;

final class BuilderGenerator
{
    public function generateBuilder(
        string $generatedNamespace,
        string $shortClassName,
        ReflectionClass $reflectionClass,
    ): PhpFile {
        $file = new PhpFile();
        $file->setStrictTypes();
        $namespace = $file->addNamespace($generatedNamespace);

        $namespace->addUse(BuildableWithArgUnpacking::class);
        $namespace->addUse(Buildatable::class);
        $namespace->addUse($reflectionClass->getName());

        $class = $namespace->addClass($shortClassName);
        $class->addImplement(Buildatable::class)
            ->setFinal()
            ->addComment(\sprintf('@implements Buildatable<%s>', $reflectionClass->getShortName()))
            ->addTrait(BuildableWithArgUnpacking::class);

        $constructor = $class->addMethod('__construct');

        foreach ($reflectionClass->getProperties() as $property) {
            $constructor->addPromotedParameter($property->getName())
                ->setType((string) $property->getType());
        }

        $class->addMethod('random')
            ->setStatic()
            ->setReturnType('static')
            ->setBody(
                <<<'CODE'
$random = \random();

return new static(/** @TODO: Initialize its properties to commonly used or safe values */);
CODE
            );

        $body = \sprintf('return new %s(%s', $reflectionClass->getShortName(), \PHP_EOL);
        foreach ($reflectionClass->getProperties() as $property) {
            $body .= \sprintf('    $this->%s,%s', $property->getName(), \PHP_EOL);
        }
        $body .= ');';

        $class->addMethod('build')
            ->setReturnType($reflectionClass->getName())
            ->setBody($body);

        return $file;
    }
}
