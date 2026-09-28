<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Profile\Generation;

final readonly class ProfileIdentifierNormalizer
{
    public function normalize(string $value): string
    {
        $value = trim($value);
        $value = preg_replace(
            '/([A-Z]+)([A-Z][a-z])/',
            '$1_$2',
            $value,
        ) ?? $value;
        $value = preg_replace(
            '/([a-z0-9])([A-Z])/',
            '$1_$2',
            $value,
        ) ?? $value;
        $value = preg_replace(
            '/[^A-Za-z0-9]+/',
            '_',
            $value,
        ) ?? $value;
        $value = preg_replace(
            '/_+/',
            '_',
            $value,
        ) ?? $value;

        $value = strtolower(
            trim($value, '_'),
        );

        if (
            1 !== preg_match(
                '/^[a-z0-9][a-z0-9_]*$/',
                $value,
            )
        ) {
            throw new ProfileGenerationException('FIT profile identifier cannot be normalized to snake_case.');
        }

        return $value;
    }
}
