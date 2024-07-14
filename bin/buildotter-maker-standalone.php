<?php

declare(strict_types=1);

use Buildotter\MakerStandalone\AppFactory;

require_once __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::make();
$app->run();
