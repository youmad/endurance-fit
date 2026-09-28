<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Decoder;

use Youmad\Endurance\Fit\Decoded\DecodedFieldElements;
use Youmad\Endurance\Fit\Decoded\DecodedFieldValue;
use Youmad\Endurance\Fit\Decoded\InvalidFieldElement;
use Youmad\Endurance\Fit\Decoded\UndecodableFieldValue;
use Youmad\Endurance\Fit\Decoded\ValidFieldElement;
use Youmad\Endurance\Fit\Exception\FitDecodeException;
use Youmad\Endurance\Fit\Raw\FitArchitecture;
use Youmad\Endurance\Fit\Raw\FitBaseType;
use Youmad\Endurance\Fit\Raw\FitBaseTypeKind;
use Youmad\Endurance\Fit\Raw\RawFieldValue;

final class FitBaseTypeDecoder
{
    private const string UINT64_SIGNED_MINIMUM =
        '9223372036854775808';

    /**
     * @internal compile once per standard field definition and reuse for
     * repeated data messages with that definition
     */
    public function compile(
        FitBaseType $baseType,
        FitArchitecture $architecture,
        int $fieldSize,
    ): FitBaseTypeDecodingPlan {
        $kind = $baseType->kind();

        return new FitBaseTypeDecodingPlan(
            baseType: $baseType,
            architecture: $architecture,
            kind: $kind,
            fieldSize: $fieldSize,
            elementSize: $kind?->elementSize() ?? 0,
            invalidBytes: null === $kind
                ? ''
                : $this->invalidBytes(
                    kind: $kind,
                    architecture: $architecture,
                ),
        );
    }

    public function decode(
        FitBaseType $baseType,
        FitArchitecture $architecture,
        RawFieldValue $rawValue,
    ): DecodedFieldValue {
        $kind = $baseType->kind();

        if (null === $kind) {
            return new UndecodableFieldValue(
                type: $baseType,
                rawValue: $rawValue,
                reason: sprintf(
                    'Unknown FIT base type number %d.',
                    $baseType->number(),
                ),
            );
        }

        if (FitBaseTypeKind::StringValue === $kind) {
            return $this->decodeString(
                baseType: $baseType,
                rawValue: $rawValue,
            );
        }

        $elementSize = $kind->elementSize();

        if (
            0 !== $rawValue->size() % $elementSize
        ) {
            return new UndecodableFieldValue(
                type: $baseType,
                rawValue: $rawValue,
                reason: sprintf(
                    'FIT field size %d is not divisible by base type element size %d.',
                    $rawValue->size(),
                    $elementSize,
                ),
            );
        }

        $chunks = str_split(
            $rawValue->bytes(),
            $elementSize,
        );

        if (
            FitBaseTypeKind::Byte === $kind
            && 1 < count($chunks)
        ) {
            return $this->decodeByteArray(
                baseType: $baseType,
                chunks: $chunks,
            );
        }

        $invalidBytes = $this->invalidBytes(
            kind: $kind,
            architecture: $architecture,
        );

        $elements = [];

        foreach ($chunks as $chunk) {
            if ($invalidBytes === $chunk) {
                $elements[] = new InvalidFieldElement();

                continue;
            }

            $elements[] = new ValidFieldElement(
                $this->decodeElement(
                    kind: $kind,
                    architecture: $architecture,
                    bytes: $chunk,
                ),
            );
        }

        return DecodedFieldElements::create(
            $baseType,
            ...$elements,
        );
    }

    /**
     * @internal the raw value must belong to the field definition whose
     * size and base type were used to compile the plan
     */
    public function decodeCompiled(
        FitBaseTypeDecodingPlan $plan,
        RawFieldValue $rawValue,
    ): DecodedFieldValue {
        $kind = $plan->kind;

        if (null === $kind) {
            return new UndecodableFieldValue(
                type: $plan->baseType,
                rawValue: $rawValue,
                reason: sprintf(
                    'Unknown FIT base type number %d.',
                    $plan->baseType->number(),
                ),
            );
        }

        if (FitBaseTypeKind::StringValue === $kind) {
            return $this->decodeString(
                baseType: $plan->baseType,
                rawValue: $rawValue,
            );
        }

        if (0 !== $plan->fieldSize % $plan->elementSize) {
            return new UndecodableFieldValue(
                type: $plan->baseType,
                rawValue: $rawValue,
                reason: sprintf(
                    'FIT field size %d is not divisible by base type element size %d.',
                    $plan->fieldSize,
                    $plan->elementSize,
                ),
            );
        }

        if ($plan->fieldSize === $plan->elementSize) {
            $bytes = $rawValue->bytes();
            $element = $plan->invalidBytes === $bytes
                ? new InvalidFieldElement()
                : new ValidFieldElement(
                    $this->decodeElement(
                        kind: $kind,
                        architecture: $plan->architecture,
                        bytes: $bytes,
                    ),
                );

            return DecodedFieldElements::create(
                $plan->baseType,
                $element,
            );
        }

        $chunks = str_split(
            $rawValue->bytes(),
            $plan->elementSize,
        );

        if (
            FitBaseTypeKind::Byte === $kind
            && 1 < count($chunks)
        ) {
            return $this->decodeByteArray(
                baseType: $plan->baseType,
                chunks: $chunks,
            );
        }

        $elements = [];

        foreach ($chunks as $chunk) {
            if ($plan->invalidBytes === $chunk) {
                $elements[] = new InvalidFieldElement();

                continue;
            }

            $elements[] = new ValidFieldElement(
                $this->decodeElement(
                    kind: $kind,
                    architecture: $plan->architecture,
                    bytes: $chunk,
                ),
            );
        }

        return DecodedFieldElements::create(
            $plan->baseType,
            ...$elements,
        );
    }

    private function decodeString(
        FitBaseType $baseType,
        RawFieldValue $rawValue,
    ): DecodedFieldElements {
        $value = rtrim(
            $rawValue->bytes(),
            "\0",
        );

        if ('' === $value) {
            return DecodedFieldElements::create(
                $baseType,
                new InvalidFieldElement(),
            );
        }

        $elements = [];

        foreach (explode("\0", $value) as $part) {
            $elements[] = new ValidFieldElement(
                $part,
            );
        }

        return DecodedFieldElements::create(
            $baseType,
            ...$elements,
        );
    }

    /**
     * FIT byte arrays are special: an individual 0xFF byte may be
     * legitimate binary data. The array is invalid only when every
     * element contains the invalid sentinel.
     *
     * @param non-empty-list<string> $chunks
     */
    private function decodeByteArray(
        FitBaseType $baseType,
        array $chunks,
    ): DecodedFieldElements {
        $onlyInvalidValues = true;

        foreach ($chunks as $chunk) {
            if ("\xFF" !== $chunk) {
                $onlyInvalidValues = false;

                break;
            }
        }

        $elements = [];

        foreach ($chunks as $chunk) {
            $elements[] = $onlyInvalidValues
                ? new InvalidFieldElement()
                : new ValidFieldElement(ord($chunk));
        }

        return DecodedFieldElements::create(
            $baseType,
            ...$elements,
        );
    }

    private function invalidBytes(
        FitBaseTypeKind $kind,
        FitArchitecture $architecture,
    ): string {
        return match ($kind) {
            FitBaseTypeKind::Enumeration,
            FitBaseTypeKind::UnsignedInt8,
            FitBaseTypeKind::Byte => "\xFF",

            FitBaseTypeKind::SignedInt8 => "\x7F",

            FitBaseTypeKind::SignedInt16 => $this->encodeUInt16(
                value: 0x7FFF,
                architecture: $architecture,
            ),

            FitBaseTypeKind::UnsignedInt16 => $this->encodeUInt16(
                value: 0xFFFF,
                architecture: $architecture,
            ),

            FitBaseTypeKind::SignedInt32 => $this->encodeUInt32(
                value: 0x7FFFFFFF,
                architecture: $architecture,
            ),

            FitBaseTypeKind::UnsignedInt32 => $this->encodeUInt32(
                value: 0xFFFFFFFF,
                architecture: $architecture,
            ),

            FitBaseTypeKind::StringValue,
            FitBaseTypeKind::UnsignedInt8Zero => "\x00",

            FitBaseTypeKind::Float32 => str_repeat(
                "\xFF",
                4,
            ),

            FitBaseTypeKind::Float64,
            FitBaseTypeKind::UnsignedInt64 => str_repeat(
                "\xFF",
                8,
            ),

            FitBaseTypeKind::UnsignedInt16Zero => str_repeat(
                "\x00",
                2,
            ),

            FitBaseTypeKind::UnsignedInt32Zero => str_repeat(
                "\x00",
                4,
            ),

            FitBaseTypeKind::SignedInt64 => match ($architecture) {
                FitArchitecture::LittleEndian => str_repeat("\xFF", 7)."\x7F",

                FitArchitecture::BigEndian => "\x7F".str_repeat("\xFF", 7),
            },

            FitBaseTypeKind::UnsignedInt64Zero => str_repeat(
                "\x00",
                8,
            ),
        };
    }

    private function encodeUInt16(
        int $value,
        FitArchitecture $architecture,
    ): string {
        return pack(
            match ($architecture) {
                FitArchitecture::LittleEndian => 'v',
                FitArchitecture::BigEndian => 'n',
            },
            $value,
        );
    }

    private function encodeUInt32(
        int $value,
        FitArchitecture $architecture,
    ): string {
        return pack(
            match ($architecture) {
                FitArchitecture::LittleEndian => 'V',
                FitArchitecture::BigEndian => 'N',
            },
            $value,
        );
    }

    private function decodeElement(
        FitBaseTypeKind $kind,
        FitArchitecture $architecture,
        string $bytes,
    ): int|float|string {
        return match ($kind) {
            FitBaseTypeKind::Enumeration,
            FitBaseTypeKind::UnsignedInt8,
            FitBaseTypeKind::UnsignedInt8Zero,
            FitBaseTypeKind::Byte => ord($bytes),

            FitBaseTypeKind::SignedInt8 => $this->decodeSignedInt8(
                $bytes,
            ),

            FitBaseTypeKind::SignedInt16 => $this->decodeSignedInt16(
                architecture: $architecture,
                bytes: $bytes,
            ),

            FitBaseTypeKind::UnsignedInt16,
            FitBaseTypeKind::UnsignedInt16Zero => $this->decodeUnsignedInt16(
                architecture: $architecture,
                bytes: $bytes,
            ),

            FitBaseTypeKind::SignedInt32 => $this->decodeSignedInt32(
                architecture: $architecture,
                bytes: $bytes,
            ),

            FitBaseTypeKind::UnsignedInt32,
            FitBaseTypeKind::UnsignedInt32Zero => $this->decodeUnsignedInt32(
                architecture: $architecture,
                bytes: $bytes,
            ),

            FitBaseTypeKind::Float32 => $this->decodeFloat32(
                architecture: $architecture,
                bytes: $bytes,
            ),

            FitBaseTypeKind::Float64 => $this->decodeFloat64(
                architecture: $architecture,
                bytes: $bytes,
            ),

            FitBaseTypeKind::SignedInt64 => $this->decodeSignedInt64(
                architecture: $architecture,
                bytes: $bytes,
            ),

            FitBaseTypeKind::UnsignedInt64,
            FitBaseTypeKind::UnsignedInt64Zero => $this->decodeUnsignedInt64(
                architecture: $architecture,
                bytes: $bytes,
            ),

            FitBaseTypeKind::StringValue => throw new FitDecodeException('FIT strings must be decoded as complete fields.'),
        };
    }

    private function decodeSignedInt8(string $bytes): int
    {
        $value = unpack(
            'cvalue',
            $bytes,
        );

        return $this->integerFromUnpack(
            $value,
            'signed 8-bit integer',
        );
    }

    /**
     * @param array<string, mixed>|false $value
     */
    private function integerFromUnpack(
        array|false $value,
        string $description,
    ): int {
        if (
            false === $value
            || !isset($value['value'])
            || !is_int($value['value'])
        ) {
            throw new FitDecodeException(sprintf('Failed to decode FIT %s.', $description));
        }

        return $value['value'];
    }

    private function decodeSignedInt16(
        FitArchitecture $architecture,
        string $bytes,
    ): int {
        $value = $this->decodeUnsignedInt16(
            architecture: $architecture,
            bytes: $bytes,
        );

        return 0x8000 <= $value
            ? $value - 0x10000
            : $value;
    }

    private function decodeUnsignedInt16(
        FitArchitecture $architecture,
        string $bytes,
    ): int {
        $format = match ($architecture) {
            FitArchitecture::LittleEndian => 'vvalue',
            FitArchitecture::BigEndian => 'nvalue',
        };

        return $this->integerFromUnpack(
            unpack($format, $bytes),
            'unsigned 16-bit integer',
        );
    }

    private function decodeSignedInt32(
        FitArchitecture $architecture,
        string $bytes,
    ): int {
        $value = $this->decodeUnsignedInt32(
            architecture: $architecture,
            bytes: $bytes,
        );

        return 0x80000000 <= $value
            ? $value - 0x100000000
            : $value;
    }

    private function decodeUnsignedInt32(
        FitArchitecture $architecture,
        string $bytes,
    ): int {
        $format = match ($architecture) {
            FitArchitecture::LittleEndian => 'Vvalue',
            FitArchitecture::BigEndian => 'Nvalue',
        };

        return $this->integerFromUnpack(
            unpack($format, $bytes),
            'unsigned 32-bit integer',
        );
    }

    private function decodeFloat32(
        FitArchitecture $architecture,
        string $bytes,
    ): float {
        $format = match ($architecture) {
            FitArchitecture::LittleEndian => 'gvalue',
            FitArchitecture::BigEndian => 'Gvalue',
        };

        return $this->floatFromUnpack(
            unpack($format, $bytes),
            '32-bit float',
        );
    }

    /**
     * @param array<string, mixed>|false $value
     */
    private function floatFromUnpack(
        array|false $value,
        string $description,
    ): float {
        if (
            false === $value
            || !isset($value['value'])
            || !is_float($value['value'])
        ) {
            throw new FitDecodeException(sprintf('Failed to decode FIT %s.', $description));
        }

        return $value['value'];
    }

    private function decodeFloat64(
        FitArchitecture $architecture,
        string $bytes,
    ): float {
        $format = match ($architecture) {
            FitArchitecture::LittleEndian => 'evalue',
            FitArchitecture::BigEndian => 'Evalue',
        };

        return $this->floatFromUnpack(
            unpack($format, $bytes),
            '64-bit float',
        );
    }

    private function decodeSignedInt64(
        FitArchitecture $architecture,
        string $bytes,
    ): int|string {
        $bytes = FitArchitecture::LittleEndian === $architecture
            ? strrev($bytes)
            : $bytes;

        $negative = 0 !== (
            ord($bytes[0]) & 0x80
        );

        if (!$negative) {
            return $this->decodeUnsignedBigEndian(
                $bytes,
            );
        }

        $magnitude = $this->decodeUnsignedBigEndian(
            $this->twosComplementMagnitude($bytes),
        );

        if (
            is_string($magnitude)
            && self::UINT64_SIGNED_MINIMUM === $magnitude
            && 8 <= PHP_INT_SIZE
        ) {
            return PHP_INT_MIN;
        }

        return is_int($magnitude)
            ? -$magnitude
            : '-'.$magnitude;
    }

    private function decodeUnsignedBigEndian(
        string $bytes,
    ): int|string {
        $decimal = '0';

        $length = strlen($bytes);

        for ($index = 0; $index < $length; ++$index) {
            $decimal = $this->decimalMultiplyAndAdd(
                decimal: $decimal,
                multiplier: 256,
                addition: ord($bytes[$index]),
            );
        }

        if ($this->decimalFitsPhpInteger($decimal)) {
            return (int) $decimal;
        }

        return $decimal;
    }

    private function decimalMultiplyAndAdd(
        string $decimal,
        int $multiplier,
        int $addition,
    ): string {
        $carry = $addition;
        $result = '';

        for (
            $index = strlen($decimal) - 1;
            0 <= $index;
            --$index
        ) {
            $value = ((int) $decimal[$index])
                * $multiplier
                + $carry;

            $result = (string) ($value % 10)
                .$result;

            $carry = intdiv(
                $value,
                10,
            );
        }

        while (0 < $carry) {
            $result = (string) ($carry % 10)
                .$result;

            $carry = intdiv(
                $carry,
                10,
            );
        }

        $result = ltrim(
            $result,
            '0',
        );

        return '' === $result
            ? '0'
            : $result;
    }

    private function decimalFitsPhpInteger(
        string $decimal,
    ): bool {
        $maximum = (string) PHP_INT_MAX;

        $decimalLength = strlen($decimal);
        $maximumLength = strlen($maximum);

        if ($decimalLength !== $maximumLength) {
            return $decimalLength < $maximumLength;
        }

        return $decimal <= $maximum;
    }

    private function twosComplementMagnitude(
        string $bytes,
    ): string {
        $length = strlen($bytes);
        $result = '';

        for ($index = 0; $index < $length; ++$index) {
            $result .= chr(
                0xFF - ord($bytes[$index]),
            );
        }

        $carry = 1;

        for (
            $index = $length - 1;
            0 <= $index && 0 !== $carry;
            --$index
        ) {
            $value = ord($result[$index]) + $carry;

            $result[$index] = chr(
                $value & 0xFF,
            );

            $carry = 0xFF < $value
                ? 1
                : 0;
        }

        return $result;
    }

    private function decodeUnsignedInt64(
        FitArchitecture $architecture,
        string $bytes,
    ): int|string {
        $bytes = FitArchitecture::LittleEndian === $architecture
            ? strrev($bytes)
            : $bytes;

        return $this->decodeUnsignedBigEndian(
            $bytes,
        );
    }
}
