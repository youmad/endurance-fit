<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Tests\Decoder;

use PHPUnit\Framework\TestCase;
use Youmad\Endurance\Fit\Component\ComponentExtractedDataMessage;
use Youmad\Endurance\Fit\Decoded\DecodedDataMessage;
use Youmad\Endurance\Fit\Decoder\FitComponentExtractor;
use Youmad\Endurance\Fit\Decoder\FitDataMessageDecoder;
use Youmad\Endurance\Fit\Decoder\FitProfileNormalizer;
use Youmad\Endurance\Fit\Exception\InvalidFitProfile;
use Youmad\Endurance\Fit\Profile\ComponentProfile;
use Youmad\Endurance\Fit\Profile\FieldProfile;
use Youmad\Endurance\Fit\Profile\FieldTransform;
use Youmad\Endurance\Fit\Profile\InMemoryFitProfileRegistry;
use Youmad\Endurance\Fit\Profile\MessageProfile;
use Youmad\Endurance\Fit\Profile\SubfieldCondition;
use Youmad\Endurance\Fit\Profile\SubfieldProfile;
use Youmad\Endurance\Fit\Profiled\ProfiledDataMessage;
use Youmad\Endurance\Fit\Raw\FitArchitecture;
use Youmad\Endurance\Fit\Raw\FitBaseType;
use Youmad\Endurance\Fit\Raw\MessageDefinition;
use Youmad\Endurance\Fit\Raw\RawDataMessage;
use Youmad\Endurance\Fit\Raw\RawFieldValue;
use Youmad\Endurance\Fit\Raw\RawStandardField;
use Youmad\Endurance\Fit\Raw\StandardFieldDefinition;

final class FitComponentExtractorTest extends TestCase
{
    public function testExtractsComponentsLeastSignificantBitsFirst(): void
    {
        $profile = MessageProfile::create(
            globalMessageNumber: 20,
            name: 'record',
            fields: [
                FieldProfile::create(
                    fieldNumber: 5,
                    name: 'distance',
                    typeName: 'uint32',
                    units: 'm',
                    accumulated: true,
                ),
                FieldProfile::create(
                    fieldNumber: 6,
                    name: 'speed',
                    typeName: 'uint16',
                    units: 'm/s',
                ),
                FieldProfile::create(
                    fieldNumber: 8,
                    name: 'compressed_speed_distance',
                    typeName: 'byte',
                    components: [
                        ComponentProfile::create(
                            targetFieldNumber: 6,
                            bits: 12,
                            transform: FieldTransform::scaleAndOffset(
                                scale: 100,
                            ),
                            units: 'm/s',
                        ),
                        ComponentProfile::create(
                            targetFieldNumber: 5,
                            bits: 12,
                            transform: FieldTransform::scaleAndOffset(
                                scale: 16,
                            ),
                            units: 'm',
                            accumulated: true,
                        ),
                    ],
                ),
            ],
        );

        $message = $this->profiledMessage(
            profile: $profile,
            architecture: FitArchitecture::LittleEndian,
            fields: [
                8 => [
                    'base_type' => 0x0D,
                    'bytes' => "\xBC\x3A\x12",
                ],
            ],
        );

        $extracted = (new FitComponentExtractor())
            ->extract($message);

        self::assertCount(
            2,
            $extracted->components(),
        );

        $speed = $extracted->components()[0];

        self::assertSame(
            6,
            $speed->targetFieldNumber(),
        );

        self::assertSame(
            0,
            $speed->bitOffset,
        );

        self::assertSame(
            12,
            $speed->component->bits,
        );

        self::assertSame(
            0xABC,
            $speed->packedValue,
        );

        self::assertFalse(
            $speed->isAccumulated(),
        );

        self::assertSame(
            'm/s',
            $speed->units(),
        );

        $distance = $extracted->components()[1];

        self::assertSame(
            5,
            $distance->targetFieldNumber(),
        );

        self::assertSame(
            12,
            $distance->bitOffset,
        );

        self::assertSame(
            0x123,
            $distance->packedValue,
        );

        self::assertTrue(
            $distance->isAccumulated(),
        );
    }

    /**
     * @param array<
     *     int,
     *     array{
     *         base_type: int,
     *         bytes: string
     *     }
     * > $fields
     */
    private function profiledMessage(
        MessageProfile $profile,
        FitArchitecture $architecture,
        array $fields,
    ): ProfiledDataMessage {
        $decoded = $this->decodedMessage(
            globalMessageNumber: $profile->globalMessageNumber,
            architecture: $architecture,
            fields: $fields,
        );

        return (
        new FitProfileNormalizer(
            new InMemoryFitProfileRegistry(
                $profile,
            ),
        )
        )->normalize($decoded);
    }

    /**
     * @param array<
     *     int,
     *     array{
     *         base_type: int,
     *         bytes: string
     *     }
     * > $fields
     */
    private function decodedMessage(
        int $globalMessageNumber,
        FitArchitecture $architecture,
        array $fields,
    ): DecodedDataMessage {
        $definitions = [];
        $rawFields = [];

        foreach ($fields as $fieldNumber => $field) {
            $definition = StandardFieldDefinition::create(
                fieldNumber: $fieldNumber,
                size: strlen($field['bytes']),
                baseType: FitBaseType::fromDefinitionByte(
                    $field['base_type'],
                ),
            );

            $definitions[] = $definition;

            $rawFields[] = new RawStandardField(
                definition: $definition,
                value: RawFieldValue::fromBytes(
                    $field['bytes'],
                ),
            );
        }

        $definition = MessageDefinition::create(
            localMessageNumber: 0,
            architecture: $architecture,
            globalMessageNumber: $globalMessageNumber,
            standardFields: $definitions,
        );

        return (
        new FitDataMessageDecoder()
        )->decode(
            RawDataMessage::create(
                sequenceNumber: 1,
                byteOffset: 20,
                recordHeaderByte: 0x00,
                definition: $definition,
                standardFields: $rawFields,
            ),
        );
    }

    public function testAccountsForBigEndianContainerElements(): void
    {
        $profile = MessageProfile::create(
            globalMessageNumber: 20,
            name: 'record',
            fields: [
                FieldProfile::create(
                    fieldNumber: 1,
                    name: 'low_nibble',
                    typeName: 'uint8',
                ),
                FieldProfile::create(
                    fieldNumber: 2,
                    name: 'remaining_bits',
                    typeName: 'uint16',
                ),
                FieldProfile::create(
                    fieldNumber: 8,
                    name: 'packed',
                    typeName: 'uint16',
                    components: [
                        ComponentProfile::create(
                            targetFieldNumber: 1,
                            bits: 4,
                        ),
                        ComponentProfile::create(
                            targetFieldNumber: 2,
                            bits: 12,
                        ),
                    ],
                ),
            ],
        );

        $extracted = (new FitComponentExtractor())
            ->extract(
                $this->profiledMessage(
                    profile: $profile,
                    architecture: FitArchitecture::BigEndian,
                    fields: [
                        8 => [
                            'base_type' => 0x84,
                            'bytes' => "\x12\x34",
                        ],
                    ],
                ),
            );

        self::assertSame(
            0x04,
            $extracted->components()[0]->packedValue,
        );

        self::assertSame(
            0x123,
            $extracted->components()[1]->packedValue,
        );
    }

    public function testSelectedSubfieldReplacesMainComponents(): void
    {
        $mainComponent = ComponentProfile::create(
            targetFieldNumber: 7,
            bits: 8,
        );

        $subfieldComponent = ComponentProfile::create(
            targetFieldNumber: 9,
            bits: 8,
        );

        $subfield = SubfieldProfile::create(
            name: 'gear_change_data',
            typeName: 'uint32',
            conditions: [
                SubfieldCondition::create(
                    referenceFieldNumber: 0,
                    acceptedRawValues: [1],
                ),
            ],
            components: [$subfieldComponent],
        );

        $profile = MessageProfile::create(
            globalMessageNumber: 21,
            name: 'event',
            fields: [
                FieldProfile::create(
                    fieldNumber: 0,
                    name: 'event',
                    typeName: 'event',
                ),
                FieldProfile::create(
                    fieldNumber: 3,
                    name: 'data',
                    typeName: 'uint32',
                    subfields: [$subfield],
                    components: [$mainComponent],
                ),
                FieldProfile::create(
                    fieldNumber: 7,
                    name: 'main_target',
                    typeName: 'uint8',
                ),
                FieldProfile::create(
                    fieldNumber: 9,
                    name: 'front_gear',
                    typeName: 'uint8',
                ),
            ],
        );

        $extracted = (new FitComponentExtractor())
            ->extract(
                $this->profiledMessage(
                    profile: $profile,
                    architecture: FitArchitecture::LittleEndian,
                    fields: [
                        0 => [
                            'base_type' => 0x00,
                            'bytes' => "\x01",
                        ],
                        3 => [
                            'base_type' => 0x86,
                            'bytes' => "\x2A\x00\x00\x00",
                        ],
                    ],
                ),
            );

        self::assertCount(
            1,
            $extracted->components(),
        );

        self::assertSame(
            9,
            $extracted
                ->components()[0]
                ->targetFieldNumber(),
        );

        self::assertSame(
            42,
            $extracted
                ->components()[0]
                ->packedValue,
        );
    }

    public function testSelectsActiveSubfieldForComponentTarget(): void
    {
        $batteryLevel = SubfieldProfile::create(
            name: 'battery_level',
            typeName: 'uint16',
            transform: FieldTransform::scaleAndOffset(
                scale: 1000,
            ),
            units: 'V',
            conditions: [
                SubfieldCondition::create(
                    referenceFieldNumber: 0,
                    acceptedRawValues: [11],
                ),
            ],
        );

        $profile = MessageProfile::create(
            globalMessageNumber: 21,
            name: 'event',
            fields: [
                FieldProfile::create(
                    fieldNumber: 0,
                    name: 'event',
                    typeName: 'event',
                ),
                FieldProfile::create(
                    fieldNumber: 2,
                    name: 'data16',
                    typeName: 'uint16',
                    components: [
                        ComponentProfile::create(
                            targetFieldNumber: 3,
                            bits: 16,
                        ),
                    ],
                ),
                FieldProfile::create(
                    fieldNumber: 3,
                    name: 'data',
                    typeName: 'uint32',
                    subfields: [$batteryLevel],
                ),
            ],
        );

        $extracted = (new FitComponentExtractor())
            ->extract(
                $this->profiledMessage(
                    profile: $profile,
                    architecture: FitArchitecture::LittleEndian,
                    fields: [
                        0 => [
                            'base_type' => 0x00,
                            'bytes' => "\x0B",
                        ],
                        2 => [
                            'base_type' => 0x84,
                            'bytes' => pack('v', 42),
                        ],
                    ],
                ),
            );

        self::assertCount(
            1,
            $extracted->components(),
        );

        $component = $extracted->components()[0];

        self::assertSame(
            $batteryLevel,
            $component->targetSubfield,
        );

        self::assertSame(
            'battery_level',
            $component->name(),
        );

        self::assertSame(
            'uint16',
            $component->typeName(),
        );

        self::assertSame(
            'V',
            $component->units(),
        );
    }

    public function testSkipsContainerContainingOnlyInvalidValues(): void
    {
        $profile = MessageProfile::create(
            globalMessageNumber: 20,
            name: 'record',
            fields: [
                FieldProfile::create(
                    fieldNumber: 1,
                    name: 'first',
                    typeName: 'uint8',
                ),
                FieldProfile::create(
                    fieldNumber: 2,
                    name: 'second',
                    typeName: 'uint8',
                ),
                FieldProfile::create(
                    fieldNumber: 8,
                    name: 'packed',
                    typeName: 'byte',
                    components: [
                        ComponentProfile::create(
                            targetFieldNumber: 1,
                            bits: 8,
                        ),
                        ComponentProfile::create(
                            targetFieldNumber: 2,
                            bits: 8,
                        ),
                    ],
                ),
            ],
        );

        $extracted = (new FitComponentExtractor())
            ->extract(
                $this->profiledMessage(
                    profile: $profile,
                    architecture: FitArchitecture::LittleEndian,
                    fields: [
                        8 => [
                            'base_type' => 0x0D,
                            'bytes' => "\xFF\xFF",
                        ],
                    ],
                ),
            );

        self::assertSame(
            [],
            $extracted->components(),
        );
    }

    public function testPreservesRepeatedTargetComponents(): void
    {
        $profile = MessageProfile::create(
            globalMessageNumber: 132,
            name: 'hr',
            fields: [
                FieldProfile::create(
                    fieldNumber: 9,
                    name: 'event_timestamp',
                    typeName: 'uint32',
                    accumulated: true,
                ),
                FieldProfile::create(
                    fieldNumber: 10,
                    name: 'packed_timestamps',
                    typeName: 'byte',
                    components: [
                        ComponentProfile::create(
                            targetFieldNumber: 9,
                            bits: 4,
                            accumulated: true,
                        ),
                        ComponentProfile::create(
                            targetFieldNumber: 9,
                            bits: 4,
                            accumulated: true,
                        ),
                    ],
                ),
            ],
        );

        $extracted = (new FitComponentExtractor())
            ->extract(
                $this->profiledMessage(
                    profile: $profile,
                    architecture: FitArchitecture::LittleEndian,
                    fields: [
                        10 => [
                            'base_type' => 0x0D,
                            'bytes' => "\xA5",
                        ],
                    ],
                ),
            );

        $timestamps = $extracted
            ->componentsForField(9);

        self::assertCount(
            2,
            $timestamps,
        );

        self::assertSame(
            5,
            $timestamps[0]->packedValue,
        );

        self::assertSame(
            10,
            $timestamps[1]->packedValue,
        );
    }

    public function testStopsWhenRemainingPayloadCannotContainNextComponent(): void
    {
        $profile = MessageProfile::create(
            globalMessageNumber: 20,
            name: 'record',
            fields: [
                FieldProfile::create(
                    fieldNumber: 1,
                    name: 'first',
                    typeName: 'uint8',
                ),
                FieldProfile::create(
                    fieldNumber: 2,
                    name: 'second',
                    typeName: 'uint8',
                ),
                FieldProfile::create(
                    fieldNumber: 8,
                    name: 'packed',
                    typeName: 'byte',
                    components: [
                        ComponentProfile::create(
                            targetFieldNumber: 1,
                            bits: 5,
                        ),
                        ComponentProfile::create(
                            targetFieldNumber: 2,
                            bits: 5,
                        ),
                    ],
                ),
            ],
        );

        $extracted = (new FitComponentExtractor())
            ->extract(
                $this->profiledMessage(
                    profile: $profile,
                    architecture: FitArchitecture::LittleEndian,
                    fields: [
                        8 => [
                            'base_type' => 0x0D,
                            'bytes' => "\xFE",
                        ],
                    ],
                ),
            );

        self::assertCount(
            1,
            $extracted->components(),
        );

        self::assertSame(
            0x1E,
            $extracted->components()[0]->packedValue,
        );
    }

    public function testSignExtendsPackedComponentForSignedTarget(): void
    {
        $profile = MessageProfile::create(
            globalMessageNumber: 20,
            name: 'record',
            fields: [
                FieldProfile::create(
                    fieldNumber: 1,
                    name: 'signed_value',
                    typeName: 'sint16',
                ),
                FieldProfile::create(
                    fieldNumber: 8,
                    name: 'packed',
                    typeName: 'byte',
                    components: [
                        ComponentProfile::create(
                            targetFieldNumber: 1,
                            bits: 8,
                            signed: true,
                        ),
                    ],
                ),
            ],
        );

        $extracted = (new FitComponentExtractor())
            ->extract(
                $this->profiledMessage(
                    profile: $profile,
                    architecture: FitArchitecture::LittleEndian,
                    fields: [
                        8 => [
                            'base_type' => 0x0D,
                            'bytes' => "\xF0",
                        ],
                    ],
                ),
            );

        self::assertSame(
            -16,
            $extracted->components()[0]->packedValue,
        );
    }

    public function testRejectsMissingTargetFieldProfile(): void
    {
        $profile = MessageProfile::create(
            globalMessageNumber: 20,
            name: 'record',
            fields: [
                FieldProfile::create(
                    fieldNumber: 8,
                    name: 'packed',
                    typeName: 'byte',
                    components: [
                        ComponentProfile::create(
                            targetFieldNumber: 99,
                            bits: 8,
                        ),
                    ],
                ),
            ],
        );

        $this->expectException(
            InvalidFitProfile::class,
        );

        $this->expectExceptionMessage(
            'missing target field 99',
        );

        (new FitComponentExtractor())->extract(
            $this->profiledMessage(
                profile: $profile,
                architecture: FitArchitecture::LittleEndian,
                fields: [
                    8 => [
                        'base_type' => 0x0D,
                        'bytes' => "\x2A",
                    ],
                ],
            ),
        );
    }

    public function testExtractsStreamLazily(): void
    {
        $profile = MessageProfile::create(
            globalMessageNumber: 20,
            name: 'record',
            fields: [
                FieldProfile::create(
                    fieldNumber: 1,
                    name: 'value',
                    typeName: 'uint8',
                ),
                FieldProfile::create(
                    fieldNumber: 8,
                    name: 'packed',
                    typeName: 'byte',
                    components: [
                        ComponentProfile::create(
                            targetFieldNumber: 1,
                            bits: 8,
                        ),
                    ],
                ),
            ],
        );

        $message = $this->profiledMessage(
            profile: $profile,
            architecture: FitArchitecture::LittleEndian,
            fields: [
                8 => [
                    'base_type' => 0x0D,
                    'bytes' => "\x2A",
                ],
            ],
        );

        $log = [];

        $stream = (
            static function () use (
                &$log,
                $message,
            ): iterable {
                $log[] = 'yield';

                yield 7 => $message;
            }
        )();

        $extractedStream = (
        new FitComponentExtractor()
        )->extractStream($stream);

        self::assertSame(
            [],
            $log,
        );

        $messages = iterator_to_array(
            $extractedStream,
        );

        self::assertSame(
            ['yield'],
            $log,
        );

        self::assertSame(
            [7],
            array_keys($messages),
        );

        self::assertInstanceOf(
            ComponentExtractedDataMessage::class,
            $messages[7],
        );

        self::assertSame(
            42,
            $messages[7]
                ->components()[0]
                ->packedValue,
        );
    }
}
