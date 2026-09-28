<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Decoder;

use Youmad\Endurance\Fit\Exception\FitDecodeException;

final class FitComponentAccumulator
{
    private const int MINIMUM_MESSAGE_NUMBER = 0;
    private const int MAXIMUM_MESSAGE_NUMBER = 65_535;

    private const int MINIMUM_FIELD_NUMBER = 0;
    private const int MAXIMUM_FIELD_NUMBER = 255;

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

    public function seed(
        int $globalMessageNumber,
        int $fieldNumber,
        int $value,
    ): void {
        $this->assertIdentity(
            globalMessageNumber: $globalMessageNumber,
            fieldNumber: $fieldNumber,
        );

        $key = $this->key(
            globalMessageNumber: $globalMessageNumber,
            fieldNumber: $fieldNumber,
        );

        $this->states[$key] = [
            'accumulated' => $value,
            'last' => $value,
        ];
    }

    private function assertIdentity(
        int $globalMessageNumber,
        int $fieldNumber,
    ): void {
        if (
            self::MINIMUM_MESSAGE_NUMBER
            > $globalMessageNumber
            || self::MAXIMUM_MESSAGE_NUMBER
            < $globalMessageNumber
        ) {
            throw new FitDecodeException('FIT global message number must fit into an unsigned 16-bit integer.');
        }

        if (
            self::MINIMUM_FIELD_NUMBER
            > $fieldNumber
            || self::MAXIMUM_FIELD_NUMBER
            < $fieldNumber
        ) {
            throw new FitDecodeException('FIT field number must be between 0 and 255.');
        }
    }

    private function key(
        int $globalMessageNumber,
        int $fieldNumber,
    ): string {
        return sprintf(
            '%d:%d',
            $globalMessageNumber,
            $fieldNumber,
        );
    }

    public function accumulate(
        int $globalMessageNumber,
        int $fieldNumber,
        int $packedValue,
        int $bits,
    ): int {
        $this->assertIdentity(
            globalMessageNumber: $globalMessageNumber,
            fieldNumber: $fieldNumber,
        );

        $mask = $this->mask($bits);

        $key = $this->key(
            globalMessageNumber: $globalMessageNumber,
            fieldNumber: $fieldNumber,
        );

        $state = $this->states[$key]
            ?? [
                'accumulated' => 0,
                'last' => 0,
            ];

        $difference = $packedValue
            - $state['last'];

        $delta = $difference & $mask;

        if (
            PHP_INT_MAX - $state['accumulated']
            < $delta
        ) {
            throw new FitDecodeException(sprintf('Accumulated FIT field %d in message %d exceeds PHP integer range.', $fieldNumber, $globalMessageNumber));
        }

        $accumulated = $state['accumulated']
            + $delta;

        $this->states[$key] = [
            'accumulated' => $accumulated,
            'last' => $packedValue,
        ];

        return $accumulated;
    }

    private function mask(int $bits): int
    {
        if (
            self::MINIMUM_BITS > $bits
            || self::MAXIMUM_BITS < $bits
        ) {
            throw new FitDecodeException('Accumulated FIT component width must be between 1 and 63 bits.');
        }

        if (self::MAXIMUM_BITS === $bits) {
            return PHP_INT_MAX;
        }

        return (1 << $bits) - 1;
    }

    public function has(
        int $globalMessageNumber,
        int $fieldNumber,
    ): bool {
        return isset(
            $this->states[$this->key(
                globalMessageNumber: $globalMessageNumber,
                fieldNumber: $fieldNumber,
            )],
        );
    }

    public function reset(): void
    {
        $this->states = [];
    }
}
