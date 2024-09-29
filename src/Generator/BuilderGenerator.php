<?php

declare(strict_types=1);

namespace Buildotter\MakerStandalone\Generator;

use Buildotter\Core\BuildableWithArgUnpacking;
use Buildotter\Core\Buildatable;
use Buildotter\Core\Many;
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

        $class->addMethod('new')
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

    public function generateFunctionsForBuilder(
        PhpFile $functionsFile,
        string $generatedFqcn,
        ReflectionClass $reflectionClass,
        string $builderShortClassName,
    ): PhpFile {
        $functionsFile = clone $functionsFile;

        $functionsFile->setStrictTypes();
        $functionsFile->addUse($generatedFqcn);
        $functionsFile->addUse(Many::class);

        $functionsFile
            ->addFunction(\sprintf('a%s', $reflectionClass->getShortName()))
            ->setReturnType($builderShortClassName)
            ->setBody(\sprintf('return %s::new();', $builderShortClassName));

        $someFromFunction = $functionsFile->addFunction(\sprintf('some%ss', $reflectionClass->getShortName()));
        $someFromFunction->addComment(\sprintf('@return %s[]', $reflectionClass->getName()));
        $someFromFunction->addParameter('numberOfItems')
            ->setType('int|null')
            ->setDefaultValue(null);
        $someFromFunction
            ->setReturnType('array')
            ->setBody(\sprintf('return Many::from(%s::class, $numberOfItems);', $builderShortClassName));

        $someToBuildFromFunction = $functionsFile->addFunction(\sprintf('some%ssToBuild', $reflectionClass->getShortName()));
        $someToBuildFromFunction->addComment(\sprintf('@return %s[]', $builderShortClassName));
        $someToBuildFromFunction->addParameter('numberOfItems')
            ->setType('int|null')
            ->setDefaultValue(null);
        $someToBuildFromFunction
            ->setReturnType('array')
            ->setBody(\sprintf('return Many::toBuildFrom(%s::class, $numberOfItems);', $builderShortClassName));

        return $functionsFile;
    }

    public function generateRandomFunctionBasedOnFaker(PhpFile $file): PhpFile
    {
        if (true === $this->hasFunction($file, 'random')) {
            return $file;
        }

        $file = clone $file;
        $file
            ->addFunction('random')
            ->setReturnType('\Faker\Generator')
            ->setBody(\sprintf('return \Faker\Factory::create();'));

        return $file;
    }

    private function hasFunction(PhpFile $file, string $name): bool
    {
        return \array_key_exists($name, $file->getFunctions());
    }
}
