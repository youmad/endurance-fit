<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Developer;

use Youmad\Endurance\Fit\Exception\InvalidFitProfile;

final readonly class DeveloperComponentProfile
{
    private const int MINIMUM_BITS = 1;
    private const int MAXIMUM_BITS = 63;

    private function __construct(
        public string $name,
        public int $bits,
        public bool $accumulated,
    ) {
    }

    public static function create(
        string $name,
        int $bits,
        bool $accumulated = false,
    ): self {
        if (
            '' === $name
            || trim($name) !== $name
        ) {
            throw new InvalidFitProfile('FIT developer component name must be non-empty and trimmed.');
        }

        if (
            self::MINIMUM_BITS > $bits
            || self::MAXIMUM_BITS < $bits
        ) {
            throw new InvalidFitProfile('FIT developer component size must be between 1 and 63 bits.');
        }

        return new self(
            name: $name,
            bits: $bits,
            accumulated: $accumulated,
        );
    }

    /**
     * @return list<self>
     */
    public static function fromMetadata(
        ?string $components,
        ?string $bits,
        ?string $accumulate,
    ): array {
        if (
            null === $components
            && null === $bits
            && null === $accumulate
        ) {
            return [];
        }

        if (null === $components) {
            throw new InvalidFitProfile('FIT developer component metadata requires component names.');
        }

        if (null === $bits) {
            throw new InvalidFitProfile('FIT developer component metadata requires bit widths.');
        }

        $names = self::split(
            value: $components,
            description: 'FIT developer component names',
        );

        $widths = self::split(
            value: $bits,
            description: 'FIT developer component bit widths',
        );

        if (count($names) !== count($widths)) {
            throw new InvalidFitProfile('FIT developer component names and bit widths must have the same number of entries.');
        }

        $accumulated = null === $accumulate
            ? array_fill(
                start_index: 0,
                count: count($names),
                value: false,
            )
            : self::accumulationFlags(
                value: $accumulate,
                componentCount: count($names),
            );

        $profiles = [];

        foreach ($names as $index => $name) {
            $width = $widths[$index];

            if (1 !== preg_match('/^[0-9]+$/', $width)) {
                throw new InvalidFitProfile(sprintf('FIT developer component %s has invalid bit width %s.', $name, $width));
            }

            $profiles[] = self::create(
                name: $name,
                bits: (int) $width,
                accumulated: $accumulated[$index],
            );
        }

        return $profiles;
    }

    /**
     * @return list<string>
     */
    private static function split(
        string $value,
        string $description,
    ): array {
        $entries = array_map(
            static fn (string $entry): string => trim($entry),
            explode(',', $value),
        );

        foreach ($entries as $entry) {
            if ('' === $entry) {
                throw new InvalidFitProfile(sprintf('%s must not contain empty entries.', $description));
            }
        }

        return $entries;
    }

    /**
     * @return list<bool>
     */
    private static function accumulationFlags(
        string $value,
        int $componentCount,
    ): array {
        $entries = self::split(
            value: $value,
            description: 'FIT developer component accumulation flags',
        );

        if ($componentCount !== count($entries)) {
            throw new InvalidFitProfile('FIT developer component accumulation flags must match the component count.');
        }

        $flags = [];

        foreach ($entries as $entry) {
            $flags[] = match ($entry) {
                '0' => false,
                '1' => true,
                default => throw new InvalidFitProfile(sprintf('FIT developer component accumulation flag must be 0 or 1; got %s.', $entry)),
            };
        }

        return $flags;
    }
}
