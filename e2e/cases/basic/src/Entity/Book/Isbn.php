<?php

declare(strict_types=1);

namespace App\Entity\Book;

final readonly class Isbn
{
    public function __construct(
        private string $value,
    ) {}

    public function getValue(): string
    {
        return $this->value;
    }
}
