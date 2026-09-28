<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Decoder;

use Youmad\Endurance\Fit\Component\ComponentExtractedDataMessage;
use Youmad\Endurance\Fit\Component\ComponentResolvedDataMessage;
use Youmad\Endurance\Fit\Component\ExtractedComponentField;
use Youmad\Endurance\Fit\Component\ResolvedComponentField;
use Youmad\Endurance\Fit\Decoded\DecodedFieldElements;
use Youmad\Endurance\Fit\Decoded\ValidFieldElement;
use Youmad\Endurance\Fit\Exception\FitDecodeException;
use Youmad\Endurance\Fit\Exception\InvalidFitProfile;
use Youmad\Endurance\Fit\Profiled\ProfiledStandardField;

final class FitComponentValueResolver
{
    private const float INTEGER_TOLERANCE_MULTIPLIER = 8.0;

    /**
     * Latest physical values of full accumulated fields that have
     * not yet been applied to a packed component stream.
     *
     * @var array<string, int|float>
     */
    private array $pendingSeeds = [];

    public function __construct(
        private readonly FitComponentAccumulator $accumulator =
        new FitComponentAccumulator(),
        private readonly FitSubfieldSelector $subfields =
        new FitSubfieldSelector(),
    ) {
    }

    /**
     * Accumulator state belongs to one FIT stream. Starting a new
     * stream automatically clears state from a previous file.
     *
     * @param iterable<ComponentExtractedDataMessage> $messages
     *
     * @return \Generator<int, ComponentResolvedDataMessage>
     */
    public function resolveStream(
        iterable $messages,
    ): \Generator {
        $this->reset();

        foreach ($messages as $sequence => $message) {
            yield $sequence => $this->resolve(
                $message,
            );
        }
    }

    public function reset(): void
    {
        $this->accumulator->reset();
        $this->pendingSeeds = [];
    }

    public function resolve(
        ComponentExtractedDataMessage $message,
    ): ComponentResolvedDataMessage {
        $this->capturePhysicalSeeds($message);

        $extracted = $message->components();
        $resolved = [];

        for (
            $index = 0;
            $index < count($extracted);
            ++$index
        ) {
            $resolvedComponent = $this->resolveComponent(
                message: $message,
                component: $extracted[$index],
            );

            $resolved[] = $resolvedComponent;

            foreach (
                $this->extractNestedComponents(
                    message: $message,
                    parent: $resolvedComponent,
                ) as $nestedComponent
            ) {
                $extracted[] = $nestedComponent;
            }
        }

        $expandedSource = count($extracted)
            === count($message->components())
            ? $message
            : ComponentExtractedDataMessage::create(
                source: $message->source,
                components: $extracted,
            );

        return ComponentResolvedDataMessage::create(
            source: $expandedSource,
            components: $resolved,
        );
    }

    private function capturePhysicalSeeds(
        ComponentExtractedDataMessage $message,
    ): void {
        $globalMessageNumber = $message
            ->globalMessageNumber();

        foreach (
            $message->source->standardFields() as $field
        ) {
            if (
                null === $field->profile
                || !$field->profile->isAccumulated()
                || null === $field->source->rawField
            ) {
                continue;
            }

            $value = $this->lastNumericValue(
                $field,
            );

            if (null === $value) {
                continue;
            }

            $this->pendingSeeds[$this->key(
                globalMessageNumber: $globalMessageNumber,
                fieldNumber: $field->fieldNumber(),
            )] = $value;
        }
    }

    private function lastNumericValue(
        ProfiledStandardField $field,
    ): int|float|null {
        if (
            !$field->value
                instanceof DecodedFieldElements
        ) {
            return null;
        }

        $value = null;

        foreach ($field->value->elements() as $element) {
            if (
                !$element instanceof ValidFieldElement
                || (
                    !is_int($element->value)
                    && !is_float($element->value)
                )
            ) {
                continue;
            }

            $value = $element->value;
        }

        return $value;
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

    private function resolveComponent(
        ComponentExtractedDataMessage $message,
        ExtractedComponentField $component,
    ): ResolvedComponentField {
        $globalMessageNumber = $message
            ->globalMessageNumber();

        $fieldNumber = $component
            ->targetFieldNumber();

        $componentRawValue = $component->packedValue;
        $accumulationApplied = $component->isAccumulated();

        if ($accumulationApplied) {
            $key = $this->key(
                globalMessageNumber: $globalMessageNumber,
                fieldNumber: $fieldNumber,
            );

            if (
                array_key_exists(
                    $key,
                    $this->pendingSeeds,
                )
            ) {
                $seed = $component
                    ->component
                    ->transform
                    ->toRaw(
                        $this->pendingSeeds[$key],
                    );

                $this->accumulator->seed(
                    globalMessageNumber: $globalMessageNumber,
                    fieldNumber: $fieldNumber,
                    value: $this->truncateInteger(
                        value: $seed,
                        description: sprintf(
                            'Accumulated FIT component seed for field %d',
                            $fieldNumber,
                        ),
                        signed: $component->component->signed,
                    ),
                );

                unset($this->pendingSeeds[$key]);
            }

            $componentRawValue = $this
                ->accumulator
                ->accumulate(
                    globalMessageNumber: $globalMessageNumber,
                    fieldNumber: $fieldNumber,
                    packedValue: $component->packedValue,
                    bits: $component
                        ->component
                        ->bits,
                );
        }

        $physicalValue = $component
            ->component
            ->transform
            ->apply($componentRawValue);

        $targetRawValue = (
            $component->targetSubfield->transform
            ?? $component->targetProfile->transform
        )->toRaw($physicalValue);

        return ResolvedComponentField::create(
            source: $component,
            componentRawValue: $componentRawValue,
            physicalValue: $physicalValue,
            targetRawValue: $targetRawValue,
            accumulationApplied: $accumulationApplied,
        );
    }

    /**
     * @return list<ExtractedComponentField>
     */
    private function extractNestedComponents(
        ComponentExtractedDataMessage $message,
        ResolvedComponentField $parent,
    ): array {
        $components = null !== $parent->source->targetSubfield
            ? $parent->source->targetSubfield->components()
            : $parent->source->targetProfile->components();

        if ([] === $components) {
            return [];
        }

        $containerRawValue = $this->nestedContainerRawValue(
            parent: $parent,
            componentCount: count($components),
        );

        $totalBits = 0;

        foreach ($components as $component) {
            $totalBits += $component->bits;
        }

        $bits = FitBitReader::fromUnsignedInteger(
            value: $containerRawValue,
            minimumBits: $totalBits,
        );

        $nested = [];

        foreach ($components as $componentIndex => $component) {
            if ($component->bits > $bits->remaining()) {
                break;
            }

            $this->assertNoComponentCycle(
                parent: $parent,
                targetFieldNumber: $component->targetFieldNumber,
            );

            $targetProfile = $message
                ->source
                ->profile
                ?->field(
                    $component->targetFieldNumber,
                );

            if (null === $targetProfile) {
                throw new InvalidFitProfile(sprintf('FIT nested component of field %d references missing target field %d.', $parent->targetFieldNumber(), $component->targetFieldNumber));
            }

            $targetSubfield = $this->subfields->select(
                field: $targetProfile,
                message: $message->source->source,
            );

            $bitOffset = $bits->position();
            $packedValue = $component->signed
                ? $bits->readSigned($component->bits)
                : $bits->readUnsigned($component->bits);

            $nested[] = ExtractedComponentField::create(
                container: $parent->source->container,
                component: $component,
                targetProfile: $targetProfile,
                componentIndex: $componentIndex,
                bitOffset: $bitOffset,
                packedValue: $packedValue,
                targetSubfield: $targetSubfield,
                parent: $parent,
            );
        }

        return $nested;
    }

    private function nestedContainerRawValue(
        ResolvedComponentField $parent,
        int $componentCount,
    ): int {
        if (1 === $componentCount) {
            $component = null !== $parent->source->targetSubfield
                ? $parent->source->targetSubfield->components()[0]
                : $parent->source->targetProfile->components()[0];

            return $this->unsignedInteger(
                value: $component->transform->toRaw(
                    $parent->physicalValue,
                ),
                description: sprintf(
                    'Nested FIT component container field %d',
                    $parent->targetFieldNumber(),
                ),
            );
        }

        return $parent->componentRawValue;
    }

    private function assertNoComponentCycle(
        ResolvedComponentField $parent,
        int $targetFieldNumber,
    ): void {
        $current = $parent;

        while (true) {
            if (
                $current->targetFieldNumber()
                === $targetFieldNumber
            ) {
                throw new InvalidFitProfile(sprintf('FIT component expansion contains a cycle through field %d.', $targetFieldNumber));
            }

            $current = $current->source->parent;

            if (null === $current) {
                return;
            }
        }
    }

    private function unsignedInteger(
        int|float $value,
        string $description,
    ): int {
        return $this->integer(
            value: $value,
            description: $description,
            signed: false,
        );
    }

    private function truncateInteger(
        int|float $value,
        string $description,
        bool $signed,
    ): int {
        if (is_float($value)) {
            if (!is_finite($value)) {
                throw new FitDecodeException(sprintf('%s must be finite.', $description));
            }

            // Garmin SDK seeds accumulated component state by
            // truncating the transformed full-field value.
            $value = 0 > $value
                ? ceil($value)
                : floor($value);
        }

        return $this->integer(
            value: $value,
            description: $description,
            signed: $signed,
        );
    }

    private function integer(
        int|float $value,
        string $description,
        bool $signed,
    ): int {
        if (is_int($value)) {
            if (!$signed && 0 > $value) {
                throw new FitDecodeException(sprintf('%s cannot be negative.', $description));
            }

            return $value;
        }

        if (!is_finite($value)) {
            throw new FitDecodeException(sprintf('%s must be finite.', $description));
        }

        $rounded = round($value);

        $tolerance = PHP_FLOAT_EPSILON
            * max(
                1.0,
                abs($value),
            )
            * self::INTEGER_TOLERANCE_MULTIPLIER;

        if (
            abs($value - $rounded) > $tolerance
            || (!$signed && 0 > $rounded)
            || PHP_INT_MIN > $rounded
            || PHP_INT_MAX < $rounded
        ) {
            throw new FitDecodeException(sprintf('%s must resolve to %s; got %s.', $description, $signed ? 'an integer' : 'a non-negative integer', (string) $value));
        }

        return (int) $rounded;
    }
}
