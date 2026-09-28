<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Profile;

use Youmad\Endurance\Fit\Exception\InvalidFitProfile;

final readonly class TypeValueProfile
{
    /**
     * @var list<string>
     */
    private array $aliases;

    /**
     * @param list<string> $aliases
     */
    private function __construct(
        public int $value,
        public string $name,
        array $aliases,
    ) {
        $this->aliases = $aliases;
    }

    /**
     * @param array<array-key, string> $aliases
     */
    public static function create(
        int $value,
        string $name,
        array $aliases = [],
    ): self {
        self::assertName($name);

        $aliases = array_values($aliases);
        $seen = [$name => true];

        foreach ($aliases as $alias) {
            self::assertName($alias);

            if (isset($seen[$alias])) {
                throw new InvalidFitProfile(sprintf('FIT type value %d defines name %s more than once.', $value, $alias));
            }

            $seen[$alias] = true;
        }

        return new self(
            value: $value,
            name: $name,
            aliases: $aliases,
        );
    }

    /**
     * @return list<string>
     */
    public function aliases(): array
    {
        return $this->aliases;
    }

    /**
     * @return non-empty-list<string>
     */
    public function names(): array
    {
        return [
            $this->name,
            ...$this->aliases,
        ];
    }

    public function hasName(string $name): bool
    {
        return in_array(
            $name,
            $this->names(),
            true,
        );
    }

    private static function assertName(string $name): void
    {
        if (
            1 !== preg_match(
                '/^[a-z0-9][a-z0-9_]*$/',
                $name,
            )
        ) {
            throw new InvalidFitProfile('FIT type value name must be a snake_case identifier.');
        }
    }
}
