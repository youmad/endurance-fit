<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Decoder;

use Youmad\Endurance\Fit\Exception\FitDecodeException;

final class FitDeveloperComponentAccumulator
{
    private const int MINIMUM_BITS = 1;
    private const int MAXIMUM_BITS = 63;

    /**
     * @var array<
     *     string,
     *     array{
     *         accumulated: int,
     *         last: int
     *     }
     * >
     */
    private array $states = [];

    public function accumulate(
        int $globalMessageNumber,
        int $developerDataIndex,
        int $fieldDefinitionNumber,
        int $componentIndex,
        int $packedValue,
        int $bits,
    ): int {
        $mask = $this->mask($bits);

        $key = $this->key(
            globalMessageNumber: $globalMessageNumber,
            developerDataIndex: $developerDataIndex,
            fieldDefinitionNumber: $fieldDefinitionNumber,
            componentIndex: $componentIndex,
        );

        $state = $this->states[$key]
            ?? [
                'accumulated' => 0,
                'last' => 0,
            ];

        $delta = ($packedValue - $state['last'])
            & $mask;

        if (
            PHP_INT_MAX - $state['accumulated']
            < $delta
        ) {
            throw new FitDecodeException(sprintf('Accumulated FIT developer component %d for field %d:%d in message %d exceeds PHP integer range.', $componentIndex, $developerDataIndex, $fieldDefinitionNumber, $globalMessageNumber));
        }

        $accumulated = $state['accumulated']
            + $delta;

        $this->states[$key] = [
            'accumulated' => $accumulated,
            'last' => $packedValue,
        ];

        return $accumulated;
    }

    public function reset(): void
    {
        $this->states = [];
    }

    private function mask(int $bits): int
    {
        if (
            self::MINIMUM_BITS > $bits
            || self::MAXIMUM_BITS < $bits
        ) {
            throw new FitDecodeException('Accumulated FIT developer component width must be between 1 and 63 bits.');
        }

        if (self::MAXIMUM_BITS === $bits) {
            return PHP_INT_MAX;
        }

        return (1 << $bits) - 1;
    }

    private function key(
        int $globalMessageNumber,
        int $developerDataIndex,
        int $fieldDefinitionNumber,
        int $componentIndex,
    ): string {
        foreach (
            [
                'global message number' => [
                    $globalMessageNumber,
                    0,
                    65_535,
                ],
                'developer data index' => [
                    $developerDataIndex,
                    0,
                    255,
                ],
                'field definition number' => [
                    $fieldDefinitionNumber,
                    0,
                    255,
                ],
                'component index' => [
                    $componentIndex,
                    0,
                    PHP_INT_MAX,
                ],
            ] as $description => [$value, $minimum, $maximum]
        ) {
            if (
                $minimum > $value
                || $maximum < $value
            ) {
                throw new FitDecodeException(sprintf('FIT developer %s must be between %d and %d.', $description, $minimum, $maximum));
            }
        }

        return sprintf(
            '%d:%d:%d:%d',
            $globalMessageNumber,
            $developerDataIndex,
            $fieldDefinitionNumber,
            $componentIndex,
        );
    }
}
