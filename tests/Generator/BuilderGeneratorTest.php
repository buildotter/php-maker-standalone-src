<?php

declare(strict_types=1);

namespace Buildotter\Tests\MakerStandalone\Generator;

use Buildotter\MakerStandalone\Generator\BuilderGenerator;
use Buildotter\Tests\MakerStandalone\fixtures\DifferentTypes;
use Buildotter\Tests\MakerStandalone\fixtures\Foo;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;
use PHPUnit\Framework\TestCase;
use Roave\BetterReflection\Reflection\ReflectionClass;

class BuilderGeneratorTest extends TestCase
{
    /**
     * @dataProvider nominalCases
     *
     * @param array{0: string, 1: string, 2: ReflectionClass} $args
     */
    public function test_it_should_generate_a_builder(
        string $expected,
        array $args,
    ): void {
        $file = $this->builderGenerator()->generateBuilder(...$args);

        self::assertEquals(
            \file_get_contents($expected),
            $this->phpFiletoStringPsrCompliant($file),
        );
    }

    /**
     * @return iterable<string, array{expected: string, args: array{0: string, 1: string, 2: ReflectionClass}}>
     */
    public static function nominalCases(): iterable
    {
        yield 'simple' => [
            'expected' => __DIR__ . '/../fixtures/expected/FooBuilder.php.txt',
            'args' => [
                'Buildotter\Tests\MakerStandalone\fixtures\expected',
                'FooBuilder',
                ReflectionClass::createFromName(Foo::class),
            ],
        ];

        yield 'different types: standard, union, intersection' => [
            'expected' => __DIR__ . '/../fixtures/expected/DifferentTypesBuilder.php.txt',
            'args' => [
                'Buildotter\Tests\MakerStandalone\fixtures\expected',
                'DifferentTypesBuilder',
                ReflectionClass::createFromName(DifferentTypes::class),
            ],
        ];
    }

    public function test_it_should_generate_the_random_function_based_on_faker(): void
    {
        $file = new PhpFile();
        $generated = $this->builderGenerator()->generateRandomFunctionBasedOnFaker($file);

        self::assertEquals(
            \file_get_contents(__DIR__ . '/fixtures/expected/random-function.php.txt'),
            $this->phpFiletoStringPsrCompliant($generated),
        );
    }

    private function phpFiletoStringPsrCompliant(PhpFile $file): string
    {
        return (new PsrPrinter())->printFile($file);
    }

    private function builderGenerator(): BuilderGenerator
    {
        $generator = new BuilderGenerator();

        return $generator;
    }
}
