<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Tests\Decoder;

use PHPUnit\Framework\TestCase;
use Youmad\Endurance\Fit\Decoded\DecodedFieldElements;
use Youmad\Endurance\Fit\Decoder\FitDataMessageDecoder;
use Youmad\Endurance\Fit\Decoder\FitProfileNormalizer;
use Youmad\Endurance\Fit\Decoder\FitTypeValueResolver;
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
use Youmad\Endurance\Fit\Typed\TypedDataMessage;
use Youmad\Endurance\Fit\Typed\TypedEnumFieldElement;
use Youmad\Endurance\Fit\Typed\TypedFieldElements;
use Youmad\Endurance\Fit\Typed\TypedInvalidFieldElement;
use Youmad\Endurance\Fit\Typed\TypedScalarFieldElement;
use Youmad\Endurance\Fit\Typed\TypeResolutionState;
use Youmad\Endurance\Fit\Typed\UnavailableTypedFieldValue;

final class FitTypeValueResolverTest extends TestCase
{
    public function testAddsSymbolicNameWithoutReplacingNumber(): void
    {
        $message = $this->profiledMessage(
            value: "\x04",
            baseType: 0x00,
            fieldTypeName: 'file',
        );

        $typed = $this->resolver(
            $this->fileType(),
        )->resolve($message);

        $field = $typed->standardField(0);

        self::assertNotNull($field);

        self::assertSame(
            TypeResolutionState::Applied,
            $field->resolutionState,
        );

        $element = $this->typedElement(
            $field->value,
            0,
        );

        self::assertInstanceOf(
            TypedEnumFieldElement::class,
            $element,
        );

        self::assertSame(
            4,
            $element->value,
        );

        self::assertSame(
            'activity',
            $element->name(),
        );

        self::assertTrue(
            $element->isKnown(),
        );

        self::assertSame(
            4,
            $this->profiledNumericValue(
                $field->source->value,
            ),
        );
    }

    private function profiledMessage(
        string $value,
        int $baseType,
        string $fieldTypeName,
        int $fieldSize = 1,
    ): ProfiledDataMessage {
        $fieldDefinition = StandardFieldDefinition::create(
            fieldNumber: 0,
            size: $fieldSize,
            baseType: FitBaseType::fromDefinitionByte(
                $baseType,
            ),
        );

        $messageDefinition = MessageDefinition::create(
            localMessageNumber: 0,
            architecture: FitArchitecture::LittleEndian,
            globalMessageNumber: 0,
            standardFields: [$fieldDefinition],
        );

        $raw = RawDataMessage::create(
            sequenceNumber: 1,
            byteOffset: 20,
            recordHeaderByte: 0x00,
            definition: $messageDefinition,
            standardFields: [
                new RawStandardField(
                    definition: $fieldDefinition,
                    value: RawFieldValue::fromBytes(
                        $value,
                    ),
                ),
            ],
        );

        $decoded = (new FitDataMessageDecoder())
            ->decode($raw);

        return (
        new FitProfileNormalizer(
            new InMemoryFitProfileRegistry(
                MessageProfile::create(
                    globalMessageNumber: 0,
                    name: 'file_id',
                    fields: [
                        FieldProfile::create(
                            fieldNumber: 0,
                            name: 'type',
                            typeName: $fieldTypeName,
                        ),
                    ],
                ),
            ),
        )
        )->normalize($decoded);
    }

    private function resolver(
        FitTypeProfile ...$types,
    ): FitTypeValueResolver {
        return new FitTypeValueResolver(
            new InMemoryFitTypeRegistry(
                types: $types,
            ),
        );
    }

    private function fileType(): FitTypeProfile
    {
        return FitTypeProfile::create(
            name: 'file',
            values: [
                TypeValueProfile::create(
                    value: 4,
                    name: 'activity',
                ),
                TypeValueProfile::create(
                    value: 6,
                    name: 'course',
                ),
            ],
        );
    }

    private function typedElement(
        mixed $value,
        int $index,
    ): mixed {
        self::assertInstanceOf(
            TypedFieldElements::class,
            $value,
        );

        return $value->elements()[$index];
    }

    private function profiledNumericValue(
        mixed $value,
    ): int {
        self::assertInstanceOf(
            DecodedFieldElements::class,
            $value,
        );

        $element = $value->elements()[0];

        self::assertInstanceOf(
            \Youmad\Endurance\Fit\Decoded\ValidFieldElement::class,
            $element,
        );

        self::assertIsInt(
            $element->value,
        );

        return $element->value;
    }

    public function testUnknownEnumValueRemainsAvailable(): void
    {
        $typed = $this->resolver(
            $this->fileType(),
        )->resolve(
            $this->profiledMessage(
                value: "\x63",
                baseType: 0x00,
                fieldTypeName: 'file',
            ),
        );

        $field = $typed->standardField(0);

        self::assertNotNull($field);

        $element = $this->typedElement(
            $field->value,
            0,
        );

        self::assertInstanceOf(
            TypedEnumFieldElement::class,
            $element,
        );

        self::assertSame(
            99,
            $element->value,
        );

        self::assertNull(
            $element->name(),
        );

        self::assertFalse(
            $element->isKnown(),
        );
    }

    public function testResolvesArrayElementsIndependently(): void
    {
        $typed = $this->resolver(
            $this->fileType(),
        )->resolve(
            $this->profiledMessage(
                value: "\x04\xFF\x63",
                baseType: 0x00,
                fieldTypeName: 'file',
                fieldSize: 3,
            ),
        );

        $field = $typed->standardField(0);

        self::assertNotNull($field);

        self::assertInstanceOf(
            TypedFieldElements::class,
            $field->value,
        );

        $elements = $field->value->elements();

        self::assertInstanceOf(
            TypedEnumFieldElement::class,
            $elements[0],
        );

        self::assertSame(
            'activity',
            $elements[0]->name(),
        );

        self::assertInstanceOf(
            TypedInvalidFieldElement::class,
            $elements[1],
        );

        self::assertInstanceOf(
            TypedEnumFieldElement::class,
            $elements[2],
        );

        self::assertSame(
            99,
            $elements[2]->value,
        );

        self::assertNull(
            $elements[2]->name(),
        );
    }

    public function testPrimitiveTypeRemainsScalar(): void
    {
        $typed = $this->resolver()->resolve(
            $this->profiledMessage(
                value: "\x96",
                baseType: 0x02,
                fieldTypeName: 'uint8',
            ),
        );

        $field = $typed->standardField(0);

        self::assertNotNull($field);

        self::assertSame(
            TypeResolutionState::NotApplicable,
            $field->resolutionState,
        );

        self::assertNull(
            $field->typeProfile,
        );

        $element = $this->typedElement(
            $field->value,
            0,
        );

        self::assertInstanceOf(
            TypedScalarFieldElement::class,
            $element,
        );

        self::assertSame(
            150,
            $element->value(),
        );
    }

    public function testKnownTypeCannotResolveFloatValue(): void
    {
        $typed = $this->resolver(
            $this->fileType(),
        )->resolve(
            $this->profiledMessage(
                value: pack('g', 4.0),
                baseType: 0x88,
                fieldTypeName: 'file',
                fieldSize: 4,
            ),
        );

        $field = $typed->standardField(0);

        self::assertNotNull($field);

        self::assertSame(
            TypeResolutionState::Unavailable,
            $field->resolutionState,
        );

        self::assertInstanceOf(
            UnavailableTypedFieldValue::class,
            $field->value,
        );

        self::assertStringContainsString(
            'requires integer values',
            $field->value->reason,
        );
    }

    public function testUndecodableValueRemainsAvailable(): void
    {
        $typed = $this->resolver(
            FitTypeProfile::create(
                name: 'custom_type',
            ),
        )->resolve(
            $this->profiledMessage(
                value: "\x12\x34",
                baseType: 0x1F,
                fieldTypeName: 'custom_type',
                fieldSize: 2,
            ),
        );

        $field = $typed->standardField(0);

        self::assertNotNull($field);

        self::assertSame(
            TypeResolutionState::Unavailable,
            $field->resolutionState,
        );

        self::assertInstanceOf(
            UnavailableTypedFieldValue::class,
            $field->value,
        );

        self::assertSame(
            $field->source->value,
            $field->value->source,
        );
    }

    public function testResolvesStreamLazily(): void
    {
        $message = $this->profiledMessage(
            value: "\x04",
            baseType: 0x00,
            fieldTypeName: 'file',
        );

        $log = [];

        $stream = (
            static function () use (
                &$log,
                $message,
            ): iterable {
                $log[] = 'yield';

                yield 8 => $message;
            }
        )();

        $typedStream = $this->resolver(
            $this->fileType(),
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
            [8],
            array_keys($messages),
        );

        self::assertInstanceOf(
            TypedDataMessage::class,
            $messages[8],
        );

        $field = $messages[8]->standardField(0);

        self::assertNotNull($field);

        $element = $this->typedElement(
            $field->value,
            0,
        );

        self::assertInstanceOf(
            TypedEnumFieldElement::class,
            $element,
        );

        self::assertSame(
            'activity',
            $element->name(),
        );
    }
}
