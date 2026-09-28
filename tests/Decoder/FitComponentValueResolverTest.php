<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Tests\Decoder;

use PHPUnit\Framework\TestCase;
use Youmad\Endurance\Fit\Component\ComponentExtractedDataMessage;
use Youmad\Endurance\Fit\Component\ComponentResolvedDataMessage;
use Youmad\Endurance\Fit\Decoder\FitComponentExtractor;
use Youmad\Endurance\Fit\Decoder\FitComponentValueResolver;
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

final class FitComponentValueResolverTest extends TestCase
{
    public function testResolvesNonAccumulatedComponentAndPreservesProvenance(): void
    {
        $profile = MessageProfile::create(
            globalMessageNumber: 20,
            name: 'record',
            fields: [
                FieldProfile::create(
                    fieldNumber: 6,
                    name: 'speed',
                    typeName: 'uint16',
                    transform: FieldTransform::scaleAndOffset(
                        scale: 1000,
                    ),
                    units: 'm/s',
                ),
                FieldProfile::create(
                    fieldNumber: 8,
                    name: 'compressed_speed',
                    typeName: 'uint16',
                    components: [
                        ComponentProfile::create(
                            targetFieldNumber: 6,
                            bits: 12,
                            transform: FieldTransform::scaleAndOffset(
                                scale: 100,
                            ),
                            units: 'm/s',
                        ),
                    ],
                ),
            ],
        );

        $extracted = $this->extractedMessage(
            profile: $profile,
            fields: [
                8 => [
                    'base_type' => 0x84,
                    'bytes' => pack('v', 2748),
                ],
            ],
        );

        $resolved = (
        new FitComponentValueResolver()
        )->resolve($extracted);

        self::assertInstanceOf(
            ComponentResolvedDataMessage::class,
            $resolved,
        );

        self::assertCount(
            1,
            $resolved->components(),
        );

        $component = $resolved->components()[0];

        self::assertSame(
            $extracted->components()[0],
            $component->source,
        );

        self::assertSame(
            2748,
            $component->packedValue(),
        );

        self::assertSame(
            2748,
            $component->componentRawValue,
        );

        self::assertSame(
            27.48,
            $component->physicalValue,
        );

        self::assertSame(
            27_480,
            $component->targetRawValue,
        );

        self::assertFalse(
            $component->accumulationApplied,
        );

        self::assertSame(
            'speed',
            $component->name(),
        );

        self::assertSame(
            'm/s',
            $component->units(),
        );
    }

    public function testRecursivelyExpandsResolvedComponentTarget(): void
    {
        $profile = MessageProfile::create(
            globalMessageNumber: 20,
            name: 'record',
            fields: [
                FieldProfile::create(
                    fieldNumber: 6,
                    name: 'speed',
                    typeName: 'uint16',
                    transform: FieldTransform::scaleAndOffset(
                        scale: 500,
                    ),
                    units: 'm/s',
                    components: [
                        ComponentProfile::create(
                            targetFieldNumber: 73,
                            bits: 16,
                            transform: FieldTransform::scaleAndOffset(
                                scale: 1000,
                            ),
                            units: 'm/s',
                        ),
                    ],
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
                    ],
                ),
                FieldProfile::create(
                    fieldNumber: 73,
                    name: 'enhanced_speed',
                    typeName: 'uint32',
                    transform: FieldTransform::scaleAndOffset(
                        scale: 1000,
                    ),
                    units: 'm/s',
                ),
            ],
        );

        $extracted = $this->extractedMessage(
            profile: $profile,
            fields: [
                8 => [
                    'base_type' => 0x84,
                    'bytes' => pack('v', 0xABC),
                ],
            ],
        );

        self::assertCount(
            1,
            $extracted->components(),
        );

        $resolved = (new FitComponentValueResolver())
            ->resolve($extracted);

        self::assertCount(
            2,
            $resolved->components(),
        );

        self::assertCount(
            2,
            $resolved->source->components(),
        );

        $speed = $resolved->components()[0];
        $enhancedSpeed = $resolved->components()[1];

        self::assertSame(
            6,
            $speed->targetFieldNumber(),
        );

        self::assertNull(
            $speed->source->parent,
        );

        self::assertSame(
            27.48,
            $speed->physicalValue,
        );

        self::assertSame(
            13_740,
            $speed->targetRawValue,
        );

        self::assertSame(
            73,
            $enhancedSpeed->targetFieldNumber(),
        );

        self::assertSame(
            $speed,
            $enhancedSpeed->source->parent,
        );

        self::assertSame(
            $speed->source->container,
            $enhancedSpeed->source->container,
        );

        self::assertSame(
            27_480,
            $enhancedSpeed->packedValue(),
        );

        self::assertSame(
            27.48,
            $enhancedSpeed->physicalValue,
        );

        self::assertSame(
            27_480,
            $enhancedSpeed->targetRawValue,
        );
    }

    public function testRejectsRecursiveComponentCycle(): void
    {
        $profile = MessageProfile::create(
            globalMessageNumber: 20,
            name: 'record',
            fields: [
                FieldProfile::create(
                    fieldNumber: 6,
                    name: 'speed',
                    typeName: 'uint16',
                    components: [
                        ComponentProfile::create(
                            targetFieldNumber: 8,
                            bits: 8,
                        ),
                    ],
                ),
                FieldProfile::create(
                    fieldNumber: 8,
                    name: 'packed',
                    typeName: 'uint16',
                    components: [
                        ComponentProfile::create(
                            targetFieldNumber: 6,
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
            'component expansion contains a cycle through field 6',
        );

        (new FitComponentValueResolver())->resolve(
            $this->extractedMessage(
                profile: $profile,
                fields: [
                    8 => [
                        'base_type' => 0x84,
                        'bytes' => pack('v', 42),
                    ],
                ],
            ),
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
    private function extractedMessage(
        MessageProfile $profile,
        array $fields,
    ): ComponentExtractedDataMessage {
        return (
        new FitComponentExtractor()
        )->extract(
            $this->profiledMessage(
                profile: $profile,
                fields: $fields,
            ),
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
        array $fields,
    ): ProfiledDataMessage {
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

        $messageDefinition = MessageDefinition::create(
            localMessageNumber: 0,
            architecture: FitArchitecture::LittleEndian,
            globalMessageNumber: $profile->globalMessageNumber,
            standardFields: $definitions,
        );

        $decoded = (
        new FitDataMessageDecoder()
        )->decode(
            RawDataMessage::create(
                sequenceNumber: 1,
                byteOffset: 20,
                recordHeaderByte: 0x00,
                definition: $messageDefinition,
                standardFields: $rawFields,
            ),
        );

        return (
        new FitProfileNormalizer(
            new InMemoryFitProfileRegistry(
                $profile,
            ),
        )
        )->normalize($decoded);
    }

    public function testUsesActiveTargetSubfieldTransform(): void
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

        $resolved = (new FitComponentValueResolver())
            ->resolve(
                $this->extractedMessage(
                    profile: $profile,
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

        $component = $resolved->components()[0];

        self::assertSame(
            42,
            $component->physicalValue,
        );

        self::assertSame(
            42_000,
            $component->targetRawValue,
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

    public function testAccumulationBelongsToComponentOccurrenceNotTargetField(): void
    {
        $profile = MessageProfile::create(
            globalMessageNumber: 20,
            name: 'record',
            fields: [
                FieldProfile::create(
                    fieldNumber: 5,
                    name: 'total',
                    typeName: 'uint32',
                    accumulated: true,
                ),
                FieldProfile::create(
                    fieldNumber: 8,
                    name: 'packed',
                    typeName: 'byte',
                    components: [
                        ComponentProfile::create(
                            targetFieldNumber: 5,
                            bits: 8,
                            accumulated: false,
                        ),
                    ],
                ),
            ],
        );

        $resolver = new FitComponentValueResolver();

        $first = $resolver->resolve(
            $this->extractedMessage(
                profile: $profile,
                fields: [
                    8 => [
                        'base_type' => 0x0D,
                        'bytes' => "\xFA",
                    ],
                ],
            ),
        );

        $second = $resolver->resolve(
            $this->extractedMessage(
                profile: $profile,
                fields: [
                    8 => [
                        'base_type' => 0x0D,
                        'bytes' => "\x03",
                    ],
                ],
            ),
        );

        self::assertSame(250, $first->components()[0]->componentRawValue);
        self::assertSame(3, $second->components()[0]->componentRawValue);
        self::assertFalse($second->components()[0]->accumulationApplied);
    }

    public function testAccumulatedTargetMayUseDifferentComponentWidths(): void
    {
        $profile = MessageProfile::create(
            globalMessageNumber: 20,
            name: 'record',
            fields: [
                FieldProfile::create(
                    fieldNumber: 5,
                    name: 'total',
                    typeName: 'uint32',
                    accumulated: true,
                ),
                FieldProfile::create(
                    fieldNumber: 8,
                    name: 'packed_wide',
                    typeName: 'byte',
                    components: [
                        ComponentProfile::create(
                            targetFieldNumber: 5,
                            bits: 8,
                            accumulated: true,
                        ),
                    ],
                ),
                FieldProfile::create(
                    fieldNumber: 9,
                    name: 'packed_narrow',
                    typeName: 'byte',
                    components: [
                        ComponentProfile::create(
                            targetFieldNumber: 5,
                            bits: 4,
                            accumulated: true,
                        ),
                    ],
                ),
            ],
        );

        $resolver = new FitComponentValueResolver();

        $first = $resolver->resolve(
            $this->extractedMessage(
                profile: $profile,
                fields: [
                    8 => [
                        'base_type' => 0x0D,
                        'bytes' => "\xFA",
                    ],
                ],
            ),
        );

        $second = $resolver->resolve(
            $this->extractedMessage(
                profile: $profile,
                fields: [
                    9 => [
                        'base_type' => 0x0D,
                        'bytes' => "\x03",
                    ],
                ],
            ),
        );

        self::assertSame(250, $first->components()[0]->componentRawValue);
        self::assertSame(259, $second->components()[0]->componentRawValue);
    }

    public function testAccumulatesAcrossMessagesAndHandlesRollover(): void
    {
        $profile = $this->distanceProfile();

        $resolver = new FitComponentValueResolver();

        $first = $resolver->resolve(
            $this->compressedDistanceMessage(
                profile: $profile,
                packedValue: 4090,
            ),
        );

        $second = $resolver->resolve(
            $this->compressedDistanceMessage(
                profile: $profile,
                packedValue: 3,
            ),
        );

        self::assertSame(
            4090,
            $first
                ->components()[0]
                ->componentRawValue,
        );

        self::assertSame(
            4099,
            $second
                ->components()[0]
                ->componentRawValue,
        );

        self::assertSame(
            256.1875,
            $second
                ->components()[0]
                ->physicalValue,
        );

        self::assertTrue(
            $second
                ->components()[0]
                ->accumulationApplied,
        );
    }

    private function distanceProfile(): MessageProfile
    {
        return MessageProfile::create(
            globalMessageNumber: 20,
            name: 'record',
            fields: [
                FieldProfile::create(
                    fieldNumber: 5,
                    name: 'distance',
                    typeName: 'uint32',
                    transform: FieldTransform::scaleAndOffset(
                        scale: 100,
                    ),
                    units: 'm',
                    accumulated: true,
                ),
                FieldProfile::create(
                    fieldNumber: 8,
                    name: 'compressed_distance',
                    typeName: 'uint16',
                    components: [
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
    }

    private function compressedDistanceMessage(
        MessageProfile $profile,
        int $packedValue,
    ): ComponentExtractedDataMessage {
        return $this->extractedMessage(
            profile: $profile,
            fields: [
                8 => [
                    'base_type' => 0x84,
                    'bytes' => pack(
                        'v',
                        $packedValue,
                    ),
                ],
            ],
        );
    }

    public function testFullPhysicalFieldSeedsLaterCompressedComponent(): void
    {
        $profile = $this->distanceProfile();

        $resolver = new FitComponentValueResolver();

        $fullDistance = $this->extractedMessage(
            profile: $profile,
            fields: [
                5 => [
                    'base_type' => 0x86,
                    'bytes' => pack(
                        'V',
                        160_000,
                    ),
                ],
            ],
        );

        self::assertSame(
            [],
            $fullDistance->components(),
        );

        $resolver->resolve($fullDistance);

        $compressed = $resolver->resolve(
            $this->compressedDistanceMessage(
                profile: $profile,
                packedValue: 1040,
            ),
        );

        $distance = $compressed->components()[0];

        self::assertSame(
            1040,
            $distance->packedValue(),
        );

        self::assertSame(
            25_616,
            $distance->componentRawValue,
        );

        self::assertSame(
            1601,
            $distance->physicalValue,
        );

        self::assertSame(
            160_100,
            $distance->targetRawValue,
        );
    }

    public function testRepeatedAccumulatedComponentsUsePreviousComponent(): void
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

        $resolved = (
        new FitComponentValueResolver()
        )->resolve(
            $this->extractedMessage(
                profile: $profile,
                fields: [
                    10 => [
                        'base_type' => 0x0D,
                        'bytes' => "\xA5",
                    ],
                ],
            ),
        );

        $timestamps = $resolved
            ->componentsForField(9);

        self::assertCount(
            2,
            $timestamps,
        );

        self::assertSame(
            5,
            $timestamps[0]->componentRawValue,
        );

        self::assertSame(
            10,
            $timestamps[1]->componentRawValue,
        );
    }

    public function testTruncatesFractionalAccumulatedComponentSeedLikeGarminSdk(): void
    {
        $profile = MessageProfile::create(
            globalMessageNumber: 20,
            name: 'record',
            fields: [
                FieldProfile::create(
                    fieldNumber: 5,
                    name: 'distance',
                    typeName: 'uint32',
                    transform: FieldTransform::scaleAndOffset(
                        scale: 10,
                    ),
                    accumulated: true,
                ),
                FieldProfile::create(
                    fieldNumber: 8,
                    name: 'packed_distance',
                    typeName: 'uint8',
                    components: [
                        ComponentProfile::create(
                            targetFieldNumber: 5,
                            bits: 8,
                            accumulated: true,
                        ),
                    ],
                ),
            ],
        );

        $resolver = new FitComponentValueResolver();

        $resolver->resolve(
            $this->extractedMessage(
                profile: $profile,
                fields: [
                    5 => [
                        'base_type' => 0x86,
                        'bytes' => pack('V', 72),
                    ],
                ],
            ),
        );

        $resolved = $resolver->resolve(
            $this->extractedMessage(
                profile: $profile,
                fields: [
                    8 => [
                        'base_type' => 0x02,
                        'bytes' => "\x08",
                    ],
                ],
            ),
        );

        $distance = $resolved->components()[0];

        self::assertSame(
            8,
            $distance->componentRawValue,
        );

        self::assertSame(
            8,
            $distance->physicalValue,
        );

        self::assertSame(
            80,
            $distance->targetRawValue,
        );
    }

    public function testResolveStreamResetsStateForNewFile(): void
    {
        $profile = $this->distanceProfile();
        $resolver = new FitComponentValueResolver();

        $firstStream = iterator_to_array(
            $resolver->resolveStream(
                [
                    $this->compressedDistanceMessage(
                        profile: $profile,
                        packedValue: 4090,
                    ),
                    $this->compressedDistanceMessage(
                        profile: $profile,
                        packedValue: 3,
                    ),
                ],
            ),
            false,
        );

        self::assertSame(
            4099,
            $firstStream[1]
                ->components()[0]
                ->componentRawValue,
        );

        $secondStream = iterator_to_array(
            $resolver->resolveStream(
                [
                    $this->compressedDistanceMessage(
                        profile: $profile,
                        packedValue: 3,
                    ),
                ],
            ),
            false,
        );

        self::assertSame(
            3,
            $secondStream[0]
                ->components()[0]
                ->componentRawValue,
        );
    }
}
