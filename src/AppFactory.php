<?php

declare(strict_types=1);

namespace Buildotter\MakerStandalone;

use Buildotter\MakerStandalone\Generator\BuilderGenerator;
use Symfony\Component\Console\Application;

final class AppFactory
{
    public static function make(): Application
    {
        $app = new Application('Buildotter Maker Standalone', '@compiled-git-version@');

        $command = new Command\GenerateCommand(new BuilderGenerator());
        $app->add($command);
        $app->setDefaultCommand($command->getName() ?? 'generate', true);

        return $app;
    }
}
