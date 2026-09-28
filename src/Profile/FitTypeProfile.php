<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Profile;

use Youmad\Endurance\Fit\Exception\InvalidFitProfile;

final readonly class FitTypeProfile
{
    private const array BASE_TYPES = [
        'enum' => true,
        'sint8' => true,
        'uint8' => true,
        'sint16' => true,
        'uint16' => true,
        'sint32' => true,
        'uint32' => true,
        'string' => true,
        'float32' => true,
        'float64' => true,
        'uint8z' => true,
        'uint16z' => true,
        'uint32z' => true,
        'byte' => true,
        'sint64' => true,
        'uint64' => true,
        'uint64z' => true,
    ];

    /**
     * @var list<TypeValueProfile>
     */
    private array $values;

    /**
     * @var array<int, TypeValueProfile>
     */
    private array $valuesByNumber;

    /**
     * @param list<TypeValueProfile>       $values
     * @param array<int, TypeValueProfile> $valuesByNumber
     */
    private function __construct(
        public string $name,
        public ?string $baseType,
        array $values,
        array $valuesByNumber,
    ) {
        $this->values = $values;
        $this->valuesByNumber = $valuesByNumber;
    }

    /**
     * @param array<array-key, mixed> $values
     */
    public static function create(
        string $name,
        array $values = [],
        ?string $baseType = null,
    ): self {
        if (
            1 !== preg_match(
                '/^[a-z][a-z0-9_]*$/',
                $name,
            )
        ) {
            throw new InvalidFitProfile('FIT type name must be a snake_case identifier.');
        }

        if (
            null !== $baseType
            && !isset(self::BASE_TYPES[$baseType])
        ) {
            throw new InvalidFitProfile(sprintf('FIT type %s uses unknown base type %s.', $name, $baseType));
        }

        $values = array_values($values);
        $valuesByNumber = [];
        $names = [];

        foreach ($values as $value) {
            if (!$value instanceof TypeValueProfile) {
                throw new InvalidFitProfile('FIT type values must contain TypeValueProfile objects.');
            }

            if (isset($valuesByNumber[$value->value])) {
                throw new InvalidFitProfile(sprintf('FIT type %s defines value %d more than once.', $name, $value->value));
            }

            foreach ($value->names() as $valueName) {
                if (isset($names[$valueName])) {
                    throw new InvalidFitProfile(sprintf('FIT type %s defines name %s more than once.', $name, $valueName));
                }

                $names[$valueName] = true;
            }

            $valuesByNumber[$value->value] = $value;
        }

        /* @var list<TypeValueProfile> $values Validated above. */
        return new self(
            name: $name,
            baseType: $baseType,
            values: $values,
            valuesByNumber: $valuesByNumber,
        );
    }

    /**
     * @return list<TypeValueProfile>
     */
    public function values(): array
    {
        return $this->values;
    }

    public function value(int $number): ?TypeValueProfile
    {
        return $this->valuesByNumber[$number] ?? null;
    }
}
