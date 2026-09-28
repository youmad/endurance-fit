<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Tests\Decoder;

use PHPUnit\Framework\TestCase;
use Youmad\Endurance\Fit\Decoder\FitComponentExtractor;
use Youmad\Endurance\Fit\Decoder\FitComponentTypeValueResolver;
use Youmad\Endurance\Fit\Decoder\FitComponentValueResolver;
use Youmad\Endurance\Fit\Decoder\FitDataMessageAssembler;
use Youmad\Endurance\Fit\Decoder\FitDataMessageDecoder;
use Youmad\Endurance\Fit\Decoder\FitDataMessageProcessor;
use Youmad\Endurance\Fit\Decoder\FitDecoder;
use Youmad\Endurance\Fit\Decoder\FitDecodingSession;
use Youmad\Endurance\Fit\Decoder\FitProfileNormalizer;
use Youmad\Endurance\Fit\Decoder\FitTypeValueResolver;
use Youmad\Endurance\Fit\Profile\ComponentProfile;
use Youmad\Endurance\Fit\Profile\FieldProfile;
use Youmad\Endurance\Fit\Profile\FieldTransform;
use Youmad\Endurance\Fit\Profile\FitTypeProfile;
use Youmad\Endurance\Fit\Profile\InMemoryFitProfileRegistry;
use Youmad\Endurance\Fit\Profile\InMemoryFitTypeRegistry;
use Youmad\Endurance\Fit\Profile\MessageProfile;
use Youmad\Endurance\Fit\Profile\TypeValueProfile;
use Youmad\Endurance\Fit\Raw\FitArchitecture;
use Youmad\Endurance\Fit\Raw\FitBaseType;
use Youmad\Endurance\Fit\Raw\MessageDefinition;
use Youmad\Endurance\Fit\Raw\RawDataMessage;
use Youmad\Endurance\Fit\Raw\RawDefinitionMessage;
use Youmad\Endurance\Fit\Raw\RawFieldValue;
use Youmad\Endurance\Fit\Raw\RawStandardField;
use Youmad\Endurance\Fit\Raw\StandardFieldDefinition;
use Youmad\Endurance\Fit\Typed\TypedEnumFieldElement;
use Youmad\Endurance\Fit\Typed\TypedFieldElements;
use Youmad\Endurance\Fit\Unified\FieldValueOrigin;
use Youmad\Endurance\Fit\Unified\UnifiedDataMessage;

final class FitDataMessageProcessorTest extends TestCase
{
    public function testProcessesRawRecordThroughCompletePipeline(): void
    {
        $profile = $this->eventProfile();
        $raw = $this->rawMessage(
            profile: $profile,
            fields: [
                1 => [
                    'base_type' => 0x00,
                    'bytes' => "\x04",
                ],
                8 => [
                    'base_type' => 0x0D,
                    'bytes' => "\x06\x2A",
                ],
            ],
        );

        $message = $this->processor(
            profile: $profile,
            types: $this->eventTypes(),
        )->process($raw);

        self::assertInstanceOf(
            UnifiedDataMessage::class,
            $message,
        );

        $event = $message->standardField(1);

        self::assertNotNull($event);
        self::assertNotNull($event->physical());

        self::assertSame(
            FieldValueOrigin::Physical,
            $event->physical()->origin(),
        );

        self::assertSame(
            'timer',
            $this->physicalSymbolicName($message, 1),
        );

        self::assertCount(
            1,
            $event->components(),
        );

        self::assertSame(
            FieldValueOrigin::Component,
            $event->components()[0]->origin(),
        );

        self::assertSame(
            6,
            $event->components()[0]
                ->source
                ->value(),
        );

        self::assertSame(
            'stop_all',
            $event->components()[0]
                ->source
                ->symbolicName(),
        );

        self::assertSame(
            42,
            $message
                ->standardField(2)
                ?->components()[0]
                ->source
                ->value(),
        );
    }

    public function testOptimizedPipelineMatchesValidatingReferencePipeline(): void
    {
        $profile = $this->eventProfile();
        $types = $this->eventTypes();
        $decoded = (new FitDataMessageDecoder())->decode(
            $this->rawMessage(
                profile: $profile,
                fields: [
                    1 => [
                        'base_type' => 0x00,
                        'bytes' => "\x04",
                    ],
                    8 => [
                        'base_type' => 0x0D,
                        'bytes' => "\x06\x2A",
                    ],
                ],
            ),
        );

        $profiles = new FitProfileNormalizer(
            new InMemoryFitProfileRegistry(
                $profile,
            ),
        );
        $physicalTypes = new FitTypeValueResolver(
            $types,
        );
        $components = new FitComponentExtractor();
        $componentValues = new FitComponentValueResolver();
        $componentTypes = new FitComponentTypeValueResolver(
            $types,
        );
        $assembler = new FitDataMessageAssembler();

        $profiled = $profiles->normalize($decoded);
        $physical = $physicalTypes->resolve($profiled);
        $typedComponents = $componentTypes->resolve(
            $componentValues->resolve(
                $components->extract($profiled),
            ),
        );
        $expected = $assembler->assemble(
            physical: $physical,
            components: $typedComponents,
        );

        $actual = $this->processor(
            profile: $profile,
            types: $types,
        )->process($decoded);

        self::assertEquals($expected, $actual);
    }

    public function testProcessesNestedComponentsThroughCompletePipeline(): void
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

        $message = $this->processor(
            profile: $profile,
        )->process(
            $this->rawMessage(
                profile: $profile,
                fields: [
                    8 => [
                        'base_type' => 0x84,
                        'bytes' => pack('v', 0xABC),
                    ],
                ],
            ),
        );

        $speed = $message->standardField(6);
        $enhancedSpeed = $message->standardField(73);

        self::assertNotNull($speed);
        self::assertNotNull($enhancedSpeed);

        self::assertSame(
            27.48,
            $speed->components()[0]
                ->source
                ->value(),
        );

        self::assertSame(
            27.48,
            $enhancedSpeed->components()[0]
                ->source
                ->value(),
        );

        self::assertSame(
            6,
            $enhancedSpeed->components()[0]
                ->source
                ->source
                ->source
                ->parent
                ?->targetFieldNumber(),
        );
    }

    private function eventProfile(): MessageProfile
    {
        return MessageProfile::create(
            globalMessageNumber: 21,
            name: 'event',
            fields: [
                FieldProfile::create(
                    fieldNumber: 1,
                    name: 'event_type',
                    typeName: 'event',
                ),
                FieldProfile::create(
                    fieldNumber: 2,
                    name: 'event_data',
                    typeName: 'uint8',
                ),
                FieldProfile::create(
                    fieldNumber: 8,
                    name: 'packed_event',
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
    private function rawMessage(
        MessageProfile $profile,
        array $fields,
        int $sequenceNumber = 0,
    ): RawDataMessage {
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
            architecture: FitArchitecture::LittleEndian,
            globalMessageNumber: $profile->globalMessageNumber,
            standardFields: $definitions,
        );

        return RawDataMessage::create(
            sequenceNumber: $sequenceNumber,
            byteOffset: 20,
            recordHeaderByte: 0x00,
            definition: $definition,
            standardFields: $rawFields,
        );
    }

    private function processor(
        MessageProfile $profile,
        ?InMemoryFitTypeRegistry $types = null,
    ): FitDataMessageProcessor {
        $types ??= new InMemoryFitTypeRegistry();

        return new FitDataMessageProcessor(
            decoder: new FitDataMessageDecoder(),
            profiles: new FitProfileNormalizer(
                new InMemoryFitProfileRegistry(
                    $profile,
                ),
            ),
            physicalTypes: new FitTypeValueResolver(
                $types,
            ),
            components: new FitComponentExtractor(),
            componentValues: new FitComponentValueResolver(),
            componentTypes: new FitComponentTypeValueResolver(
                $types,
            ),
            assembler: new FitDataMessageAssembler(),
        );
    }

    private function eventTypes(): InMemoryFitTypeRegistry
    {
        return new InMemoryFitTypeRegistry(
            types: [
                FitTypeProfile::create(
                    name: 'event',
                    values: [
                        TypeValueProfile::create(
                            value: 4,
                            name: 'timer',
                        ),
                        TypeValueProfile::create(
                            value: 6,
                            name: 'stop_all',
                        ),
                    ],
                ),
            ],
        );
    }

    private function physicalSymbolicName(
        UnifiedDataMessage $message,
        int $fieldNumber,
    ): ?string {
        $physical = $message
            ->standardField($fieldNumber)
            ?->physical();

        self::assertNotNull($physical);

        $value = $physical->source->value;

        self::assertInstanceOf(
            TypedFieldElements::class,
            $value,
        );

        $element = $value->elements()[0];

        self::assertInstanceOf(
            TypedEnumFieldElement::class,
            $element,
        );

        return $element->name();
    }

    public function testAcceptsAlreadyDecodedMessage(): void
    {
        $profile = $this->eventProfile();
        $raw = $this->rawMessage(
            profile: $profile,
            fields: [
                1 => [
                    'base_type' => 0x00,
                    'bytes' => "\x04",
                ],
                8 => [
                    'base_type' => 0x0D,
                    'bytes' => "\x06\x2A",
                ],
            ],
        );

        $decoded = (new FitDataMessageDecoder())
            ->decode($raw);

        $message = $this->processor(
            profile: $profile,
            types: $this->eventTypes(),
        )->process($decoded);

        self::assertSame(
            $decoded,
            $message
                ->physicalSource
                ->source
                ->source,
        );

        self::assertSame(
            'timer',
            $this->physicalSymbolicName($message, 1),
        );
    }

    public function testProcessesStreamLazilyAndSkipsDefinitions(): void
    {
        $profile = $this->eventProfile();
        $raw = $this->rawMessage(
            profile: $profile,
            fields: [
                1 => [
                    'base_type' => 0x00,
                    'bytes' => "\x04",
                ],
                8 => [
                    'base_type' => 0x0D,
                    'bytes' => "\x06\x2A",
                ],
            ],
            sequenceNumber: 1,
        );

        $definition = new RawDefinitionMessage(
            sequenceNumber: 0,
            messageByteOffset: 0,
            recordHeaderByte: 0x40,
            reservedByte: 0,
            definition: $raw->definition(),
        );

        $log = [];

        $stream = (
            static function () use (
                &$log,
                $definition,
                $raw,
            ): iterable {
                $log[] = 'definition';

                yield 0 => $definition;

                $log[] = 'data';

                yield 1 => $raw;
            }
        )();

        $processed = $this->processor(
            profile: $profile,
            types: $this->eventTypes(),
        )->processStream($stream);

        self::assertSame(
            [],
            $log,
        );

        $messages = iterator_to_array(
            $processed,
        );

        self::assertSame(
            [
                'definition',
                'data',
            ],
            $log,
        );

        self::assertSame(
            [1],
            array_keys($messages),
        );

        self::assertSame(
            'timer',
            $this->physicalSymbolicName(
                $messages[1],
                1,
            ),
        );
    }

    public function testAccumulatorLivesWithinStreamAndResetsForNextStream(): void
    {
        $profile = $this->distanceProfile();
        $processor = $this->processor(
            profile: $profile,
        );

        $firstStream = iterator_to_array(
            $processor->processStream(
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
            4090,
            $this->componentRawValue(
                $firstStream[0],
                5,
            ),
        );

        self::assertSame(
            4099,
            $this->componentRawValue(
                $firstStream[1],
                5,
            ),
        );

        $secondStream = iterator_to_array(
            $processor->processStream(
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
            $this->componentRawValue(
                $secondStream[0],
                5,
            ),
        );
    }

    public function testSessionSharesComponentStateWithProjectionAndResetsIt(): void
    {
        $profile = $this->distanceProfile();
        $profiles = new InMemoryFitProfileRegistry($profile);
        $types = new InMemoryFitTypeRegistry();
        $session = new FitDecodingSession($profiles, $types);
        $independent = new FitDecodingSession($profiles, $types);
        $first = $this->compressedDistanceMessage($profile, 4090);
        $next = $this->compressedDistanceMessage($profile, 3);

        // A specialized projection uses the lower-level components first.
        $session->componentValues->resolve($session->components->extract(
            $session->profiles->normalize($session->decoder->decode($first)),
        ));
        self::assertSame(4099, $this->componentRawValue($session->messages->process($next), 5));
        self::assertSame(3, $this->componentRawValue($independent->messages->process($next), 5));

        $session->messages->reset();
        self::assertSame(3, $this->componentRawValue($session->messages->process($next), 5));
    }

    public function testDecoderCreatesIndependentAccumulationSessions(): void
    {
        $profile = $this->distanceProfile();
        $decoder = new FitDecoder(
            new InMemoryFitProfileRegistry($profile),
            new InMemoryFitTypeRegistry(),
        );
        $first = $decoder->newMessageProcessor();
        $second = $decoder->newMessageProcessor();
        $first->process($this->compressedDistanceMessage($profile, 4090));
        $next = $this->compressedDistanceMessage($profile, 3);
        self::assertSame(3, $this->componentRawValue($second->process($next), 5));
        self::assertSame(4099, $this->componentRawValue($first->process($next), 5));
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
    ): RawDataMessage {
        return $this->rawMessage(
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

    private function componentRawValue(
        UnifiedDataMessage $message,
        int $fieldNumber,
    ): int {
        $field = $message->standardField(
            $fieldNumber,
        );

        self::assertNotNull($field);
        self::assertNotEmpty($field->components());

        return $field
            ->components()[0]
            ->source
            ->source
            ->componentRawValue;
    }
}
