<?php

declare(strict_types=1);

namespace Endurance\Fit\Decoder;

use Endurance\Fit\Decoded\DecodedFieldElements;
use Endurance\Fit\Decoded\DecodedFieldValue;
use Endurance\Fit\Decoded\InvalidFieldElement;
use Endurance\Fit\Decoded\ValidFieldElement;
use Endurance\Fit\Profile\FitTypeProfile;
use Endurance\Fit\Typed\ResolvedTypedFieldValue;
use Endurance\Fit\Typed\TypedEnumFieldElement;
use Endurance\Fit\Typed\TypedFieldElements;
use Endurance\Fit\Typed\TypedInvalidFieldElement;
use Endurance\Fit\Typed\TypedScalarFieldElement;
use Endurance\Fit\Typed\TypeResolutionState;
use Endurance\Fit\Typed\UnavailableTypedFieldValue;

final readonly class FitTypedFieldValueResolver
{
    public function resolve(
        DecodedFieldValue $value,
        ?FitTypeProfile $typeProfile,
    ): ResolvedTypedFieldValue {
        if (null === $typeProfile) {
            return $this->preserveScalarValue($value);
        }

        return $this->resolveSymbolicValue(
            value: $value,
            typeProfile: $typeProfile,
        );
    }

    private function preserveScalarValue(
        DecodedFieldValue $value,
    ): ResolvedTypedFieldValue {
        if (!$value instanceof DecodedFieldElements) {
            return new ResolvedTypedFieldValue(
                typeProfile: null,
                value: new UnavailableTypedFieldValue(
                    source: $value,
                    reason: 'Decoded FIT field does not expose individual elements.',
                ),
                resolutionState: TypeResolutionState::Unavailable,
            );
        }

        $elements = [];

        foreach ($value->elements() as $element) {
            if ($element instanceof InvalidFieldElement) {
                $elements[] = new TypedInvalidFieldElement(
                    $element,
                );

                continue;
            }

            if ($element instanceof ValidFieldElement) {
                $elements[] = new TypedScalarFieldElement(
                    $element,
                );

                continue;
            }

            return new ResolvedTypedFieldValue(
                typeProfile: null,
                value: new UnavailableTypedFieldValue(
                    source: $value,
                    reason: sprintf(
                        'Unsupported decoded FIT element: %s.',
                        $element::class,
                    ),
                ),
                resolutionState: TypeResolutionState::Unavailable,
            );
        }

        return new ResolvedTypedFieldValue(
            typeProfile: null,
            value: TypedFieldElements::create(
                ...$elements,
            ),
            resolutionState: TypeResolutionState::NotApplicable,
        );
    }

    private function resolveSymbolicValue(
        DecodedFieldValue $value,
        FitTypeProfile $typeProfile,
    ): ResolvedTypedFieldValue {
        if (!$value instanceof DecodedFieldElements) {
            return $this->unavailableSymbolicValue(
                value: $value,
                typeProfile: $typeProfile,
                reason: 'Decoded FIT field does not expose individual elements.',
            );
        }

        foreach ($value->elements() as $element) {
            if (
                $element instanceof ValidFieldElement
                && !is_int($element->value)
            ) {
                return $this->unavailableSymbolicValue(
                    value: $value,
                    typeProfile: $typeProfile,
                    reason: sprintf(
                        'FIT type %s requires integer values.',
                        $typeProfile->name,
                    ),
                );
            }

            if (
                !$element instanceof ValidFieldElement
                && !$element instanceof InvalidFieldElement
            ) {
                return $this->unavailableSymbolicValue(
                    value: $value,
                    typeProfile: $typeProfile,
                    reason: sprintf(
                        'Unsupported decoded FIT element: %s.',
                        $element::class,
                    ),
                );
            }
        }

        $elements = [];

        foreach ($value->elements() as $element) {
            if ($element instanceof InvalidFieldElement) {
                $elements[] = new TypedInvalidFieldElement(
                    $element,
                );

                continue;
            }

            /** @var ValidFieldElement $element */
            /** @var int $numericValue */
            $numericValue = $element->value;

            $elements[] = TypedEnumFieldElement::create(
                source: $element,
                profile: $typeProfile->value(
                    $numericValue,
                ),
            );
        }

        return new ResolvedTypedFieldValue(
            typeProfile: $typeProfile,
            value: TypedFieldElements::create(
                ...$elements,
            ),
            resolutionState: TypeResolutionState::Applied,
        );
    }

    private function unavailableSymbolicValue(
        DecodedFieldValue $value,
        FitTypeProfile $typeProfile,
        string $reason,
    ): ResolvedTypedFieldValue {
        return new ResolvedTypedFieldValue(
            typeProfile: $typeProfile,
            value: new UnavailableTypedFieldValue(
                source: $value,
                reason: $reason,
            ),
            resolutionState: TypeResolutionState::Unavailable,
        );
    }
}
