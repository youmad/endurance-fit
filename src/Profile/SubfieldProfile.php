<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Profile;

use Youmad\Endurance\Fit\Exception\InvalidFitProfile;

final readonly class SubfieldProfile
{
    /**
     * @var non-empty-list<SubfieldCondition>
     */
    private array $conditions;

    /**
     * @var list<ComponentProfile>
     */
    private array $components;

    /**
     * @param non-empty-list<SubfieldCondition> $conditions
     * @param list<ComponentProfile>            $components
     */
    private function __construct(
        public string $name,
        public string $typeName,
        public FieldTransform $transform,
        public ?string $units,
        array $conditions,
        array $components,
    ) {
        $this->conditions = $conditions;
        $this->components = $components;
    }

    /**
     * @param array<array-key, mixed> $conditions
     * @param array<array-key, mixed> $components
     */
    public static function create(
        string $name,
        string $typeName,
        ?FieldTransform $transform = null,
        ?string $units = null,
        array $conditions = [],
        array $components = [],
    ): self {
        self::assertIdentifier(
            description: 'FIT subfield name',
            value: $name,
        );

        self::assertIdentifier(
            description: 'FIT subfield type',
            value: $typeName,
        );

        if (
            null !== $units
            && (
                '' === $units
                || trim($units) !== $units
            )
        ) {
            throw new InvalidFitProfile('FIT subfield units must be a non-empty trimmed string.');
        }

        $conditions = array_values($conditions);

        if ([] === $conditions) {
            throw new InvalidFitProfile('FIT subfield must define at least one selection condition.');
        }

        $referenceFields = [];

        foreach ($conditions as $condition) {
            if (!$condition instanceof SubfieldCondition) {
                throw new InvalidFitProfile('FIT subfield conditions must contain SubfieldCondition objects.');
            }

            if (
                isset(
                    $referenceFields[$condition->referenceFieldNumber],
                )
            ) {
                throw new InvalidFitProfile(sprintf('FIT subfield %s defines reference field %d more than once.', $name, $condition->referenceFieldNumber));
            }

            $referenceFields[$condition->referenceFieldNumber] = true;
        }

        /** @var non-empty-list<SubfieldCondition> $conditions Validated above. */
        $components = array_values($components);

        foreach ($components as $component) {
            if (!$component instanceof ComponentProfile) {
                throw new InvalidFitProfile('FIT subfield components must contain ComponentProfile objects.');
            }
        }

        /* @var list<ComponentProfile> $components Validated above. */
        /* @var non-empty-list<SubfieldCondition> $conditions */
        return new self(
            name: $name,
            typeName: $typeName,
            transform: $transform
            ?? FieldTransform::identity(),
            units: $units,
            conditions: $conditions,
            components: $components,
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

    /**
     * @return non-empty-list<SubfieldCondition>
     */
    public function conditions(): array
    {
        return $this->conditions;
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
}
