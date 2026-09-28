<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Decoder;

use Youmad\Endurance\Fit\Profile\FitTypeRegistry;
use Youmad\Endurance\Fit\Profiled\ProfiledDataMessage;
use Youmad\Endurance\Fit\Profiled\ProfiledStandardField;
use Youmad\Endurance\Fit\Typed\TypedDataMessage;
use Youmad\Endurance\Fit\Typed\TypedStandardField;

final readonly class FitTypeValueResolver
{
    public function __construct(
        private FitTypeRegistry $types,
        private FitTypedFieldValueResolver $values = new FitTypedFieldValueResolver(),
    ) {
    }

    /**
     * @param iterable<ProfiledDataMessage> $messages
     *
     * @return \Generator<int, TypedDataMessage>
     */
    public function resolveStream(
        iterable $messages,
    ): \Generator {
        foreach ($messages as $sequence => $message) {
            yield $sequence => $this->resolve(
                $message,
            );
        }
    }

    public function resolve(
        ProfiledDataMessage $message,
    ): TypedDataMessage {
        $fields = [];

        foreach ($message->standardFields() as $field) {
            $fields[] = $this->resolveField($field);
        }

        return TypedDataMessage::create(
            source: $message,
            standardFields: $fields,
        );
    }

    /**
     * @internal optimized decoder pipeline entry point
     */
    public function resolveFieldForPipeline(
        ProfiledStandardField $field,
    ): TypedStandardField {
        $typeName = $field->typeName();

        $resolved = $this->values->resolve(
            value: $field->value,
            typeProfile: null === $typeName
                ? null
                : $this->types->type($typeName),
        );

        return TypedStandardField::fromPipeline(
            source: $field,
            typeProfile: $resolved->typeProfile,
            value: $resolved->value,
            resolutionState: $resolved->resolutionState,
        );
    }

    private function resolveField(
        ProfiledStandardField $field,
    ): TypedStandardField {
        $typeName = $field->typeName();

        $resolved = $this->values->resolve(
            value: $field->value,
            typeProfile: null === $typeName
                ? null
                : $this->types->type($typeName),
        );

        return TypedStandardField::create(
            source: $field,
            typeProfile: $resolved->typeProfile,
            value: $resolved->value,
            resolutionState: $resolved->resolutionState,
        );
    }
}
