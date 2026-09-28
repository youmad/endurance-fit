<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Tests\Raw;

use PHPUnit\Framework\TestCase;
use Youmad\Endurance\Fit\Exception\InvalidRawFieldValue;
use Youmad\Endurance\Fit\Raw\RawFieldValue;

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
