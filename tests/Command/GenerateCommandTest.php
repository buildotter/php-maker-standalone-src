<?php

declare(strict_types=1);

namespace Buildotter\Tests\MakerStandalone\Command;

use Buildotter\MakerStandalone\Command\GenerateCommand;
use Buildotter\MakerStandalone\Generator\BuilderGenerator;
use Buildotter\Tests\MakerStandalone\fixtures\Bar;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

final class GenerateCommandTest extends TestCase
{
    private string $basePath;

    protected function setUp(): void
    {
        $this->basePath = \generatedBuildotterTestBasePath();
    }

    public function test_it_should_generate_builder(): void
    {
        $builderFolder = Path::canonicalize(\sprintf('%s/%s', $this->basePath, \uniqid()));
        $functionsFile = $builderFolder . '/data-builders.php';

        $command = new GenerateCommand(new BuilderGenerator());
        $commandTester = new CommandTester($command);
        $commandTester->execute(
            [
                'class' => Bar::class,
                'generated-class' => 'Buildotter\Tests\MakerStandalone\fixtures\expected\BarBuilder',
                '--autoloader' => __DIR__ . '/../../vendor/autoload.php',
                '--generated-folder' => $builderFolder,
                '--generated-functions' => $functionsFile,
            ],
            [
                'capture_stderr_separately' => true,
            ],
        );

        $commandTester->assertCommandIsSuccessful();
        self::assertEmpty($commandTester->getErrorOutput());

        $file = \sprintf('%s/BarBuilder.php', $builderFolder);
        self::assertFileExists($file);
        self::assertFileEquals(\sprintf('%s/../fixtures/expected/BarBuilder.php.txt', __DIR__), $file);
        self::assertFileExists($functionsFile);
        self::assertFileEquals(\sprintf('%s/fixtures/expected/data-builders.php.txt', __DIR__), $functionsFile);
    }

    public function test_it_should_not_generate_functions_when_no_generated_functions_option_is_used(): void
    {
        $builderFolder = Path::canonicalize(\sprintf('%s/%s', $this->basePath, \uniqid()));
        $functionsFile = $builderFolder . '/data-builders.php';

        $command = new GenerateCommand(new BuilderGenerator());
        $commandTester = new CommandTester($command);
        $commandTester->execute(
            [
                'class' => Bar::class,
                'generated-class' => 'Buildotter\Tests\MakerStandalone\fixtures\expected\BarBuilder',
                '--autoloader' => __DIR__ . '/../../vendor/autoload.php',
                '--generated-folder' => $builderFolder,
                '--generated-functions' => $functionsFile,
                '--no-generated-functions' => true,
            ],
            [
                'capture_stderr_separately' => true,
            ],
        );

        $commandTester->assertCommandIsSuccessful();
        self::assertEmpty($commandTester->getErrorOutput());

        self::assertFileDoesNotExist($functionsFile);
        $file = \sprintf('%s/BarBuilder.php', $builderFolder);
        self::assertFileExists($file);
        self::assertFileEquals(\sprintf('%s/../fixtures/expected/BarBuilder.php.txt', __DIR__), $file);
    }

    public function test_it_should_append_functions_to_existing_functions_file(): void
    {
        $builderFolder = Path::canonicalize(\sprintf('%s/%s', $this->basePath, \uniqid()));
        $functionsFile = $builderFolder . '/data-builders.php';
        $filesystem = new Filesystem();
        $filesystem->copy(__DIR__ . '/fixtures/data-builders.php.append.txt', $functionsFile);

        $command = new GenerateCommand(new BuilderGenerator());
        $commandTester = new CommandTester($command);
        $commandTester->execute(
            [
                'class' => Bar::class,
                'generated-class' => 'Buildotter\Tests\MakerStandalone\fixtures\expected\BarBuilder',
                '--autoloader' => __DIR__ . '/../../vendor/autoload.php',
                '--generated-folder' => $builderFolder,
                '--generated-functions' => $functionsFile,
            ],
            [
                'capture_stderr_separately' => true,
            ],
        );

        $commandTester->assertCommandIsSuccessful();
        self::assertEmpty($commandTester->getErrorOutput());

        $file = \sprintf('%s/BarBuilder.php', $builderFolder);
        self::assertFileExists($file);
        self::assertFileEquals(\sprintf('%s/../fixtures/expected/BarBuilder.php.txt', __DIR__), $file);
        self::assertFileExists($functionsFile);
        self::assertFileEquals(\sprintf('%s/fixtures/expected/data-builders.php.append.txt', __DIR__), $functionsFile);
    }

    public function test_it_should_not_generate_random_function_when_its_generation_is_disable(): void
    {
        // generate the test
        $builderFolder = Path::canonicalize(\sprintf('%s/%s', $this->basePath, \uniqid()));
        $functionsFile = $builderFolder . '/data-builders.php';

        $command = new GenerateCommand(new BuilderGenerator());
        $commandTester = new CommandTester($command);
        $commandTester->execute(
            [
                'class' => Bar::class,
                'generated-class' => 'Buildotter\Tests\MakerStandalone\fixtures\expected\BarBuilder',
                '--autoloader' => __DIR__ . '/../../vendor/autoload.php',
                '--generated-folder' => $builderFolder,
                '--generated-functions' => $functionsFile,
                '--no-generated-random-function' => true,
            ],
            [
                'capture_stderr_separately' => true,
            ],
        );

        $commandTester->assertCommandIsSuccessful();
        self::assertEmpty($commandTester->getErrorOutput());

        self::assertFileExists($functionsFile);
        self::assertFileEquals(\sprintf('%s/fixtures/expected/data-builders.php.no-random.txt', __DIR__), $functionsFile);
        $file = \sprintf('%s/BarBuilder.php', $builderFolder);
        self::assertFileExists($file);
        self::assertFileEquals(\sprintf('%s/../fixtures/expected/BarBuilder.php.txt', __DIR__), $file);
    }
}
