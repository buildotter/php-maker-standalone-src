<?php

declare(strict_types=1);

namespace Buildotter\Tests\MakerStandalone\fixtures;

final class Bar
{
    public function __construct(
        private string $value,
    ) {}

    public function value(): string
    {
        return $this->value;
    }
}
