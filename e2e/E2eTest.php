<?php

declare(strict_types=1);

namespace Buildotter\E2e\MakerStandalone;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

final class E2eTest extends TestCase
{
    const BIN = __DIR__ . '/../build/buildotter-maker-standalone.phar';

    private string $phpBinaryPath;
    private string $basePath;

    protected function setUp(): void
    {
        $this->phpBinaryPath = $this->phpBinaryPath();

        $this->basePath = $_ENV['BUILDATA_TEST_BASE_PATH'] ?? \sys_get_temp_dir();
    }

    public function test_smoke(): void
    {
        $process = new Process([$this->phpBinaryPath, self::BIN, '--version']);
        $process->run();

        $this->assertProcessIsSuccessful($process);
    }

    public function test_it_generate_simple_case_interactively_and_multiple_use(): void
    {
        $testCaseFolder = $this->prepareIsolatedTestCaseFolder(__DIR__ . '/cases/basic');
        $generatedFolder = \sprintf('%s/src/Fixtures/Builder', $testCaseFolder);

        $process = new Process(
            command: [$this->phpBinaryPath, self::BIN],
            cwd: $testCaseFolder,
        );
        $process->setInput("App\Entity\Topic\n\n\n");
        $process->run();

        $this->assertProcessIsSuccessful($process);

        self::assertFileEquals(__DIR__ . '/cases/basic/expected/Fixtures/Builder/TopicBuilder.php.txt', \sprintf('%s/TopicBuilder.php', $generatedFolder));
        self::assertFileEquals(__DIR__ . '/cases/basic/expected/Fixtures/Builder/data-builders.php.topic-only.txt', \sprintf('%s/data-builders.php', $generatedFolder));

        $process = new Process(
            command: [$this->phpBinaryPath, self::BIN],
            cwd: $testCaseFolder,
        );
        $process->setInput("App\Entity\Book\n\n\n");
        $process->run();

        $this->assertProcessIsSuccessful($process);

        self::assertFileEquals(__DIR__ . '/cases/basic/expected/Fixtures/Builder/BookBuilder.php.txt', \sprintf('%s/BookBuilder.php', $generatedFolder));
        self::assertFileEquals(__DIR__ . '/cases/basic/expected/Fixtures/Builder/TopicBuilder.php.txt', \sprintf('%s/TopicBuilder.php', $generatedFolder));
        self::assertFileEquals(__DIR__ . '/cases/basic/expected/Fixtures/Builder/data-builders.php.txt', \sprintf('%s/data-builders.php', $generatedFolder));
    }

    public function test_it_generate_simple_case_non_interactively(): void
    {
        $testCaseFolder = $this->prepareIsolatedTestCaseFolder(__DIR__ . '/cases/basic');

        $generatedFolder = \sprintf('%s/data-builder', $testCaseFolder);
        $process = new Process(
            command: [
                $this->phpBinaryPath, self::BIN,
                \sprintf('--generated-folder=%s', $generatedFolder),
                'App\Entity\Topic',
                'App\Fixtures\Builder\TopicBuilder',
            ],
            cwd: $testCaseFolder,
        );
        $process->run();

        $this->assertProcessIsSuccessful($process);

        self::assertFileEquals(__DIR__ . '/cases/basic/expected/Fixtures/Builder/TopicBuilder.php.txt', \sprintf('%s/TopicBuilder.php', $generatedFolder));
        // Functions are generated in a specific folder that is configurable.
        self::assertFileEquals(__DIR__ . '/cases/basic/expected/Fixtures/Builder/data-builders.php.topic-only.txt', \sprintf('%s/src/Fixtures/Builder/data-builders.php', $testCaseFolder));
    }

    public function test_it_generate_simple_case_non_interactively_with_option_generated_functions(): void
    {
        $testCaseFolder = $this->prepareIsolatedTestCaseFolder(__DIR__ . '/cases/basic');

        $generatedFolder = \sprintf('%s/data-builder', $testCaseFolder);
        $process = new Process(
            command: [
                $this->phpBinaryPath, self::BIN,
                \sprintf('--generated-folder=%s', $generatedFolder),
                \sprintf('--generated-functions=%s/functions.php', $generatedFolder),
                'App\Entity\Topic',
                'App\Fixtures\Builder\TopicBuilder',
            ],
            cwd: $testCaseFolder,
        );
        $process->run();

        $this->assertProcessIsSuccessful($process);

        self::assertFileEquals(__DIR__ . '/cases/basic/expected/Fixtures/Builder/TopicBuilder.php.txt', \sprintf('%s/TopicBuilder.php', $generatedFolder));
        self::assertFileEquals(__DIR__ . '/cases/basic/expected/Fixtures/Builder/data-builders.php.topic-only.txt', \sprintf('%s/functions.php', $generatedFolder));
    }

    public function test_it_generate_simple_case_non_interactively_with_option_no_generated_random_function(): void
    {
        $testCaseFolder = $this->prepareIsolatedTestCaseFolder(__DIR__ . '/cases/basic');

        $generatedFolder = \sprintf('%s/data-builder', $testCaseFolder);
        $process = new Process(
            command: [
                $this->phpBinaryPath, self::BIN,
                \sprintf('--generated-folder=%s', $generatedFolder),
                '--no-generated-random-function',
                'App\Entity\Topic',
                'App\Fixtures\Builder\TopicBuilder',
            ],
            cwd: $testCaseFolder,
        );
        $process->run();

        $this->assertProcessIsSuccessful($process);

        self::assertFileEquals(__DIR__ . '/cases/basic/expected/Fixtures/Builder/TopicBuilder.php.txt', \sprintf('%s/TopicBuilder.php', $generatedFolder));
        // Functions are generated in a specific folder that is configurable.
        self::assertFileEquals(__DIR__ . '/cases/basic/expected/Fixtures/Builder/data-builders.php.no-random.txt', \sprintf('%s/src/Fixtures/Builder/data-builders.php', $testCaseFolder));
    }

    public function test_it_generate_simple_case_non_interactively_with_option_no_generated_functions(): void
    {
        $testCaseFolder = $this->prepareIsolatedTestCaseFolder(__DIR__ . '/cases/basic');

        $generatedFolder = \sprintf('%s/data-builder', $testCaseFolder);
        $process = new Process(
            command: [
                $this->phpBinaryPath, self::BIN,
                \sprintf('--generated-folder=%s', $generatedFolder),
                '--no-generated-functions',
                'App\Entity\Topic',
                'App\Fixtures\Builder\TopicBuilder',
            ],
            cwd: $testCaseFolder,
        );
        $process->run();

        $this->assertProcessIsSuccessful($process);

        self::assertFileEquals(__DIR__ . '/cases/basic/expected/Fixtures/Builder/TopicBuilder.php.txt', \sprintf('%s/TopicBuilder.php', $generatedFolder));
        self::assertFileDoesNotExist(\sprintf('%s/src/Fixtures/Builder/data-builders.php', $testCaseFolder));
        self::assertFileDoesNotExist(\sprintf('%s/data-builders.php', $generatedFolder));
    }

    private function phpBinaryPath(): string
    {
        $phpBinaryFinder = new PhpExecutableFinder();

        $phpBinaryPath = $phpBinaryFinder->find();
        if (false === \is_string($phpBinaryPath)) {
            throw new \RuntimeException('Could not find PHP binary.');
        }

        return $phpBinaryPath;
    }

    private function isolatedCaseFolder(): string
    {
        return Path::canonicalize(\sprintf('%s/%s', $this->basePath, \uniqid()));
    }

    private function copyCaseToIsolatedCaseFolder(string $case, string $isolated): void
    {
        $filesystem = new Filesystem();
        $filesystem->mirror($case, $isolated);
    }

    private function prepareIsolatedTestCaseFolder(string $case): string
    {
        $isolated = $this->isolatedCaseFolder();
        $this->composerInstall($case);
        $this->copyCaseToIsolatedCaseFolder($case, $isolated);

        return $isolated;
    }

    private function composerInstall(string $path): void
    {
        if (true === \file_exists(\sprintf('%s/vendor/autoload.php', $path))) {
            return;
        }

        $process = new Process(
            command: ['composer', 'install'],
            cwd: $path,
        );
        $process->mustRun();
    }

    private function assertProcessIsSuccessful(Process $process): void
    {
        self::assertTrue($process->isSuccessful(), \sprintf('Process was not successful. Error output:%s', $process->getErrorOutput()));
        self::assertTrue($process->isTerminated(), 'Process was not terminated');
        self::assertEmpty($process->getErrorOutput(), 'Process should not have written to error output.');
    }
}
