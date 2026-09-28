<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Profile;

use Youmad\Endurance\Fit\Exception\InvalidFitProfile;

final readonly class InMemoryFitTypeRegistry implements FitTypeRegistry
{
    /**
     * @var array<string, FitTypeProfile>
     */
    private array $types;

    /**
     * @param array<array-key, FitTypeProfile> $types
     */
    public function __construct(array $types = [])
    {
        $typesByName = [];

        foreach (array_values($types) as $type) {
            if (isset($typesByName[$type->name])) {
                throw new InvalidFitProfile(sprintf('FIT type %s is registered more than once.', $type->name));
            }

            $typesByName[$type->name] = $type;
        }

        $this->types = $typesByName;
    }

    public function type(string $name): ?FitTypeProfile
    {
        return $this->types[$name] ?? null;
    }
}
