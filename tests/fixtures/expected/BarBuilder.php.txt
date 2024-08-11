<?php

declare(strict_types=1);

namespace Buildotter\Tests\MakerStandalone\fixtures\expected;

use Buildotter\Core\BuildableWithArgUnpacking;
use Buildotter\Core\Buildatable;
use Buildotter\Tests\MakerStandalone\fixtures\Bar;

/**
 * @implements Buildatable<Bar>
 */
final class BarBuilder implements Buildatable
{
    use BuildableWithArgUnpacking;

    public function __construct(
        public string $value,
    ) {
    }

    public static function random(): static
    {
        $random = \random();

        return new static(/** @TODO: Initialize its properties to commonly used or safe values */);
    }

    public function build(): Bar
    {
        return new Bar(
            $this->value,
        );
    }
}
