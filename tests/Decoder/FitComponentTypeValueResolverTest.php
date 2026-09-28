<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Tests\Decoder;

use PHPUnit\Framework\TestCase;
use Youmad\Endurance\Fit\Component\ComponentResolvedDataMessage;
use Youmad\Endurance\Fit\Decoder\FitComponentExtractor;
use Youmad\Endurance\Fit\Decoder\FitComponentTypeValueResolver;
use Youmad\Endurance\Fit\Decoder\FitComponentValueResolver;
use Youmad\Endurance\Fit\Decoder\FitDataMessageDecoder;
use Youmad\Endurance\Fit\Decoder\FitProfileNormalizer;
use Youmad\Endurance\Fit\Profile\ComponentProfile;
use Youmad\Endurance\Fit\Profile\FieldProfile;
use Youmad\Endurance\Fit\Profile\FieldTransform;
use Youmad\Endurance\Fit\Profile\FitTypeProfile;
use Youmad\Endurance\Fit\Profile\InMemoryFitProfileRegistry;
use Youmad\Endurance\Fit\Profile\InMemoryFitTypeRegistry;
use Youmad\Endurance\Fit\Profile\MessageProfile;
use Youmad\Endurance\Fit\Profile\TypeValueProfile;
use Youmad\Endurance\Fit\Profiled\ProfiledDataMessage;
use Youmad\Endurance\Fit\Raw\FitArchitecture;
use Youmad\Endurance\Fit\Raw\FitBaseType;
use Youmad\Endurance\Fit\Raw\MessageDefinition;
use Youmad\Endurance\Fit\Raw\RawDataMessage;
use Youmad\Endurance\Fit\Raw\RawFieldValue;
use Youmad\Endurance\Fit\Raw\RawStandardField;
use Youmad\Endurance\Fit\Raw\StandardFieldDefinition;
use Youmad\Endurance\Fit\Typed\TypedComponentDataMessage;
use Youmad\Endurance\Fit\Typed\TypeResolutionState;

final class FitComponentTypeValueResolverTest extends TestCase
{
    public function testAddsSymbolicNameWithoutReplacingComponentValue(): void
    {
        $resolved = $this->resolvedComponentMessage(
            targetTypeName: 'event',
            packedValue: 4,
        );

        $typed = $this->resolver(
            $this->eventType(),
        )->resolve($resolved);

        self::assertInstanceOf(
            TypedComponentDataMessage::class,
            $typed,
        );

        $component = $typed->components()[0];

        self::assertSame(
            TypeResolutionState::Applied,
            $component->resolutionState,
        );

        self::assertSame(
            4,
            $component->value(),
        );

        self::assertSame(
            'timer',
            $component->symbolicName(),
        );

        self::assertTrue(
            $component->isKnownSymbolicValue(),
        );

        self::assertSame(
            $resolved->components()[0],
            $component->source,
        );
    }

    private function resolvedComponentMessage(
        string $targetTypeName,
        int $packedValue,
        ?FieldTransform $componentTransform = null,
    ): ComponentResolvedDataMessage {
        $profile = MessageProfile::create(
            globalMessageNumber: 21,
            name: 'event',
            fields: [
                FieldProfile::create(
                    fieldNumber: 1,
                    name: 'event_type',
                    typeName: $targetTypeName,
                ),
                FieldProfile::create(
                    fieldNumber: 8,
                    name: 'packed_event',
                    typeName: 'uint8',
                    components: [
                        ComponentProfile::create(
                            targetFieldNumber: 1,
                            bits: 8,
                            transform: $componentTransform,
                        ),
                    ],
                ),
            ],
        );

        $profiled = $this->profiledMessage(
            profile: $profile,
            fields: [
                8 => [
                    'base_type' => 0x02,
                    'bytes' => chr($packedValue),
                ],
            ],
        );

        return (
        new FitComponentValueResolver()
        )->resolve(
            (new FitComponentExtractor())
                ->extract($profiled),
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

    private function resolver(
        FitTypeProfile ...$types,
    ): FitComponentTypeValueResolver {
        return new FitComponentTypeValueResolver(
            new InMemoryFitTypeRegistry(
                types: $types,
            ),
        );
    }

    private function eventType(): FitTypeProfile
    {
        return FitTypeProfile::create(
            name: 'event',
            values: [
                TypeValueProfile::create(
                    value: 4,
                    name: 'timer',
                ),
            ],
        );
    }

    public function testUnknownSymbolicValueRemainsAvailable(): void
    {
        $typed = $this->resolver(
            $this->eventType(),
        )->resolve(
            $this->resolvedComponentMessage(
                targetTypeName: 'event',
                packedValue: 99,
            ),
        );

        $component = $typed->components()[0];

        self::assertSame(
            TypeResolutionState::Applied,
            $component->resolutionState,
        );

        self::assertSame(
            99,
            $component->value(),
        );

        self::assertNull(
            $component->symbolicName(),
        );

        self::assertFalse(
            $component->isKnownSymbolicValue(),
        );
    }

    public function testPrimitiveComponentRemainsNumeric(): void
    {
        $typed = $this->resolver()->resolve(
            $this->resolvedComponentMessage(
                targetTypeName: 'uint8',
                packedValue: 42,
            ),
        );

        $component = $typed->components()[0];

        self::assertSame(
            TypeResolutionState::NotApplicable,
            $component->resolutionState,
        );

        self::assertSame(
            42,
            $component->value(),
        );

        self::assertNull(
            $component->typeProfile,
        );
    }

    public function testKnownSymbolicTypeCannotResolveFractionalPhysicalValue(): void
    {
        $typed = $this->resolver(
            $this->eventType(),
        )->resolve(
            $this->resolvedComponentMessage(
                targetTypeName: 'event',
                packedValue: 3,
                componentTransform: FieldTransform::scaleAndOffset(
                    scale: 2,
                ),
            ),
        );

        $component = $typed->components()[0];

        self::assertSame(
            TypeResolutionState::Unavailable,
            $component->resolutionState,
        );

        self::assertSame(
            1.5,
            $component->value(),
        );

        self::assertNull(
            $component->symbolicName(),
        );
    }

    public function testResolvesStreamLazily(): void
    {
        $message = $this->resolvedComponentMessage(
            targetTypeName: 'event',
            packedValue: 4,
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

        $typedStream = $this->resolver(
            $this->eventType(),
        )->resolveStream($stream);

        self::assertSame(
            [],
            $log,
        );

        $messages = iterator_to_array(
            $typedStream,
        );

        self::assertSame(
            ['yield'],
            $log,
        );

        self::assertSame(
            [7],
            array_keys($messages),
        );

        self::assertSame(
            'timer',
            $messages[7]
                ->components()[0]
                ->symbolicName(),
        );
    }
}
