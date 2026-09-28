<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Decoder;

use Youmad\Endurance\Fit\Component\ComponentExtractedDataMessage;
use Youmad\Endurance\Fit\Component\ExtractedComponentField;
use Youmad\Endurance\Fit\Decoded\DecodedFieldElements;
use Youmad\Endurance\Fit\Decoded\InvalidFieldElement;
use Youmad\Endurance\Fit\Exception\FitDecodeException;
use Youmad\Endurance\Fit\Exception\InvalidFitProfile;
use Youmad\Endurance\Fit\Profile\ComponentProfile;
use Youmad\Endurance\Fit\Profiled\ProfiledDataMessage;
use Youmad\Endurance\Fit\Profiled\ProfiledStandardField;

final readonly class FitComponentExtractor
{
    public function __construct(
        private FitSubfieldSelector $subfields = new FitSubfieldSelector(),
    ) {
    }

    /**
     * @param iterable<ProfiledDataMessage> $messages
     *
     * @return \Generator<int, ComponentExtractedDataMessage>
     */
    public function extractStream(
        iterable $messages,
    ): \Generator {
        foreach ($messages as $sequence => $message) {
            yield $sequence => $this->extract(
                $message,
            );
        }
    }

    public function extract(
        ProfiledDataMessage $message,
    ): ComponentExtractedDataMessage {
        return $this->extractContainers(
            message: $message,
            containers: $message->standardFields(),
        );
    }

    /**
     * @param list<ProfiledStandardField> $containers
     *
     * @internal optimized decoder pipeline entry point
     */
    public function extractForPipeline(
        ProfiledDataMessage $message,
        array $containers,
    ): ComponentExtractedDataMessage {
        return $this->extractContainers(
            message: $message,
            containers: $containers,
        );
    }

    /**
     * @param list<ProfiledStandardField> $containers
     */
    private function extractContainers(
        ProfiledDataMessage $message,
        array $containers,
    ): ComponentExtractedDataMessage {
        $extracted = [];

        foreach ($containers as $container) {
            $components = $this->effectiveComponents(
                $container,
            );

            if ([] === $components) {
                continue;
            }

            if ($this->containsOnlyInvalidValues($container)) {
                continue;
            }

            $rawField = $container
                ->source
                ->rawField;

            if (null === $rawField) {
                throw new FitDecodeException(sprintf('FIT component container field %d has no physical raw payload.', $container->fieldNumber()));
            }

            $architecture = $message
                ->source
                ->source
                ->definition()
                ->architecture;

            $bits = FitBitReader::fromRawField(
                field: $rawField,
                architecture: $architecture,
            );

            foreach (
                $components as $componentIndex => $component
            ) {
                if ($component->bits > $bits->remaining()) {
                    break;
                }

                $targetProfile = $message
                    ->profile
                    ?->field(
                        $component->targetFieldNumber,
                    );

                if (null === $targetProfile) {
                    throw new InvalidFitProfile(sprintf('FIT component of field %d references missing target field %d.', $container->fieldNumber(), $component->targetFieldNumber));
                }

                $targetSubfield = $this->subfields->select(
                    field: $targetProfile,
                    message: $message->source,
                );

                $bitOffset = $bits->position();
                $packedValue = $component->signed
                    ? $bits->readSigned($component->bits)
                    : $bits->readUnsigned($component->bits);

                $extracted[] = ExtractedComponentField::create(
                    container: $container,
                    component: $component,
                    targetProfile: $targetProfile,
                    componentIndex: $componentIndex,
                    bitOffset: $bitOffset,
                    packedValue: $packedValue,
                    targetSubfield: $targetSubfield,
                );
            }
        }

        return ComponentExtractedDataMessage::create(
            source: $message,
            components: $extracted,
        );
    }

    /**
     * @return list<ComponentProfile>
     */
    private function effectiveComponents(
        ProfiledStandardField $field,
    ): array {
        if (null !== $field->subfield) {
            return $field->subfield
                ->components();
        }

        return $field->profile
            ?->components()
            ?? [];
    }

    private function containsOnlyInvalidValues(
        ProfiledStandardField $field,
    ): bool {
        $value = $field->source->value;

        if (!$value instanceof DecodedFieldElements) {
            return false;
        }

        foreach ($value->elements() as $element) {
            if (!$element instanceof InvalidFieldElement) {
                return false;
            }
        }

        return true;
    }
}
