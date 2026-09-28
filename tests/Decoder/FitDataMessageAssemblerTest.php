<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Tests\Decoder;

use PHPUnit\Framework\TestCase;
use Youmad\Endurance\Fit\Decoder\FitComponentExtractor;
use Youmad\Endurance\Fit\Decoder\FitComponentTypeValueResolver;
use Youmad\Endurance\Fit\Decoder\FitComponentValueResolver;
use Youmad\Endurance\Fit\Decoder\FitDataMessageAssembler;
use Youmad\Endurance\Fit\Decoder\FitDataMessageDecoder;
use Youmad\Endurance\Fit\Decoder\FitProfileNormalizer;
use Youmad\Endurance\Fit\Decoder\FitTypeValueResolver;
use Youmad\Endurance\Fit\Profile\ComponentProfile;
use Youmad\Endurance\Fit\Profile\FieldProfile;
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
use Youmad\Endurance\Fit\Typed\TypedDataMessage;
use Youmad\Endurance\Fit\Typed\TypedEnumFieldElement;
use Youmad\Endurance\Fit\Typed\TypedFieldElements;
use Youmad\Endurance\Fit\Unified\ComponentFieldValue;
use Youmad\Endurance\Fit\Unified\FieldValueOrigin;
use Youmad\Endurance\Fit\Unified\PhysicalFieldValue;
use Youmad\Endurance\Fit\Unified\PreferPhysicalFieldValueSelectionPolicy;
use Youmad\Endurance\Fit\Unified\UnifiedDataMessage;

final class FitDataMessageAssemblerTest extends TestCase
{
    public function testPreservesPhysicalAndComponentDerivedValues(): void
    {
        [$physical, $components] = $this->branches(
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

        $unified = (new FitDataMessageAssembler())
            ->assemble(
                physical: $physical,
                components: $components,
            );

        self::assertInstanceOf(
            UnifiedDataMessage::class,
            $unified,
        );

        self::assertSame(
            [1, 8, 2],
            array_map(
                static fn ($field): int => $field->fieldNumber,
                $unified->standardFields(),
            ),
        );

        $event = $unified->standardField(1);

        self::assertNotNull($event);

        self::assertTrue(
            $event->hasPhysicalValue(),
        );

        self::assertTrue(
            $event->hasComponentValues(),
        );

        self::assertCount(
            2,
            $event->values(),
        );

        self::assertInstanceOf(
            PhysicalFieldValue::class,
            $event->values()[0],
        );

        self::assertSame(
            FieldValueOrigin::Physical,
            $event->values()[0]->origin(),
        );

        self::assertInstanceOf(
            ComponentFieldValue::class,
            $event->values()[1],
        );

        self::assertSame(
            FieldValueOrigin::Component,
            $event->values()[1]->origin(),
        );

        self::assertSame(
            'timer',
            $this->physicalSymbolicName(
                $event->physical(),
            ),
        );

        self::assertSame(
            'stop_all',
            $event
                ->components()[0]
                ->source
                ->symbolicName(),
        );

        self::assertSame(
            6,
            $event
                ->components()[0]
                ->source
                ->value(),
        );

        $derivedOnly = $unified->standardField(2);

        self::assertNotNull($derivedOnly);

        self::assertFalse(
            $derivedOnly->hasPhysicalValue(),
        );

        self::assertCount(
            1,
            $derivedOnly->components(),
        );

        self::assertSame(
            42,
            $derivedOnly
                ->components()[0]
                ->source
                ->value(),
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
     *
     * @return array{TypedDataMessage, TypedComponentDataMessage}
     */
    private function branches(
        array $fields,
    ): array {
        $profiled = $this->profiledMessage(
            profile: $this->profile(),
            fields: $fields,
        );

        return [
            $this->physicalBranch($profiled),
            $this->componentBranch($profiled),
        ];
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

    private function profile(): MessageProfile
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

    private function physicalBranch(
        ProfiledDataMessage $message,
    ): TypedDataMessage {
        return (
        new FitTypeValueResolver(
            $this->types(),
        )
        )->resolve($message);
    }

    private function types(): InMemoryFitTypeRegistry
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

    private function componentBranch(
        ProfiledDataMessage $message,
    ): TypedComponentDataMessage {
        $resolved = (
        new FitComponentValueResolver()
        )->resolve(
            (new FitComponentExtractor())
                ->extract($message),
        );

        return (
        new FitComponentTypeValueResolver(
            $this->types(),
        )
        )->resolve($resolved);
    }

    private function physicalSymbolicName(
        ?PhysicalFieldValue $value,
    ): ?string {
        self::assertNotNull($value);

        $typedValue = $value->source->value;

        self::assertInstanceOf(
            TypedFieldElements::class,
            $typedValue,
        );

        $element = $typedValue->elements()[0];

        self::assertInstanceOf(
            TypedEnumFieldElement::class,
            $element,
        );

        return $element->name();
    }

    public function testPhysicalFirstPolicyDoesNotDiscardAlternatives(): void
    {
        [$physical, $components] = $this->branches(
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

        $field = (new FitDataMessageAssembler())
            ->assemble(
                physical: $physical,
                components: $components,
            )
            ->standardField(1);

        self::assertNotNull($field);

        $selected = (
        new PreferPhysicalFieldValueSelectionPolicy()
        )->select($field);

        self::assertCount(
            1,
            $selected,
        );

        self::assertSame(
            FieldValueOrigin::Physical,
            $selected[0]->origin(),
        );

        self::assertCount(
            2,
            $field->values(),
        );
    }

    public function testSelectionFallsBackToComponentsWhenPhysicalValueIsInvalid(): void
    {
        [$physical, $components] = $this->branches(
            fields: [
                1 => [
                    'base_type' => 0x00,
                    'bytes' => "\xFF",
                ],
                8 => [
                    'base_type' => 0x0D,
                    'bytes' => "\x04\x2A",
                ],
            ],
        );

        $field = (new FitDataMessageAssembler())
            ->assemble(
                physical: $physical,
                components: $components,
            )
            ->standardField(1);

        self::assertNotNull($field);
        self::assertNotNull($field->physical());

        self::assertFalse(
            $field->physical()->hasUsableValue(),
        );

        $selected = (
        new PreferPhysicalFieldValueSelectionPolicy()
        )->select($field);

        self::assertCount(
            1,
            $selected,
        );

        self::assertSame(
            FieldValueOrigin::Component,
            $selected[0]->origin(),
        );

        self::assertInstanceOf(ComponentFieldValue::class, $selected[0]);

        self::assertSame(
            4,
            $selected[0]
                ->source
                ->value(),
        );
    }

    public function testPreservesRepeatedComponentValuesForSameTarget(): void
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

        $profiled = $this->profiledMessage(
            profile: $profile,
            fields: [
                10 => [
                    'base_type' => 0x0D,
                    'bytes' => "\xA5",
                ],
            ],
        );

        $physical = $this->physicalBranch(
            $profiled,
        );

        $components = $this->componentBranch(
            $profiled,
        );

        $field = (new FitDataMessageAssembler())
            ->assemble(
                physical: $physical,
                components: $components,
            )
            ->standardField(9);

        self::assertNotNull($field);

        self::assertSame(
            [5, 10],
            array_map(
                static fn (
                    ComponentFieldValue $value,
                ): int|float => $value
                    ->source
                    ->value(),
                $field->components(),
            ),
        );
    }

    public function testRejectsBranchesFromDifferentProfiledMessages(): void
    {
        $first = $this->profiledMessage(
            profile: $this->profile(),
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

        $second = $this->profiledMessage(
            profile: $this->profile(),
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

        $this->expectException(
            \InvalidArgumentException::class,
        );

        $this->expectExceptionMessage(
            'same profiled message',
        );

        (new FitDataMessageAssembler())->assemble(
            physical: $this->physicalBranch($first),
            components: $this->componentBranch($second),
        );
    }
}
