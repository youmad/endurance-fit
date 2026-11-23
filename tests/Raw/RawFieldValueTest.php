<?php

declare(strict_types=1);

namespace Endurance\Fit\Tests\Raw;

use Endurance\Fit\Exception\InvalidRawFieldValue;
use Endurance\Fit\Raw\RawFieldValue;
use PHPUnit\Framework\TestCase;

final class RawFieldValueTest extends TestCase
{
    public function testPreservesExactBinaryBytes(): void
    {
        $bytes = "\x00\x7F\xFF";

        $value = RawFieldValue::fromBytes(
            $bytes,
        );

        self::assertSame(
            $bytes,
            $value->bytes(),
        );

        self::assertSame(
            3,
            $value->size(),
        );
    }

    public function testRawValueCannotBeEmpty(): void
    {
        $this->expectException(
            InvalidRawFieldValue::class,
        );

        RawFieldValue::fromBytes('');
    }
}
