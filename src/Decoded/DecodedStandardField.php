<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Decoded;

use Youmad\Endurance\Fit\Raw\FitBaseType;
use Youmad\Endurance\Fit\Raw\RawStandardField;
use Youmad\Endurance\Fit\Raw\StandardFieldDefinition;

final readonly class DecodedStandardField
{
    private const int TIMESTAMP_FIELD_NUMBER = 253;
    private const int TIMESTAMP_FIELD_SIZE = 4;
    private const int TIMESTAMP_BASE_TYPE_NUMBER = 0x06;

    private function __construct(
        public StandardFieldDefinition $definition,
        public DecodedFieldValue $value,
        public DecodedFieldOrigin $origin,
        public ?RawStandardField $rawField,
    ) {
    }

    public static function fromPayload(
        RawStandardField $rawField,
        DecodedFieldValue $value,
    ): self {
        if (
            !$rawField
                ->definition
                ->baseType
                ->equals($value->baseType())
        ) {
            throw new \InvalidArgumentException('Decoded FIT value base type does not match its raw field definition.');
        }

        return new self(
            definition: $rawField->definition,
            value: $value,
            origin: DecodedFieldOrigin::MessagePayload,
            rawField: $rawField,
        );
    }

    public static function fromImplicitCompressedTimestamp(
        int $timestamp,
    ): self {
        return self::fromCompressedTimestamp(
            definition: StandardFieldDefinition::create(
                fieldNumber: self::TIMESTAMP_FIELD_NUMBER,
                size: self::TIMESTAMP_FIELD_SIZE,
                baseType: FitBaseType::fromDefinitionByte(
                    0x86,
                ),
            ),
            timestamp: $timestamp,
        );
    }

    public static function fromCompressedTimestamp(
        StandardFieldDefinition $definition,
        int $timestamp,
    ): self {
        if (
            self::TIMESTAMP_FIELD_NUMBER
            !== $definition->fieldNumber
            || self::TIMESTAMP_FIELD_SIZE
            !== $definition->size
            || self::TIMESTAMP_BASE_TYPE_NUMBER
            !== $definition->baseType->number()
        ) {
            throw new \InvalidArgumentException('Compressed timestamp requires standard field 253 with a four-byte uint32 base type.');
        }

        if (
            0 > $timestamp
            || 0xFFFFFFFF < $timestamp
        ) {
            throw new \InvalidArgumentException('Decoded FIT timestamp must fit into an unsigned 32-bit integer.');
        }

        return new self(
            definition: $definition,
            value: DecodedFieldElements::create(
                $definition->baseType,
                new ValidFieldElement($timestamp),
            ),
            origin: DecodedFieldOrigin::CompressedTimestampHeader,
            rawField: null,
        );
    }
}
