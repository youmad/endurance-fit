<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Decoder;

use Youmad\Endurance\Fit\Decoded\DecodedDataMessage;
use Youmad\Endurance\Fit\Decoded\DecodedStandardField;
use Youmad\Endurance\Fit\Raw\MessageDefinition;
use Youmad\Endurance\Fit\Raw\RawCompressedTimestampDataMessage;
use Youmad\Endurance\Fit\Raw\RawDataRecord;
use Youmad\Endurance\Fit\Raw\RawFitMessage;

final readonly class FitDataMessageDecoder
{
    /**
     * @var \WeakMap<MessageDefinition, array<int, FitBaseTypeDecodingPlan>>
     */
    private \WeakMap $compiledStandardFieldPlans;

    public function __construct(
        private FitBaseTypeDecoder $baseTypes = new FitBaseTypeDecoder(),
    ) {
        $this->compiledStandardFieldPlans = new \WeakMap();
    }

    /**
     * Definition messages are consumed by the raw reader but do not
     * become decoded data messages.
     *
     * @param iterable<RawFitMessage> $messages
     *
     * @return \Generator<int, DecodedDataMessage>
     */
    public function decodeStream(
        iterable $messages,
    ): \Generator {
        foreach ($messages as $sequence => $message) {
            if (!$message instanceof RawDataRecord) {
                continue;
            }

            yield $sequence => $this->decode(
                $message,
            );
        }
    }

    public function decode(
        RawDataRecord $message,
    ): DecodedDataMessage {
        $definition = $message->definition();
        $rawFields = $message->standardFields();
        $payloadFieldCount = count(
            $definition->payloadStandardFields(),
        );
        $rawFieldCount = count($rawFields);

        if ($rawFieldCount < $payloadFieldCount) {
            throw new \LogicException(sprintf('Raw FIT message is missing standard payload field at position %d.', $rawFieldCount));
        }

        if ($rawFieldCount > $payloadFieldCount) {
            throw new \LogicException('Raw FIT message contains unconsumed standard payload fields.');
        }

        $decodedFields = [];

        $compiledPlans = $this->compiledStandardFieldPlans[
            $definition
        ] ?? null;

        if (null === $compiledPlans) {
            $compiledPlans = $this->compileStandardFieldPlans(
                $definition,
            );
            $this->compiledStandardFieldPlans[$definition] =
                $compiledPlans;
        }
        $compressedTimestamp = $message
            instanceof RawCompressedTimestampDataMessage;

        if ($compressedTimestamp) {
            $decodedFields[] = DecodedStandardField::fromImplicitCompressedTimestamp(
                timestamp: $message
                    ->reconstructedTimestamp,
            );
        }

        foreach (
            $definition->canonicalStandardFieldsByPayloadIndex() as $payloadIndex => $fieldDefinition
        ) {
            if (
                $compressedTimestamp
                && 253 === $fieldDefinition->fieldNumber
            ) {
                continue;
            }

            $rawField = $rawFields[$payloadIndex];

            $decodedFields[] = DecodedStandardField::fromPayload(
                rawField: $rawField,
                value: $this->baseTypes->decodeCompiled(
                    plan: $compiledPlans[$payloadIndex],
                    rawValue: $rawField->value,
                ),
            );
        }

        return DecodedDataMessage::create(
            source: $message,
            standardFields: $decodedFields,
            developerFields: $message->developerFields(),
        );
    }

    /**
     * @return array<int, FitBaseTypeDecodingPlan>
     */
    private function compileStandardFieldPlans(
        MessageDefinition $definition,
    ): array {
        $plans = [];

        foreach (
            $definition->canonicalStandardFieldsByPayloadIndex() as $payloadIndex => $fieldDefinition
        ) {
            $plans[$payloadIndex] = $this->baseTypes->compile(
                baseType: $fieldDefinition->baseType,
                architecture: $definition->architecture,
                fieldSize: $fieldDefinition->size,
            );
        }

        return $plans;
    }
}
