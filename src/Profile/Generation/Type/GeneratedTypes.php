<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Profile\Generation\Type;

use Youmad\Endurance\Fit\Profile\Generation\ProfileGenerationException;

final readonly class GeneratedTypes
{
    /**
     * @var list<GeneratedTypeDefinition>
     */
    private array $types;

    /**
     * @param list<GeneratedTypeDefinition> $types
     */
    private function __construct(array $types)
    {
        $this->types = $types;
    }

    /**
     * @param array<array-key, mixed> $types
     */
    public static function create(array $types): self
    {
        $types = array_values($types);
        $names = [];

        foreach ($types as $type) {
            if (!$type instanceof GeneratedTypeDefinition) {
                throw new ProfileGenerationException('FIT generated types must contain GeneratedTypeDefinition objects.');
            }

            if (isset($names[$type->name])) {
                throw new ProfileGenerationException(sprintf('FIT generated type %s is defined more than once.', $type->name));
            }

            $names[$type->name] = true;
        }

        /* @var list<GeneratedTypeDefinition> $types Validated above. */
        return new self($types);
    }

    /**
     * @return list<GeneratedTypeDefinition>
     */
    public function types(): array
    {
        return $this->types;
    }

    public function type(string $name): ?GeneratedTypeDefinition
    {
        foreach ($this->types as $type) {
            if ($name === $type->name) {
                return $type;
            }
        }

        return null;
    }

    public function count(): int
    {
        return count($this->types);
    }

    public function valueCount(): int
    {
        $count = 0;

        foreach ($this->types as $type) {
            foreach ($type->values() as $value) {
                $count += count($value->names());
            }
        }

        return $count;
    }
}
