<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Profile;

use Youmad\Endurance\Fit\Exception\InvalidFitProfile;

final readonly class FieldProfile
{
    private const int MINIMUM_FIELD_NUMBER = 0;
    private const int MAXIMUM_FIELD_NUMBER = 255;

    /**
     * @var list<SubfieldProfile>
     */
    private array $subfields;

    /**
     * @var list<ComponentProfile>
     */
    private array $components;

    /**
     * @param list<SubfieldProfile>  $subfields
     * @param list<ComponentProfile> $components
     */
    private function __construct(
        public int $fieldNumber,
        public string $name,
        public string $typeName,
        public FieldTransform $transform,
        public ?string $units,
        array $subfields,
        array $components,
        public bool $accumulated,
    ) {
        $this->subfields = $subfields;
        $this->components = $components;
    }

    /**
     * @param array<array-key, mixed> $subfields
     * @param array<array-key, mixed> $components
     */
    public static function create(
        int $fieldNumber,
        string $name,
        string $typeName,
        ?FieldTransform $transform = null,
        ?string $units = null,
        array $subfields = [],
        array $components = [],
        bool $accumulated = false,
    ): self {
        if (
            self::MINIMUM_FIELD_NUMBER > $fieldNumber
            || self::MAXIMUM_FIELD_NUMBER < $fieldNumber
        ) {
            throw new InvalidFitProfile('FIT profile field number must be between 0 and 255.');
        }

        self::assertIdentifier(
            description: 'FIT profile field name',
            value: $name,
        );

        self::assertIdentifier(
            description: 'FIT profile field type',
            value: $typeName,
        );

        self::assertUnits($units);

        $subfields = array_values($subfields);
        $subfieldsByName = [];

        foreach ($subfields as $subfield) {
            if (!$subfield instanceof SubfieldProfile) {
                throw new InvalidFitProfile('FIT profile subfields must contain SubfieldProfile objects.');
            }

            if (isset($subfieldsByName[$subfield->name])) {
                throw new InvalidFitProfile(sprintf('FIT profile field %d defines subfield %s more than once.', $fieldNumber, $subfield->name));
            }

            $subfieldsByName[$subfield->name] = true;
        }

        /** @var list<SubfieldProfile> $subfields Validated above. */
        $components = array_values($components);

        foreach ($components as $component) {
            if (!$component instanceof ComponentProfile) {
                throw new InvalidFitProfile('FIT profile components must contain ComponentProfile objects.');
            }
        }

        /* @var list<ComponentProfile> $components Validated above. */
        return new self(
            fieldNumber: $fieldNumber,
            name: $name,
            typeName: $typeName,
            transform: $transform
            ?? FieldTransform::identity(),
            units: $units,
            subfields: $subfields,
            components: $components,
            accumulated: $accumulated,
        );
    }

    private static function assertIdentifier(
        string $description,
        string $value,
    ): void {
        if (
            1 !== preg_match(
                '/^[a-z][a-z0-9_]*$/',
                $value,
            )
        ) {
            throw new InvalidFitProfile(sprintf('%s must be a snake_case identifier.', $description));
        }
    }

    private static function assertUnits(
        ?string $units,
    ): void {
        if (
            null !== $units
            && (
                '' === $units
                || trim($units) !== $units
            )
        ) {
            throw new InvalidFitProfile('FIT profile field units must be a non-empty trimmed string.');
        }
    }

    /**
     * @return list<SubfieldProfile>
     */
    public function subfields(): array
    {
        return $this->subfields;
    }

    /**
     * @return list<ComponentProfile>
     */
    public function components(): array
    {
        return $this->components;
    }

    public function hasComponents(): bool
    {
        return [] !== $this->components;
    }

    public function isAccumulated(): bool
    {
        return $this->accumulated;
    }
}
