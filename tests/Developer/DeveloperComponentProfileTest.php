<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Tests\Developer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Youmad\Endurance\Fit\Developer\DeveloperComponentProfile;
use Youmad\Endurance\Fit\Developer\DeveloperFieldProfile;
use Youmad\Endurance\Fit\Exception\InvalidFitProfile;
use Youmad\Endurance\Fit\Raw\FitBaseType;

final class DeveloperComponentProfileTest extends TestCase
{
    public function testParsesOrderedComponentMetadata(): void
    {
        $components = DeveloperComponentProfile::fromMetadata(
            components: 'instant,total,total',
            bits: '4,8,12',
            accumulate: '0,1,1',
        );

        self::assertCount(3, $components);

        self::assertSame('instant', $components[0]->name);
        self::assertSame(4, $components[0]->bits);
        self::assertFalse($components[0]->accumulated);

        self::assertSame('total', $components[1]->name);
        self::assertSame(8, $components[1]->bits);
        self::assertTrue($components[1]->accumulated);

        self::assertSame('total', $components[2]->name);
        self::assertSame(12, $components[2]->bits);
        self::assertTrue($components[2]->accumulated);
    }

    public function testDefaultsAccumulationToFalse(): void
    {
        $components = DeveloperComponentProfile::fromMetadata(
            components: 'first,second',
            bits: '8,8',
            accumulate: null,
        );

        self::assertFalse($components[0]->accumulated);
        self::assertFalse($components[1]->accumulated);
    }

    public function testRejectsComponentsForNonIntegerBaseType(): void
    {
        $this->expectException(InvalidFitProfile::class);
        $this->expectExceptionMessage('integer-compatible');

        DeveloperFieldProfile::create(
            developerDataIndex: 0,
            fieldDefinitionNumber: 0,
            baseType: FitBaseType::fromDefinitionByte(0x07),
            name: 'packed_text',
            components: 'first,second',
            bits: '8,8',
        );
    }

    /**
     * @return iterable<string, array{?string, ?string, ?string}>
     */
    public static function invalidMetadata(): iterable
    {
        yield 'bits without names' => [
            null,
            '8',
            null,
        ];

        yield 'names without bits' => [
            'value',
            null,
            null,
        ];

        yield 'different counts' => [
            'first,second',
            '8',
            null,
        ];

        yield 'empty name' => [
            'first,,second',
            '8,8,8',
            null,
        ];

        yield 'invalid bit width' => [
            'value',
            '0',
            null,
        ];

        yield 'different accumulation count' => [
            'first,second',
            '8,8',
            '1',
        ];

        yield 'invalid accumulation flag' => [
            'value',
            '8',
            'yes',
        ];
    }

    #[DataProvider('invalidMetadata')]
    public function testRejectsInvalidMetadata(
        ?string $components,
        ?string $bits,
        ?string $accumulate,
    ): void {
        $this->expectException(InvalidFitProfile::class);

        DeveloperComponentProfile::fromMetadata(
            components: $components,
            bits: $bits,
            accumulate: $accumulate,
        );
    }
}
