<?php

declare(strict_types=1);

namespace Endurance\Fit\Decoder;

use Endurance\Fit\Decoded\DecodedDataMessage;
use Endurance\Fit\Decoded\DecodedFieldElements;
use Endurance\Fit\Decoded\DecodedStandardField;
use Endurance\Fit\Decoded\ValidFieldElement;
use Endurance\Fit\Profile\FieldProfile;
use Endurance\Fit\Profile\SubfieldProfile;

final readonly class FitSubfieldSelector
{
    public function select(
        FieldProfile $field,
        DecodedDataMessage $message,
    ): ?SubfieldProfile {
        foreach ($field->subfields() as $subfield) {
            if (
                $this->matches(
                    subfield: $subfield,
                    message: $message,
                )
            ) {
                return $subfield;
            }
        }

        return null;
    }

    private function matches(
        SubfieldProfile $subfield,
        DecodedDataMessage $message,
    ): bool {
        foreach ($subfield->conditions() as $condition) {
            $rawValue = $this->rawReferenceValue(
                $message->standardField(
                    $condition->referenceFieldNumber,
                ),
            );

            if (
                null !== $rawValue
                && $condition->accepts($rawValue)
            ) {
                return true;
            }
        }

        return false;
    }

    private function rawReferenceValue(
        ?DecodedStandardField $field,
    ): ?int {
        if (
            null === $field
            || !$field->value
                instanceof DecodedFieldElements
        ) {
            return null;
        }

        $elements = $field->value->elements();

        if (1 !== count($elements)) {
            return null;
        }

        $element = $elements[0];

        if (
            !$element instanceof ValidFieldElement
            || !is_int($element->value)
        ) {
            return null;
        }

        return $element->value;
    }
}
