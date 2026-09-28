<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Developer;

use Youmad\Endurance\Fit\Exception\InvalidFitProfile;
use Youmad\Endurance\Fit\Profile\FieldProfile;
use Youmad\Endurance\Fit\Profile\FieldTransform;
use Youmad\Endurance\Fit\Raw\FitBaseType;
use Youmad\Endurance\Fit\Raw\FitBaseTypeKind;

final readonly class DeveloperFieldProfile
{
    private const int MINIMUM_BYTE_VALUE = 0;
    private const int MAXIMUM_BYTE_VALUE = 255;
    private const int MAXIMUM_UNSIGNED_16_BIT_VALUE = 0xFFFF;

    /**
     * @var list<DeveloperComponentProfile>
     */
    private array $componentProfiles;

    /**
     * @param list<DeveloperComponentProfile> $componentProfiles
     */
    private function __construct(
        public int $developerDataIndex,
        public int $fieldDefinitionNumber,
        public FitBaseType $baseType,
        public string $name,
        public bool $isArray,
        public FieldTransform $transform,
        public ?string $units,
        public ?string $components,
        public ?string $bits,
        public ?string $accumulate,
        array $componentProfiles,
        public ?int $fitBaseUnitId,
        public ?int $nativeMessageNumber,
        public ?int $nativeFieldNumber,
        public ?FieldProfile $nativeField,
    ) {
        $this->componentProfiles = $componentProfiles;
    }

    public static function create(
        int $developerDataIndex,
        int $fieldDefinitionNumber,
        FitBaseType $baseType,
        string $name,
        bool $isArray = false,
        ?FieldTransform $transform = null,
        ?string $units = null,
        ?string $components = null,
        ?string $bits = null,
        ?string $accumulate = null,
        ?int $fitBaseUnitId = null,
        ?int $nativeMessageNumber = null,
        ?int $nativeFieldNumber = null,
        ?FieldProfile $nativeField = null,
    ): self {
        self::assertByte(
            name: 'FIT developer data index',
            value: $developerDataIndex,
        );

        self::assertByte(
            name: 'FIT developer field definition number',
            value: $fieldDefinitionNumber,
        );

        self::assertOptionalUnsignedInt16(
            name: 'FIT developer base unit id',
            value: $fitBaseUnitId,
        );

        if (
            null !== $nativeMessageNumber
            && (
                0 > $nativeMessageNumber
                || 0xFFFF < $nativeMessageNumber
            )
        ) {
            throw new InvalidFitProfile('Native FIT message number must fit into an unsigned 16-bit integer.');
        }

        self::assertOptionalByte(
            name: 'Native FIT field number',
            value: $nativeFieldNumber,
        );

        $name = self::requiredText(
            value: $name,
            description: 'FIT developer field name',
        );

        // Units are opaque FIT string metadata. Unlike component
        // declarations, an empty or whitespace-only first string is
        // accepted by the Garmin SDK and must not invalidate the field.

        $components = self::optionalText(
            value: $components,
            description: 'FIT developer field components',
        );

        $bits = self::optionalText(
            value: $bits,
            description: 'FIT developer field bits',
        );

        $accumulate = self::optionalText(
            value: $accumulate,
            description: 'FIT developer field accumulation metadata',
        );

        $componentProfiles = DeveloperComponentProfile::fromMetadata(
            components: $components,
            bits: $bits,
            accumulate: $accumulate,
        );

        if ([] !== $componentProfiles) {
            $kind = $baseType->kind();

            if (
                null === $kind
                || in_array(
                    $kind,
                    [
                        FitBaseTypeKind::StringValue,
                        FitBaseTypeKind::Float32,
                        FitBaseTypeKind::Float64,
                    ],
                    true,
                )
            ) {
                throw new InvalidFitProfile('FIT developer components require an integer-compatible base type.');
            }
        }

        if (null !== $nativeField) {
            if (
                null === $nativeMessageNumber
                || null === $nativeFieldNumber
                || $nativeField->fieldNumber
                    !== $nativeFieldNumber
            ) {
                throw new InvalidFitProfile('Resolved native FIT field must match its message and field reference.');
            }
        }

        return new self(
            developerDataIndex: $developerDataIndex,
            fieldDefinitionNumber: $fieldDefinitionNumber,
            baseType: $baseType,
            name: $name,
            isArray: $isArray,
            transform: $transform
                ?? FieldTransform::identity(),
            units: $units,
            components: $components,
            bits: $bits,
            accumulate: $accumulate,
            componentProfiles: $componentProfiles,
            fitBaseUnitId: $fitBaseUnitId,
            nativeMessageNumber: $nativeMessageNumber,
            nativeFieldNumber: $nativeFieldNumber,
            nativeField: $nativeField,
        );
    }

    /**
     * Type name of the resolved native equivalent, when available.
     *
     * Developer data is decoded using field_description metadata; this
     * native type is informational and must not drive value decoding.
     */
    public function typeName(): ?string
    {
        return $this->nativeField?->typeName;
    }

    /**
     * Units declared by field_description.
     *
     * Native override metadata is informational and is not inherited by
     * the developer field during decoding.
     */
    public function effectiveUnits(): ?string
    {
        return $this->units;
    }

    /**
     * @return list<DeveloperComponentProfile>
     */
    public function componentProfiles(): array
    {
        return $this->componentProfiles;
    }

    public function hasComponents(): bool
    {
        return [] !== $this->componentProfiles;
    }

    public function equals(self $other): bool
    {
        return $this->developerDataIndex
                === $other->developerDataIndex
            && $this->fieldDefinitionNumber
                === $other->fieldDefinitionNumber
            && $this->baseType->equals($other->baseType)
            && $this->name === $other->name
            && $this->isArray === $other->isArray
            && $this->transform->scale
                === $other->transform->scale
            && $this->transform->offset
                === $other->transform->offset
            && $this->units === $other->units
            && $this->components === $other->components
            && $this->bits === $other->bits
            && $this->accumulate === $other->accumulate
            && $this->fitBaseUnitId
                === $other->fitBaseUnitId
            && $this->nativeMessageNumber
                === $other->nativeMessageNumber
            && $this->nativeFieldNumber
                === $other->nativeFieldNumber;
    }

    private static function assertByte(
        string $name,
        int $value,
    ): void {
        if (
            self::MINIMUM_BYTE_VALUE > $value
            || self::MAXIMUM_BYTE_VALUE < $value
        ) {
            throw new InvalidFitProfile(sprintf('%s must fit into one byte.', $name));
        }
    }

    private static function assertOptionalByte(
        string $name,
        ?int $value,
    ): void {
        if (null !== $value) {
            self::assertByte(
                name: $name,
                value: $value,
            );
        }
    }

    private static function assertOptionalUnsignedInt16(
        string $name,
        ?int $value,
    ): void {
        if (null === $value) {
            return;
        }

        if (
            0 > $value
            || self::MAXIMUM_UNSIGNED_16_BIT_VALUE < $value
        ) {
            throw new InvalidFitProfile(sprintf('%s must fit into an unsigned 16-bit integer.', $name));
        }
    }

    private static function requiredText(
        string $value,
        string $description,
    ): string {
        if (
            '' === $value
            || trim($value) !== $value
        ) {
            throw new InvalidFitProfile(sprintf('%s must be non-empty and trimmed.', $description));
        }

        return $value;
    }

    private static function optionalText(
        ?string $value,
        string $description,
    ): ?string {
        if (null === $value) {
            return null;
        }

        return self::requiredText(
            value: $value,
            description: $description,
        );
    }
}
