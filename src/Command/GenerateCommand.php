<?php

declare(strict_types=1);

namespace Buildotter\MakerStandalone\Command;

use Buildotter\Core\BuildableWithArgUnpacking;
use Buildotter\Core\Buildatable;
use Buildotter\MakerStandalone\Exception\InvalidClassLoaderException;
use Buildotter\MakerStandalone\Reflection\ReflectorFactory;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;
use Roave\BetterReflection\Reflection\ReflectionClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

final class GenerateCommand extends Command
{
    public function __construct()
    {
        parent::__construct('generate');
    }

    protected function configure(): void
    {
        $this->addArgument('class', InputArgument::OPTIONAL, 'The class for which to generate a builder');
        $this->addArgument('generated-namespace', InputArgument::OPTIONAL, 'The namespace of the generated builder.');

        $this->addOption('autoloader', null, InputOption::VALUE_REQUIRED, 'The path to the Composer autoload file', './vendor/autoload.php');
        $this->addOption('generated-folder', null, InputOption::VALUE_REQUIRED, 'The path where to generate the builder.');
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $io = new SymfonyStyle($input, $output);

        $io->info(\sprintf('Using the following autoload file: "%s". If this is not the one you wanted, it may be modified using the ', $input->getOption('autoloader')));

        // @TODO: try to infer generated folder from the namespace
        if (false === \is_string($input->getOption('generated-folder'))) {
            $question = new Question('Where do you want to store the generated builder?');
            $value = $io->askQuestion($question);

            $input->setOption('generated-folder', $value);
        }

        if (false === \is_string($input->getArgument('class'))) {
            $question = new Question('For which class do you need to generate a builder?');
            // @TODO: autocomplete
            $value = $io->askQuestion($question);

            $input->setArgument('class', $value);
        }

        // @TODO: propose a default FQCN
        // @TODO: rename argument to make it clear it is the FQCN
        if (false === \is_string($input->getArgument('generated-namespace'))) {
            $question = new Question('What is the namespace of the generated builder?', 'App\\Fixture\\Builder\\FooBuilder');
            $value = $io->askQuestion($question);

            $input->setArgument('generated-namespace', $value);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $ioError = $io->getErrorStyle();

        try {
            $reflector = ReflectorFactory::createFromAutoloader($input->getOption('autoloader'));
        }
        catch (InvalidClassLoaderException $e) {
            $ioError->error($e->getMessage());
            return Command::FAILURE;
        }

        $reflectionClass = $reflector->reflectClass($input->getArgument('class'));

        $io->text('Generating builder for class ' . $reflectionClass->getName());

        $generatedFolder = $this->getGeneratedFolder($input);
        if (false === \is_string($generatedFolder)) {
            $ioError->error('Missing or invalid generated-folder.');
            return Command::INVALID;
        }

        $generatedNamespace = $this->getGeneratedNamespace($input);
        if (false === \is_string($generatedNamespace)) {
            $ioError->error('Missing or invalid generated-namespace.');
            return Command::INVALID;
        }

        $builderShortClassName = \sprintf('%sBuilder', $reflectionClass->getShortName());

        $file = $this->generateBuilder(
            $generatedNamespace,
            $builderShortClassName,
            $reflectionClass,
        );

        $printer = new PsrPrinter();
        $filesystem = new Filesystem();
        $filesystem->mkdir($generatedFolder);
        if (false === \file_put_contents(\sprintf('%s/%s.php', $generatedFolder, $builderShortClassName), $printer->printFile($file))) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * @return non-empty-string|null
     */
    private function getGeneratedFolder(InputInterface $input): string|null
    {
        $generatedFolder = $input->getOption('generated-folder');

        if (false === \is_string($generatedFolder)) {
            return null;
        }
        if ('' === $generatedFolder) {
            return null;
        }

        return Path::canonicalize($generatedFolder);
    }

    /**
     * @return non-empty-string|null
     */
    private function getGeneratedNamespace(InputInterface $input): string|null
    {
        $generatedNamespace = $input->getArgument('generated-namespace');

        if (false === \is_string($generatedNamespace)) {
            return null;
        }
        if ('' === $generatedNamespace) {
            return null;
        }

        return $generatedNamespace;
    }

    private function generateBuilder(
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
                ->setType($property->getType()?->getName());
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
