<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Decoder;

use Youmad\Endurance\Fit\Decoded\DecodedDataMessage;
use Youmad\Endurance\Fit\Raw\RawDataRecord;
use Youmad\Endurance\Fit\Raw\RawFitMessage;
use Youmad\Endurance\Fit\Typed\TypedDataMessage;
use Youmad\Endurance\Fit\Unified\UnifiedDataMessage;
use Youmad\Endurance\Fit\Unified\UnifiedDeveloperField;

final class FitDataMessageProcessor
{
    public function __construct(
        private readonly FitDataMessageDecoder $decoder,
        private readonly FitProfileNormalizer $profiles,
        private readonly FitTypeValueResolver $physicalTypes,
        private readonly FitComponentExtractor $components,
        private readonly FitComponentValueResolver $componentValues,
        private readonly FitComponentTypeValueResolver $componentTypes,
        private readonly FitDataMessageAssembler $assembler,
        private readonly ?FitDeveloperFieldResolver $developerFields = null,
    ) {
    }

    public function process(
        RawDataRecord|DecodedDataMessage $message,
    ): UnifiedDataMessage {
        $decoded = $message instanceof DecodedDataMessage
            ? $message
            : $this->decoder->decode($message);

        [
            $profiled,
            $physical,
            $componentContainers,
        ] = $this->profiles->normalizeWithTypes(
            message: $decoded,
            types: $this->physicalTypes,
        );

        $typedComponents = $this->componentTypes->resolve(
            $this->componentValues->resolve(
                $this->components->extractForPipeline(
                    message: $profiled,
                    containers: $componentContainers,
                ),
            ),
        );

        $developerFields = null === $this->developerFields
            ? $this->unresolvedDeveloperFields($physical)
            : $this->developerFields->resolve($physical);

        return $this->assembler->assembleForPipeline(
            physical: $physical,
            components: $typedComponents,
            developerFields: $developerFields,
        );
    }

    /**
     * Definition messages are ignored. Stateful component and
     * developer profile data is reset before every stream.
     *
     * @param iterable<mixed> $messages
     *
     * @return \Generator<int, UnifiedDataMessage>
     */
    public function processStream(
        iterable $messages,
    ): \Generator {
        $this->reset();

        foreach ($messages as $sequence => $message) {
            if (
                $message instanceof RawFitMessage
                && !$message instanceof RawDataRecord
            ) {
                continue;
            }

            if (
                !$message instanceof RawDataRecord
                && !$message instanceof DecodedDataMessage
            ) {
                throw new \InvalidArgumentException(sprintf('FIT message stream contains unsupported value of type %s.', get_debug_type($message)));
            }

            yield $sequence => $this->process(
                $message,
            );
        }
    }

    public function reset(): void
    {
        $this->componentValues->reset();
        $this->developerFields?->reset();
    }

    /**
     * @return list<UnifiedDeveloperField>
     */
    private function unresolvedDeveloperFields(
        TypedDataMessage $message,
    ): array {
        $fields = [];

        foreach ($message->developerFields() as $source) {
            $fields[] = UnifiedDeveloperField::unresolved(
                source: $source,
                developerData: null,
                reason: 'FIT developer field resolution is not configured for this processor.',
            );
        }

        return $fields;
    }
}
