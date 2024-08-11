<?php

declare(strict_types=1);

namespace Buildotter\Tests\MakerStandalone\Command;

use Buildotter\MakerStandalone\Command\GenerateCommand;
use Buildotter\MakerStandalone\Generator\BuilderGenerator;
use Buildotter\Tests\MakerStandalone\fixtures\Bar;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Path;

final class GenerateCommandTest extends TestCase
{
    private string $basePath;

    protected function setUp(): void
    {
        $this->basePath = $_ENV['BUILDATA_TEST_BASE_PATH'] ?? \sys_get_temp_dir();
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
}
