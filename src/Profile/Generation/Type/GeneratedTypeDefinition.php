<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Profile\Generation\Type;

use Youmad\Endurance\Fit\Profile\Generation\ProfileGenerationException;

final readonly class GeneratedTypeDefinition
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
     * @var list<GeneratedTypeValueDefinition>
     */
    private array $values;

    /**
     * @param list<GeneratedTypeValueDefinition> $values
     */
    private function __construct(
        public string $name,
        public string $baseType,
        array $values,
    ) {
        $this->values = $values;
    }

    /**
     * @param array<array-key, mixed> $values
     */
    public static function create(
        string $name,
        string $baseType,
        array $values = [],
    ): self {
        if (
            1 !== preg_match(
                '/^[a-z][a-z0-9_]*$/',
                $name,
            )
        ) {
            throw new ProfileGenerationException('FIT generated type name must be a snake_case identifier.');
        }

        if (!isset(self::BASE_TYPES[$baseType])) {
            throw new ProfileGenerationException(sprintf('FIT generated type %s uses unknown base type %s.', $name, $baseType));
        }

        $values = array_values($values);
        $valuesByNumber = [];
        $names = [];

        foreach ($values as $value) {
            if (!$value instanceof GeneratedTypeValueDefinition) {
                throw new ProfileGenerationException('FIT generated type values must contain GeneratedTypeValueDefinition objects.');
            }

            if (isset($valuesByNumber[$value->value])) {
                throw new ProfileGenerationException(sprintf('FIT generated type %s defines value %d more than once.', $name, $value->value));
            }

            foreach ($value->names() as $valueName) {
                if (isset($names[$valueName])) {
                    throw new ProfileGenerationException(sprintf('FIT generated type %s defines name %s more than once.', $name, $valueName));
                }

                $names[$valueName] = true;
            }

            $valuesByNumber[$value->value] = true;
        }

        /* @var list<GeneratedTypeValueDefinition> $values Validated above. */
        return new self(
            name: $name,
            baseType: $baseType,
            values: $values,
        );
    }

    /**
     * @return list<GeneratedTypeValueDefinition>
     */
    public function values(): array
    {
        return $this->values;
    }

    public function valueByName(string $name): ?GeneratedTypeValueDefinition
    {
        foreach ($this->values as $value) {
            if ($value->hasName($name)) {
                return $value;
            }
        }

        return null;
    }
}
