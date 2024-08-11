<?php

declare(strict_types=1);

namespace Buildotter\Tests\MakerStandalone\fixtures;

final class Foo
{
    public function __construct(
        public string $name,
        public int $number,
        public Bar $bar,
    ) {}
}
