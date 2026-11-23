<?php

declare(strict_types=1);

namespace Endurance\Fit\Decoder;

use Endurance\Fit\Component\ComponentResolvedDataMessage;
use Endurance\Fit\Component\ResolvedComponentField;
use Endurance\Fit\Profile\FitTypeRegistry;
use Endurance\Fit\Typed\TypedComponentDataMessage;
use Endurance\Fit\Typed\TypedComponentField;
use Endurance\Fit\Typed\TypeResolutionState;

final readonly class FitComponentTypeValueResolver
{
    public function __construct(
        private FitTypeRegistry $types,
    ) {
    }

    /**
     * @param iterable<ComponentResolvedDataMessage> $messages
     *
     * @return \Generator<int, TypedComponentDataMessage>
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
        ComponentResolvedDataMessage $message,
    ): TypedComponentDataMessage {
        $components = [];

        foreach ($message->components() as $component) {
            $components[] = $this->resolveComponent(
                $component,
            );
        }

        return TypedComponentDataMessage::create(
            source: $message,
            components: $components,
        );
    }

    private function resolveComponent(
        ResolvedComponentField $component,
    ): TypedComponentField {
        $typeProfile = $this->types->type(
            $component->typeName(),
        );

        if (null === $typeProfile) {
            return TypedComponentField::create(
                source: $component,
                typeProfile: null,
                valueProfile: null,
                resolutionState: TypeResolutionState::NotApplicable,
            );
        }

        if (!is_int($component->physicalValue)) {
            return TypedComponentField::create(
                source: $component,
                typeProfile: $typeProfile,
                valueProfile: null,
                resolutionState: TypeResolutionState::Unavailable,
            );
        }

        return TypedComponentField::create(
            source: $component,
            typeProfile: $typeProfile,
            valueProfile: $typeProfile->value(
                $component->physicalValue,
            ),
            resolutionState: TypeResolutionState::Applied,
        );
    }
}
