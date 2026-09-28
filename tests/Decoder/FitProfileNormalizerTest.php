<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Tests\Decoder;

use PHPUnit\Framework\TestCase;
use Youmad\Endurance\Fit\Decoded\DecodedDataMessage;
use Youmad\Endurance\Fit\Decoded\DecodedFieldElements;
use Youmad\Endurance\Fit\Decoded\InvalidFieldElement;
use Youmad\Endurance\Fit\Decoded\ValidFieldElement;
use Youmad\Endurance\Fit\Decoder\FitDataMessageDecoder;
use Youmad\Endurance\Fit\Decoder\FitProfileNormalizer;
use Youmad\Endurance\Fit\Decoder\FitTypeValueResolver;
use Youmad\Endurance\Fit\Profile\ComponentProfile;
use Youmad\Endurance\Fit\Profile\FieldProfile;
use Youmad\Endurance\Fit\Profile\FieldTransform;
use Youmad\Endurance\Fit\Profile\InMemoryFitProfileRegistry;
use Youmad\Endurance\Fit\Profile\InMemoryFitTypeRegistry;
use Youmad\Endurance\Fit\Profile\MessageProfile;
use Youmad\Endurance\Fit\Profiled\ProfiledDataMessage;
use Youmad\Endurance\Fit\Profiled\ProfileNormalizationState;
use Youmad\Endurance\Fit\Raw\FitArchitecture;
use Youmad\Endurance\Fit\Raw\FitBaseType;
use Youmad\Endurance\Fit\Raw\MessageDefinition;
use Youmad\Endurance\Fit\Raw\RawDataMessage;
use Youmad\Endurance\Fit\Raw\RawFieldValue;
use Youmad\Endurance\Fit\Raw\RawStandardField;
use Youmad\Endurance\Fit\Raw\StandardFieldDefinition;

final class FitProfileNormalizerTest extends TestCase
{
    public function testAppliesMessageAndFieldProfile(): void
    {
        $altitudeDefinition = $this->definition(
            fieldNumber: 2,
            size: 2,
            baseType: 0x84,
        );

        $heartRateDefinition = $this->definition(
            fieldNumber: 3,
            size: 1,
            baseType: 0x02,
        );

        $decoded = $this->decodedMessage(
            globalMessageNumber: 20,
            definitions: [
                $altitudeDefinition,
                $heartRateDefinition,
            ],
            values: [
                "\xC3\x28",
                "\x96",
            ],
        );

        $normalizer = $this->normalizer(
            MessageProfile::create(
                globalMessageNumber: 20,
                name: 'record',
                fields: [
                    FieldProfile::create(
                        fieldNumber: 2,
                        name: 'altitude',
                        typeName: 'uint16',
                        transform: FieldTransform::scaleAndOffset(
                            scale: 5,
                            offset: 500,
                        ),
                        units: 'm',
                    ),
                    FieldProfile::create(
                        fieldNumber: 3,
                        name: 'heart_rate',
                        typeName: 'uint8',
                        units: 'bpm',
                    ),
                ],
            ),
        );

        $profiled = $normalizer->normalize(
            $decoded,
        );

        self::assertTrue(
            $profiled->isKnown(),
        );

        self::assertSame(
            'record',
            $profiled->name(),
        );

        $altitude = $profiled->standardField(2);

        self::assertNotNull($altitude);

        self::assertSame(
            'altitude',
            $altitude->name(),
        );

        self::assertSame(
            'm',
            $altitude->units(),
        );

        self::assertSame(
            ProfileNormalizationState::Applied,
            $altitude->normalizationState,
        );

        self::assertSame(
            1587,
            $this->validValue(
                $altitude->value,
                0,
            ),
        );

        self::assertSame(
            10_435,
            $this->validValue(
                $altitude->source->value,
                0,
            ),
        );

        $heartRate = $profiled->standardField(3);

        self::assertNotNull($heartRate);

        self::assertSame(
            ProfileNormalizationState::NotRequired,
            $heartRate->normalizationState,
        );

        self::assertSame(
            150,
            $this->validValue(
                $heartRate->value,
                0,
            ),
        );
    }

    public function testOptimizedPipelineReturnsOnlyActiveComponentContainers(): void
    {
        $plainDefinition = $this->definition(
            fieldNumber: 1,
            size: 1,
            baseType: 0x02,
        );
        $componentDefinition = $this->definition(
            fieldNumber: 8,
            size: 1,
            baseType: 0x02,
        );

        $decoded = $this->decodedMessage(
            globalMessageNumber: 20,
            definitions: [
                $plainDefinition,
                $componentDefinition,
            ],
            values: [
                "\x04",
                "\x06",
            ],
        );

        $normalizer = $this->normalizer(
            MessageProfile::create(
                globalMessageNumber: 20,
                name: 'record',
                fields: [
                    FieldProfile::create(
                        fieldNumber: 1,
                        name: 'plain',
                        typeName: 'uint8',
                    ),
                    FieldProfile::create(
                        fieldNumber: 8,
                        name: 'packed',
                        typeName: 'uint8',
                        components: [
                            ComponentProfile::create(
                                targetFieldNumber: 1,
                                bits: 8,
                            ),
                        ],
                    ),
                ],
            ),
        );

        [, , $componentContainers] = $normalizer->normalizeWithTypes(
            message: $decoded,
            types: new FitTypeValueResolver(
                new InMemoryFitTypeRegistry(),
            ),
        );

        self::assertCount(
            1,
            $componentContainers,
        );
        self::assertSame(
            8,
            $componentContainers[0]->fieldNumber(),
        );
    }

    private function definition(
        int $fieldNumber,
        int $size,
        int $baseType,
    ): StandardFieldDefinition {
        return StandardFieldDefinition::create(
            fieldNumber: $fieldNumber,
            size: $size,
            baseType: FitBaseType::fromDefinitionByte(
                $baseType,
            ),
        );
    }

    /**
     * @param list<StandardFieldDefinition> $definitions
     * @param list<string>                  $values
     */
    private function decodedMessage(
        int $globalMessageNumber,
        array $definitions,
        array $values,
    ): DecodedDataMessage {
        self::assertCount(
            count($definitions),
            $values,
        );

        $messageDefinition = MessageDefinition::create(
            localMessageNumber: 0,
            architecture: FitArchitecture::LittleEndian,
            globalMessageNumber: $globalMessageNumber,
            standardFields: $definitions,
        );

        $fields = [];

        foreach ($definitions as $index => $definition) {
            $fields[] = new RawStandardField(
                definition: $definition,
                value: RawFieldValue::fromBytes(
                    $values[$index],
                ),
            );
        }

        $raw = RawDataMessage::create(
            sequenceNumber: 1,
            byteOffset: 20,
            recordHeaderByte: 0x00,
            definition: $messageDefinition,
            standardFields: $fields,
        );

        return (new FitDataMessageDecoder())
            ->decode($raw);
    }

    private function normalizer(
        MessageProfile ...$profiles,
    ): FitProfileNormalizer {
        return new FitProfileNormalizer(
            new InMemoryFitProfileRegistry(
                ...$profiles,
            ),
        );
    }

    private function validValue(
        mixed $value,
        int $index,
    ): int|float|string {
        self::assertInstanceOf(
            DecodedFieldElements::class,
            $value,
        );

        $element = $value->elements()[$index];

        self::assertInstanceOf(
            ValidFieldElement::class,
            $element,
        );

        return $element->value;
    }

    public function testTransformsArrayElementsAndPreservesInvalidValues(): void
    {
        $definition = $this->definition(
            fieldNumber: 10,
            size: 6,
            baseType: 0x84,
        );

        $decoded = $this->decodedMessage(
            globalMessageNumber: 20,
            definitions: [$definition],
            values: [
                "\x64\x00\xFF\xFF\x78\x00",
            ],
        );

        $profiled = $this->normalizer(
            MessageProfile::create(
                globalMessageNumber: 20,
                name: 'record',
                fields: [
                    FieldProfile::create(
                        fieldNumber: 10,
                        name: 'sample_values',
                        typeName: 'uint16',
                        transform: FieldTransform::scaleAndOffset(
                            scale: 10,
                        ),
                    ),
                ],
            ),
        )->normalize($decoded);

        $field = $profiled->standardField(10);

        self::assertNotNull($field);

        self::assertSame(
            ProfileNormalizationState::Applied,
            $field->normalizationState,
        );

        self::assertSame(
            10,
            $this->validValue(
                $field->value,
                0,
            ),
        );

        $value = $field->value;

        self::assertInstanceOf(
            DecodedFieldElements::class,
            $value,
        );

        self::assertInstanceOf(
            InvalidFieldElement::class,
            $value->elements()[1],
        );

        self::assertSame(
            12,
            $this->validValue(
                $field->value,
                2,
            ),
        );
    }

    public function testUnknownFieldPreservesDecodedValue(): void
    {
        $knownDefinition = $this->definition(
            fieldNumber: 3,
            size: 1,
            baseType: 0x02,
        );

        $unknownDefinition = $this->definition(
            fieldNumber: 200,
            size: 1,
            baseType: 0x02,
        );

        $decoded = $this->decodedMessage(
            globalMessageNumber: 20,
            definitions: [
                $knownDefinition,
                $unknownDefinition,
            ],
            values: [
                "\x96",
                "\x2A",
            ],
        );

        $profiled = $this->normalizer(
            MessageProfile::create(
                globalMessageNumber: 20,
                name: 'record',
                fields: [
                    FieldProfile::create(
                        fieldNumber: 3,
                        name: 'heart_rate',
                        typeName: 'uint8',
                        units: 'bpm',
                    ),
                ],
            ),
        )->normalize($decoded);

        $unknown = $profiled->standardField(200);

        self::assertNotNull($unknown);

        self::assertFalse(
            $unknown->isKnown(),
        );

        self::assertNull(
            $unknown->name(),
        );

        self::assertSame(
            ProfileNormalizationState::Unavailable,
            $unknown->normalizationState,
        );

        self::assertSame(
            $unknown->source->value,
            $unknown->value,
        );

        self::assertSame(
            42,
            $this->validValue(
                $unknown->value,
                0,
            ),
        );
    }

    public function testUnknownMessageRemainsAvailable(): void
    {
        $definition = $this->definition(
            fieldNumber: 200,
            size: 1,
            baseType: 0x02,
        );

        $decoded = $this->decodedMessage(
            globalMessageNumber: 65_000,
            definitions: [$definition],
            values: ["\x2A"],
        );

        $profiled = $this->normalizer()
            ->normalize($decoded);

        self::assertFalse(
            $profiled->isKnown(),
        );

        self::assertNull(
            $profiled->name(),
        );

        self::assertSame(
            65_000,
            $profiled->globalMessageNumber(),
        );

        self::assertSame(
            42,
            $this->validValue(
                $profiled
                    ->standardFields()[0]
                    ->value,
                0,
            ),
        );
    }

    public function testDoesNotApplyNumericTransformToString(): void
    {
        $definition = $this->definition(
            fieldNumber: 0,
            size: 8,
            baseType: 0x07,
        );

        $decoded = $this->decodedMessage(
            globalMessageNumber: 23,
            definitions: [$definition],
            values: ['Edge 840'],
        );

        $profiled = $this->normalizer(
            MessageProfile::create(
                globalMessageNumber: 23,
                name: 'device_info',
                fields: [
                    FieldProfile::create(
                        fieldNumber: 0,
                        name: 'device_name',
                        typeName: 'string',
                        transform: FieldTransform::scaleAndOffset(
                            scale: 2,
                        ),
                    ),
                ],
            ),
        )->normalize($decoded);

        $field = $profiled->standardField(0);

        self::assertNotNull($field);

        self::assertSame(
            ProfileNormalizationState::Unavailable,
            $field->normalizationState,
        );

        self::assertSame(
            $field->source->value,
            $field->value,
        );

        self::assertSame(
            'Edge 840',
            $this->validValue(
                $field->value,
                0,
            ),
        );
    }

    public function testNormalizesStreamLazily(): void
    {
        $definition = $this->definition(
            fieldNumber: 3,
            size: 1,
            baseType: 0x02,
        );

        $decoded = $this->decodedMessage(
            globalMessageNumber: 20,
            definitions: [$definition],
            values: ["\x96"],
        );

        $log = [];

        $stream = (
            static function () use (
                &$log,
                $decoded,
            ): iterable {
                $log[] = 'yield';

                yield 7 => $decoded;
            }
        )();

        $normalizedStream = $this->normalizer(
            MessageProfile::create(
                globalMessageNumber: 20,
                name: 'record',
                fields: [
                    FieldProfile::create(
                        fieldNumber: 3,
                        name: 'heart_rate',
                        typeName: 'uint8',
                        units: 'bpm',
                    ),
                ],
            ),
        )->normalizeStream($stream);

        self::assertSame(
            [],
            $log,
        );

        $messages = iterator_to_array(
            $normalizedStream,
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
            ProfiledDataMessage::class,
            $messages[7],
        );

        self::assertSame(
            'record',
            $messages[7]->name(),
        );
    }
}
