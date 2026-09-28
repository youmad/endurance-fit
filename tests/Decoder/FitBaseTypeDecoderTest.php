<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Tests\Decoder;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Youmad\Endurance\Fit\Decoded\DecodedFieldElements;
use Youmad\Endurance\Fit\Decoded\InvalidFieldElement;
use Youmad\Endurance\Fit\Decoded\UndecodableFieldValue;
use Youmad\Endurance\Fit\Decoded\ValidFieldElement;
use Youmad\Endurance\Fit\Decoder\FitBaseTypeDecoder;
use Youmad\Endurance\Fit\Raw\FitArchitecture;
use Youmad\Endurance\Fit\Raw\FitBaseType;
use Youmad\Endurance\Fit\Raw\FitBaseTypeKind;
use Youmad\Endurance\Fit\Raw\RawFieldValue;

final class FitBaseTypeDecoderTest extends TestCase
{
    /**
     * @return iterable<string, array{
     *     int,
     *     FitArchitecture,
     *     string,
     *     int|string
     * }>
     */
    public static function integerScalarProvider(): iterable
    {
        yield 'enum' => [
            0x00,
            FitArchitecture::LittleEndian,
            '2A',
            42,
        ];

        yield 'signed int8' => [
            0x01,
            FitArchitecture::LittleEndian,
            'FE',
            -2,
        ];

        yield 'unsigned int8' => [
            0x02,
            FitArchitecture::LittleEndian,
            'FE',
            254,
        ];

        yield 'signed int16 little endian' => [
            0x83,
            FitArchitecture::LittleEndian,
            'FEFF',
            -2,
        ];

        yield 'signed int16 big endian' => [
            0x83,
            FitArchitecture::BigEndian,
            'FFFE',
            -2,
        ];

        yield 'unsigned int16 little endian' => [
            0x84,
            FitArchitecture::LittleEndian,
            '3412',
            4660,
        ];

        yield 'unsigned int16 big endian' => [
            0x84,
            FitArchitecture::BigEndian,
            '1234',
            4660,
        ];

        yield 'signed int32 little endian' => [
            0x85,
            FitArchitecture::LittleEndian,
            'FEFFFFFF',
            -2,
        ];

        yield 'unsigned int32 little endian' => [
            0x86,
            FitArchitecture::LittleEndian,
            'FEFFFFFF',
            4_294_967_294,
        ];

        yield 'unsigned int8 zero' => [
            0x0A,
            FitArchitecture::LittleEndian,
            '2A',
            42,
        ];

        yield 'unsigned int16 zero' => [
            0x8B,
            FitArchitecture::LittleEndian,
            '3412',
            4660,
        ];

        yield 'unsigned int32 zero' => [
            0x8C,
            FitArchitecture::LittleEndian,
            '78563412',
            305_419_896,
        ];

        yield 'byte' => [
            0x0D,
            FitArchitecture::LittleEndian,
            '2A',
            42,
        ];

        yield 'signed int64 little endian' => [
            0x8E,
            FitArchitecture::LittleEndian,
            'FEFFFFFFFFFFFFFF',
            -2,
        ];

        yield 'signed int64 big endian' => [
            0x8E,
            FitArchitecture::BigEndian,
            'FFFFFFFFFFFFFFFE',
            -2,
        ];

        yield 'unsigned int64 above PHP integer' => [
            0x8F,
            FitArchitecture::LittleEndian,
            'FEFFFFFFFFFFFFFF',
            '18446744073709551614',
        ];

        yield 'unsigned int64 zero' => [
            0x90,
            FitArchitecture::LittleEndian,
            '0100000000000000',
            1,
        ];
    }

    /**
     * @return iterable<string, array{
     *     int,
     *     FitArchitecture,
     *     string,
     *     float
     * }>
     */
    public static function floatProvider(): iterable
    {
        yield 'float32 little endian' => [
            0x88,
            FitArchitecture::LittleEndian,
            pack('g', 12.5),
            12.5,
        ];

        yield 'float32 big endian' => [
            0x88,
            FitArchitecture::BigEndian,
            pack('G', -3.25),
            -3.25,
        ];

        yield 'float64 little endian' => [
            0x89,
            FitArchitecture::LittleEndian,
            pack('e', 125.125),
            125.125,
        ];

        yield 'float64 big endian' => [
            0x89,
            FitArchitecture::BigEndian,
            pack('E', -25.5),
            -25.5,
        ];
    }

    /**
     * @return iterable<string, array{
     *     int,
     *     FitArchitecture,
     *     string
     * }>
     */
    public static function invalidScalarProvider(): iterable
    {
        yield 'enum' => [
            0x00,
            FitArchitecture::LittleEndian,
            'FF',
        ];

        yield 'signed int8' => [
            0x01,
            FitArchitecture::LittleEndian,
            '7F',
        ];

        yield 'unsigned int8' => [
            0x02,
            FitArchitecture::LittleEndian,
            'FF',
        ];

        yield 'signed int16 little endian' => [
            0x83,
            FitArchitecture::LittleEndian,
            'FF7F',
        ];

        yield 'signed int16 big endian' => [
            0x83,
            FitArchitecture::BigEndian,
            '7FFF',
        ];

        yield 'unsigned int16' => [
            0x84,
            FitArchitecture::LittleEndian,
            'FFFF',
        ];

        yield 'signed int32 little endian' => [
            0x85,
            FitArchitecture::LittleEndian,
            'FFFFFF7F',
        ];

        yield 'signed int32 big endian' => [
            0x85,
            FitArchitecture::BigEndian,
            '7FFFFFFF',
        ];

        yield 'unsigned int32' => [
            0x86,
            FitArchitecture::LittleEndian,
            'FFFFFFFF',
        ];

        yield 'float32' => [
            0x88,
            FitArchitecture::LittleEndian,
            'FFFFFFFF',
        ];

        yield 'float64' => [
            0x89,
            FitArchitecture::LittleEndian,
            'FFFFFFFFFFFFFFFF',
        ];

        yield 'unsigned int8 zero' => [
            0x0A,
            FitArchitecture::LittleEndian,
            '00',
        ];

        yield 'unsigned int16 zero' => [
            0x8B,
            FitArchitecture::LittleEndian,
            '0000',
        ];

        yield 'unsigned int32 zero' => [
            0x8C,
            FitArchitecture::LittleEndian,
            '00000000',
        ];

        yield 'byte' => [
            0x0D,
            FitArchitecture::LittleEndian,
            'FF',
        ];

        yield 'signed int64 little endian' => [
            0x8E,
            FitArchitecture::LittleEndian,
            'FFFFFFFFFFFFFF7F',
        ];

        yield 'signed int64 big endian' => [
            0x8E,
            FitArchitecture::BigEndian,
            '7FFFFFFFFFFFFFFF',
        ];

        yield 'unsigned int64' => [
            0x8F,
            FitArchitecture::LittleEndian,
            'FFFFFFFFFFFFFFFF',
        ];

        yield 'unsigned int64 zero' => [
            0x90,
            FitArchitecture::LittleEndian,
            '0000000000000000',
        ];
    }

    #[DataProvider('integerScalarProvider')]
    public function testDecodesIntegerScalar(
        int $definitionByte,
        FitArchitecture $architecture,
        string $hex,
        int|string $expected,
    ): void {
        $value = $this->decodeElements(
            definitionByte: $definitionByte,
            architecture: $architecture,
            bytes: $this->bytes($hex),
        );

        $element = $value->elements()[0];

        self::assertInstanceOf(
            ValidFieldElement::class,
            $element,
        );

        self::assertSame(
            $expected,
            $element->value,
        );
    }

    private function decodeElements(
        int $definitionByte,
        FitArchitecture $architecture,
        string $bytes,
    ): DecodedFieldElements {
        $value = (new FitBaseTypeDecoder())->decode(
            baseType: FitBaseType::fromDefinitionByte(
                $definitionByte,
            ),
            architecture: $architecture,
            rawValue: RawFieldValue::fromBytes(
                $bytes,
            ),
        );

        self::assertInstanceOf(
            DecodedFieldElements::class,
            $value,
        );

        return $value;
    }

    private function bytes(string $hex): string
    {
        $bytes = hex2bin($hex);

        self::assertIsString($bytes);

        return $bytes;
    }

    #[DataProvider('floatProvider')]
    public function testDecodesFloatingPointValue(
        int $definitionByte,
        FitArchitecture $architecture,
        string $bytes,
        float $expected,
    ): void {
        $value = $this->decodeElements(
            definitionByte: $definitionByte,
            architecture: $architecture,
            bytes: $bytes,
        );

        $element = $value->elements()[0];

        self::assertInstanceOf(
            ValidFieldElement::class,
            $element,
        );

        self::assertSame(
            $expected,
            $element->value,
        );
    }

    #[DataProvider('invalidScalarProvider')]
    public function testRecognizesInvalidScalarSentinel(
        int $definitionByte,
        FitArchitecture $architecture,
        string $hex,
    ): void {
        $value = $this->decodeElements(
            definitionByte: $definitionByte,
            architecture: $architecture,
            bytes: $this->bytes($hex),
        );

        self::assertInstanceOf(
            InvalidFieldElement::class,
            $value->elements()[0],
        );
    }

    public function testDecodesInvalidArrayElementsIndependently(): void
    {
        $value = $this->decodeElements(
            definitionByte: 0x84,
            architecture: FitArchitecture::LittleEndian,
            bytes: $this->bytes(
                '0100FFFF0200',
            ),
        );

        self::assertCount(
            3,
            $value->elements(),
        );

        self::assertInstanceOf(
            ValidFieldElement::class,
            $value->elements()[0],
        );

        self::assertSame(
            1,
            $value->elements()[0]->value,
        );

        self::assertInstanceOf(
            InvalidFieldElement::class,
            $value->elements()[1],
        );

        self::assertInstanceOf(
            ValidFieldElement::class,
            $value->elements()[2],
        );

        self::assertSame(
            2,
            $value->elements()[2]->value,
        );
    }

    public function testMixedByteArrayPreservesFFAsBinaryData(): void
    {
        $value = $this->decodeElements(
            definitionByte: 0x0D,
            architecture: FitArchitecture::LittleEndian,
            bytes: $this->bytes('01FF02'),
        );

        self::assertSame(
            [
                1,
                255,
                2,
            ],
            array_map(
                static function ($element): int {
                    self::assertInstanceOf(
                        ValidFieldElement::class,
                        $element,
                    );

                    self::assertIsInt(
                        $element->value,
                    );

                    return $element->value;
                },
                $value->elements(),
            ),
        );
    }

    public function testAllInvalidByteArrayIsInvalid(): void
    {
        $value = $this->decodeElements(
            definitionByte: 0x0D,
            architecture: FitArchitecture::LittleEndian,
            bytes: $this->bytes('FFFF'),
        );

        self::assertInstanceOf(
            InvalidFieldElement::class,
            $value->elements()[0],
        );

        self::assertInstanceOf(
            InvalidFieldElement::class,
            $value->elements()[1],
        );
    }

    public function testDecodesNullTerminatedString(): void
    {
        $value = $this->decodeElements(
            definitionByte: 0x07,
            architecture: FitArchitecture::LittleEndian,
            bytes: "Edge 840\0\0",
        );

        self::assertCount(
            1,
            $value->elements(),
        );

        $element = $value->elements()[0];

        self::assertInstanceOf(
            ValidFieldElement::class,
            $element,
        );

        self::assertSame(
            'Edge 840',
            $element->value,
        );
    }

    public function testDecodesStringArraySeparatedByNullBytes(): void
    {
        $value = $this->decodeElements(
            definitionByte: 0x07,
            architecture: FitArchitecture::LittleEndian,
            bytes: "left\0right\0",
        );

        self::assertCount(
            2,
            $value->elements(),
        );

        self::assertInstanceOf(ValidFieldElement::class, $value->elements()[0]);
        self::assertInstanceOf(ValidFieldElement::class, $value->elements()[1]);

        self::assertSame(
            'left',
            $value->elements()[0]->value,
        );

        self::assertSame(
            'right',
            $value->elements()[1]->value,
        );
    }

    public function testAllNullStringIsInvalid(): void
    {
        $value = $this->decodeElements(
            definitionByte: 0x07,
            architecture: FitArchitecture::LittleEndian,
            bytes: "\0\0\0",
        );

        self::assertInstanceOf(
            InvalidFieldElement::class,
            $value->elements()[0],
        );
    }

    public function testDecodesMinimumSignedInt64(): void
    {
        $value = $this->decodeElements(
            definitionByte: 0x8E,
            architecture: FitArchitecture::LittleEndian,
            bytes: $this->bytes(
                '0000000000000080',
            ),
        );

        $element = $value->elements()[0];

        self::assertInstanceOf(
            ValidFieldElement::class,
            $element,
        );

        self::assertSame(
            PHP_INT_MIN,
            $element->value,
        );
    }

    public function testUnknownBaseTypeRemainsUndecodable(): void
    {
        $baseType = FitBaseType::fromDefinitionByte(
            0x1F,
        );

        $rawValue = RawFieldValue::fromBytes(
            "\x12\x34",
        );

        $value = (new FitBaseTypeDecoder())->decode(
            baseType: $baseType,
            architecture: FitArchitecture::LittleEndian,
            rawValue: $rawValue,
        );

        self::assertInstanceOf(
            UndecodableFieldValue::class,
            $value,
        );

        self::assertSame(
            $rawValue,
            $value->rawValue,
        );

        self::assertStringContainsString(
            'Unknown FIT base type',
            $value->reason,
        );
    }

    public function testMisalignedFieldRemainsUndecodable(): void
    {
        $rawValue = RawFieldValue::fromBytes(
            "\x01\x02\x03",
        );

        $value = (new FitBaseTypeDecoder())->decode(
            baseType: FitBaseType::fromDefinitionByte(
                0x84,
            ),
            architecture: FitArchitecture::LittleEndian,
            rawValue: $rawValue,
        );

        self::assertInstanceOf(
            UndecodableFieldValue::class,
            $value,
        );

        self::assertSame(
            $rawValue,
            $value->rawValue,
        );

        self::assertStringContainsString(
            'not divisible',
            $value->reason,
        );
    }

    public function testDefinitionByteMapsToKnownBaseTypeKind(): void
    {
        $baseType = FitBaseType::fromDefinitionByte(
            0x86,
        );

        self::assertSame(
            FitBaseTypeKind::UnsignedInt32,
            $baseType->kind(),
        );
    }

    public function testCompiledPlanMatchesGenericDecoder(): void
    {
        $cases = [
            'unsigned int8' => [
                0x02,
                FitArchitecture::LittleEndian,
                "\xFE",
            ],
            'signed int16 little endian' => [
                0x83,
                FitArchitecture::LittleEndian,
                "\xFE\xFF",
            ],
            'signed int16 big endian' => [
                0x83,
                FitArchitecture::BigEndian,
                "\xFF\xFE",
            ],
            'unsigned int32 little endian' => [
                0x86,
                FitArchitecture::LittleEndian,
                "\x78\x56\x34\x12",
            ],
            'float32 big endian' => [
                0x88,
                FitArchitecture::BigEndian,
                pack('G', -3.25),
            ],
            'invalid unsigned int16' => [
                0x84,
                FitArchitecture::LittleEndian,
                "\xFF\xFF",
            ],
            'unsigned int16 array' => [
                0x84,
                FitArchitecture::LittleEndian,
                "\x01\x00\xFF\xFF\x02\x00",
            ],
            'mixed byte array' => [
                0x0D,
                FitArchitecture::LittleEndian,
                "\x01\xFF\x02",
            ],
            'string' => [
                0x07,
                FitArchitecture::LittleEndian,
                "left\0right\0",
            ],
            'signed int64' => [
                0x8E,
                FitArchitecture::LittleEndian,
                "\xFE\xFF\xFF\xFF\xFF\xFF\xFF\xFF",
            ],
            'unknown base type' => [
                0x1F,
                FitArchitecture::LittleEndian,
                "\x12\x34",
            ],
            'misaligned field' => [
                0x84,
                FitArchitecture::LittleEndian,
                "\x01\x02\x03",
            ],
        ];

        $decoder = new FitBaseTypeDecoder();

        foreach (
            $cases as $name => [
                $definitionByte,
                $architecture,
                $bytes,
            ]
        ) {
            $baseType = FitBaseType::fromDefinitionByte(
                $definitionByte,
            );
            $rawValue = RawFieldValue::fromBytes($bytes);

            self::assertEquals(
                $decoder->decode(
                    baseType: $baseType,
                    architecture: $architecture,
                    rawValue: $rawValue,
                ),
                $decoder->decodeCompiled(
                    plan: $decoder->compile(
                        baseType: $baseType,
                        architecture: $architecture,
                        fieldSize: strlen($bytes),
                    ),
                    rawValue: $rawValue,
                ),
                $name,
            );
        }
    }
}
