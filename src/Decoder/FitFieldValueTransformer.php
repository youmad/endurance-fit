<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Decoder;

use Youmad\Endurance\Fit\Decoded\DecodedFieldElements;
use Youmad\Endurance\Fit\Decoded\DecodedFieldValue;
use Youmad\Endurance\Fit\Decoded\InvalidFieldElement;
use Youmad\Endurance\Fit\Decoded\ValidFieldElement;
use Youmad\Endurance\Fit\Profile\FieldTransform;

final readonly class FitFieldValueTransformer
{
    public function apply(
        DecodedFieldValue $value,
        FieldTransform $transform,
    ): ?DecodedFieldElements {
        if (!$value instanceof DecodedFieldElements) {
            return null;
        }

        foreach ($value->elements() as $element) {
            if (
                $element instanceof ValidFieldElement
                && !is_int($element->value)
                && !is_float($element->value)
                && !$this->isLargeUnsignedInteger(
                    $element->value,
                )
            ) {
                return null;
            }
        }

        $elements = [];

        foreach ($value->elements() as $element) {
            if ($element instanceof InvalidFieldElement) {
                $elements[] = $element;

                continue;
            }

            if (!$element instanceof ValidFieldElement) {
                return null;
            }

            $rawValue = $element->value;

            if (is_string($rawValue)) {
                $transformed = $this->applyLargeUnsignedInteger(
                    rawValue: $rawValue,
                    transform: $transform,
                );

                if (null === $transformed) {
                    return null;
                }

                $elements[] = new ValidFieldElement(
                    $transformed,
                );

                continue;
            }

            $elements[] = new ValidFieldElement(
                $transform->apply($rawValue),
            );
        }

        return DecodedFieldElements::create(
            $value->baseType(),
            ...$elements,
        );
    }

    public function containsValidElement(
        DecodedFieldValue $value,
    ): bool {
        if (!$value instanceof DecodedFieldElements) {
            return false;
        }

        foreach ($value->elements() as $element) {
            if ($element instanceof ValidFieldElement) {
                return true;
            }
        }

        return false;
    }

    private function isLargeUnsignedInteger(
        mixed $value,
    ): bool {
        return is_string($value)
            && 1 === preg_match(
                '/^[1-9][0-9]*$/D',
                $value,
            )
            && $this->compareUnsignedDecimals(
                $value,
                (string) PHP_INT_MAX,
            ) > 0;
    }

    private function applyLargeUnsignedInteger(
        string $rawValue,
        FieldTransform $transform,
    ): int|string|null {
        $scale = $this->developerScale(
            $transform->scale,
        );
        $offset = $this->developerOffset(
            $transform->offset,
        );

        if (null === $scale || null === $offset) {
            return null;
        }

        $adjustment = $offset * $scale;
        $numerator = 0 <= $adjustment
            ? $this->subtractSmallUnsignedInteger(
                decimal: $rawValue,
                subtraction: $adjustment,
            )
            : $this->addSmallUnsignedInteger(
                decimal: $rawValue,
                addition: -$adjustment,
            );

        if (null === $numerator) {
            return null;
        }

        [$integerPart, $remainder] = $this
            ->divideUnsignedDecimalByInteger(
                decimal: $numerator,
                divisor: $scale,
            );

        if (0 === $remainder) {
            return $this->fitsPhpInteger($integerPart)
                ? (int) $integerPart
                : $integerPart;
        }

        $denominator = intdiv(
            $scale,
            $this->greatestCommonDivisor(
                $remainder,
                $scale,
            ),
        );

        while (0 === $denominator % 2) {
            $denominator = intdiv($denominator, 2);
        }

        while (0 === $denominator % 5) {
            $denominator = intdiv($denominator, 5);
        }

        if (1 !== $denominator) {
            return null;
        }

        $fraction = '';

        while (0 !== $remainder) {
            $value = $remainder * 10;
            $fraction .= (string) intdiv(
                $value,
                $scale,
            );
            $remainder = $value % $scale;
        }

        return $integerPart.'.'.$fraction;
    }

    private function developerScale(float $scale): ?int
    {
        if (
            $scale !== floor($scale)
            || 1 > $scale
            || 254 < $scale
        ) {
            return null;
        }

        return (int) $scale;
    }

    private function developerOffset(float $offset): ?int
    {
        if (
            $offset !== floor($offset)
            || -128 > $offset
            || 127 < $offset
        ) {
            return null;
        }

        return (int) $offset;
    }

    private function addSmallUnsignedInteger(
        string $decimal,
        int $addition,
    ): string {
        $carry = $addition;
        $result = '';

        for (
            $index = strlen($decimal) - 1;
            0 <= $index;
            --$index
        ) {
            $value = (int) $decimal[$index] + $carry;
            $result = (string) ($value % 10).$result;
            $carry = intdiv($value, 10);
        }

        while (0 < $carry) {
            $result = (string) ($carry % 10).$result;
            $carry = intdiv($carry, 10);
        }

        return $result;
    }

    private function subtractSmallUnsignedInteger(
        string $decimal,
        int $subtraction,
    ): ?string {
        $right = (string) $subtraction;

        if (
            0 > $this->compareUnsignedDecimals(
                $decimal,
                $right,
            )
        ) {
            return null;
        }

        $right = str_pad(
            $right,
            strlen($decimal),
            '0',
            STR_PAD_LEFT,
        );
        $borrow = 0;
        $result = '';

        for (
            $index = strlen($decimal) - 1;
            0 <= $index;
            --$index
        ) {
            $value = (int) $decimal[$index]
                - (int) $right[$index]
                - $borrow;

            if (0 > $value) {
                $value += 10;
                $borrow = 1;
            } else {
                $borrow = 0;
            }

            $result = (string) $value.$result;
        }

        return ltrim($result, '0') ?: '0';
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function divideUnsignedDecimalByInteger(
        string $decimal,
        int $divisor,
    ): array {
        $quotient = '';
        $remainder = 0;

        foreach (str_split($decimal) as $digit) {
            $value = $remainder * 10 + (int) $digit;
            $quotient .= (string) intdiv(
                $value,
                $divisor,
            );
            $remainder = $value % $divisor;
        }

        return [
            ltrim($quotient, '0') ?: '0',
            $remainder,
        ];
    }

    private function greatestCommonDivisor(
        int $left,
        int $right,
    ): int {
        while (0 !== $right) {
            [$left, $right] = [
                $right,
                $left % $right,
            ];
        }

        return $left;
    }

    private function fitsPhpInteger(string $decimal): bool
    {
        return 0 >= $this->compareUnsignedDecimals(
            $decimal,
            (string) PHP_INT_MAX,
        );
    }

    private function compareUnsignedDecimals(
        string $left,
        string $right,
    ): int {
        $lengthComparison = strlen($left)
            <=> strlen($right);

        return 0 !== $lengthComparison
            ? $lengthComparison
            : $left <=> $right;
    }
}
