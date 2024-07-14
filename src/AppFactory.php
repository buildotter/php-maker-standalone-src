<?php

declare(strict_types=1);

namespace Buildotter\MakerStandalone;

use Symfony\Component\Console\Application;

final class AppFactory
{
    public static function make(): Application
    {
        $app = new Application();

        $command = new Command\GenerateCommand();
        $app->add($command);
        $app->setDefaultCommand($command->getName() ?? 'generate', true);

        return $app;
    }
}
