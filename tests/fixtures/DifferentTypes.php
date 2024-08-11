<?php

declare(strict_types=1);

namespace Buildotter\Tests\MakerStandalone\fixtures;

final class DifferentTypes
{
    public function __construct(
        public string $classic,
        public string|int|Bar $union,
        public \Iterator&\Countable $intersection,
    ) {}
}
