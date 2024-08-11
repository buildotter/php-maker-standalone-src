<?php

declare(strict_types=1);

namespace Buildotter\MakerStandalone\Command;

use Buildotter\Core\BuildableWithArgUnpacking;
use Buildotter\Core\Buildatable;
use Buildotter\MakerStandalone\Exception\InvalidArgumentException;
use Buildotter\MakerStandalone\Exception\InvalidClassLoaderException;
use Buildotter\MakerStandalone\Reflection\ReflectorFactory;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;
use Roave\BetterReflection\Reflection\ReflectionClass;
use Roave\BetterReflection\Reflector\Exception\IdentifierNotFound;
use Roave\BetterReflection\Reflector\Reflector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
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
        $this->addArgument('class', InputArgument::OPTIONAL, 'The class (FQCN) for which to generate a builder');
        $this->addArgument('generated-class', InputArgument::OPTIONAL, 'The FQCN of the generated builder.');

        $this->addOption('autoloader', null, InputOption::VALUE_REQUIRED, 'The path to the Composer autoload file', './vendor/autoload.php');
        $this->addOption('generated-folder', null, InputOption::VALUE_REQUIRED, 'The path where to generate the builder.');
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $io = new SymfonyStyle($input, $output);
        $ioError = $io->getErrorStyle();

        $io->info(\sprintf('Using the following autoload file: "%s". If this is not the one you wanted, it may be modified using the option "--autoloader"', $input->getOption('autoloader')));

        try {
            $reflector = ReflectorFactory::createFromAutoloader($input->getOption('autoloader'));
        }
        catch (InvalidClassLoaderException $e) {
            throw new RuntimeException($e->getMessage());
        }

        if (false === \is_string($input->getArgument('class'))) {
            $question = new Question('For which class do you need to generate a builder? Please enter the Fully Qualified Class Name (FQCN)');
            $question->setValidator(static function (string|null $class) use ($reflector): string {
                if (false === \is_string($class) || '' === $class) {
                    throw new InvalidArgumentException('Invalid class name.');
                }

                try {
                    $reflector->reflectClass($class);
                }
                catch (IdentifierNotFound $_) {
                    throw new InvalidArgumentException(\sprintf('Class "%s" not found.', $class));
                }

                return $class;
            });

            // @TODO: autocomplete
            $input->setArgument('class', $io->askQuestion($question));
        }

        if (false === \is_string($input->getArgument('generated-class'))) {
            $reflectionClass = $this->getReflectionClass($reflector, $input);
            $builderShortClassName = \sprintf('%sBuilder', $reflectionClass->getShortName());

            $question = new Question('What is the FQCN of the generated builder?', \sprintf('App\\Fixture\\Builder\\%s', $builderShortClassName));
            $question->setValidator(static fn (string|null $generatedClass): string => (true === \is_string($generatedClass) && '' !== $generatedClass) ? $generatedClass : throw new InvalidArgumentException('Invalid generated class name.'));

            $input->setArgument('generated-class', $io->askQuestion($question));
        }

        if (false === \is_string($input->getOption('generated-folder'))) {
            // Poor man's solution to infer the generated folder from the FQCN and propose it as default value.
            $generatedNamespace = $this->getGeneratedNamespace($this->getGeneratedFqcn($input));
            $generatedFolder = \preg_replace('/^[^\/]+/', 'src', \str_replace('\\', '/', $generatedNamespace));

            $question = new Question('Where do you want to store the generated builder?', $generatedFolder);

            $input->setOption('generated-folder', $io->askQuestion($question));
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

        try {
            $reflectionClass = $this->getReflectionClass($reflector, $input);
        }
        catch (IdentifierNotFound|InvalidArgumentException $_) {
            $ioError->error(\sprintf('Class "%s" not found.', $input->getArgument('class')));
            return Command::INVALID;
        }
        if (true === $output->isDebug()) {
            $ioError->writeln(\sprintf('[debug] Generating builder for class "%s".', $reflectionClass->getName()));
        }

        $generatedFolder = $this->getGeneratedFolder($input);
        if (false === \is_string($generatedFolder)) {
            $ioError->error('Missing or invalid generated-folder.');
            return Command::INVALID;
        }
        if (true === $output->isDebug()) {
            $ioError->writeln(\sprintf('[debug] It will be generated in folder "%s".', $generatedFolder));
        }

        $generatedFqcn = $this->getGeneratedFqcn($input);
        if (false === \is_string($generatedFqcn)) {
            $ioError->error('Missing or invalid generated-class.');
            return Command::INVALID;
        }
        if (true === $output->isDebug()) {
            $ioError->writeln(\sprintf('[debug] Generated builder is "%s".', $generatedFqcn));
        }

        $builderShortClassName = $this->getBuilderShortClassName($generatedFqcn);

        $file = $this->generateBuilder(
            $this->getGeneratedNamespace($generatedFqcn),
            $builderShortClassName,
            $reflectionClass,
        );

        $printer = new PsrPrinter();
        $filesystem = new Filesystem();
        $filesystem->mkdir($generatedFolder);
        if (false === \file_put_contents(\sprintf('%s/%s.php', $generatedFolder, $builderShortClassName), $printer->printFile($file))) {
            return Command::FAILURE;
        }

        // @TODO: generate the functions (afoo(), someFoo() etc..)

        $io->success(\sprintf(
            'Builder "%s" for class "%s" generated in "%s".',
            $generatedFqcn, $reflectionClass->getName(), $generatedFolder,
        ));

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

        $canonicalized = Path::canonicalize($generatedFolder);
        if ('' === $canonicalized) {
            return null;
        }

        return $canonicalized;
    }

    /**
     * @return non-empty-string|null
     */
    private function getGeneratedFqcn(InputInterface $input): string|null
    {
        $generatedFqcn = $input->getArgument('generated-class');

        if (false === \is_string($generatedFqcn)) {
            return null;
        }
        if ('' === $generatedFqcn) {
            return null;
        }

        return $generatedFqcn;
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

    private function getGeneratedNamespace(string|null $generatedFqcn): string
    {
        if (false === \is_string($generatedFqcn) || '' === $generatedFqcn) {
            throw new InvalidArgumentException('Invalid generated FQCN.');
        }

        $position = \strrpos($generatedFqcn, '\\');
        if (false === $position) {
            throw new InvalidArgumentException('Unable to find namespace from generated FQCN.');
        }
        $generatedNamespace = \substr($generatedFqcn, 0, $position);

        return $generatedNamespace;
    }

    private function getBuilderShortClassName(?string $generatedFqcn): string
    {
        if (false === \is_string($generatedFqcn) || '' === $generatedFqcn) {
            throw new InvalidArgumentException('Invalid generated FQCN.');
        }

        $position = \strrpos($generatedFqcn, '\\');
        if (false === $position) {
            throw new InvalidArgumentException('Unable to find namespace from generated FQCN.');
        }
        $builderShortClassName = \substr($generatedFqcn, $position + 1);

        return $builderShortClassName;
    }

    private function getReflectionClass(Reflector $reflector, InputInterface $input): ReflectionClass
    {
        $class = $input->getArgument('class');
        if (false === \is_string($class) || '' === $class) {
            throw new InvalidArgumentException('Invalid class name.');
        }

        $reflectionClass = $reflector->reflectClass($class);

        return $reflectionClass;
    }
}
