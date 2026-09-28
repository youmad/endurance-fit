<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Decoder;

use Youmad\Endurance\Fit\Exception\FitDecodeException;
use Youmad\Endurance\Fit\IO\FitInput;
use Youmad\Endurance\Fit\Raw\DeveloperFieldDefinition;
use Youmad\Endurance\Fit\Raw\FitArchitecture;
use Youmad\Endurance\Fit\Raw\FitBaseType;
use Youmad\Endurance\Fit\Raw\MessageDefinition;
use Youmad\Endurance\Fit\Raw\RawCompressedTimestampDataMessage;
use Youmad\Endurance\Fit\Raw\RawDataMessage;
use Youmad\Endurance\Fit\Raw\RawDefinitionMessage;
use Youmad\Endurance\Fit\Raw\RawDeveloperField;
use Youmad\Endurance\Fit\Raw\RawFieldValue;
use Youmad\Endurance\Fit\Raw\RawFitMessage;
use Youmad\Endurance\Fit\Raw\RawStandardField;
use Youmad\Endurance\Fit\Raw\StandardFieldDefinition;

final readonly class RawRecordStreamReader
{
    private const int COMPRESSED_HEADER_MASK = 0x80;
    private const int DEFINITION_MESSAGE_MASK = 0x40;
    private const int DEVELOPER_DATA_MASK = 0x20;

    private const int NORMAL_LOCAL_MESSAGE_NUMBER_MASK = 0x0F;

    private const int COMPRESSED_LOCAL_MESSAGE_NUMBER_SHIFT = 5;
    private const int COMPRESSED_LOCAL_MESSAGE_NUMBER_MASK = 0x03;
    private const int COMPRESSED_TIME_OFFSET_MASK = 0x1F;

    /**
     * @return \Generator<int, RawFitMessage>
     */
    public function read(
        FitInput $input,
        int $dataSize,
        ?RawRecordStreamState $state = null,
    ): \Generator {
        if (0 > $dataSize) {
            throw new FitDecodeException('FIT data section size cannot be negative.');
        }

        $reader = new BoundedBinaryReader(
            reader: new BinaryReader($input),
            length: $dataSize,
        );

        $state ??= new RawRecordStreamState();
        $definitions = &$state->definitions;
        $timestamps = $state->timestamps;
        $sequence = &$state->sequence;

        while (0 < $reader->remaining()) {
            $byteOffset = $reader->position();
            $recordHeaderByte = $reader->readByte();

            if (
                0 !== (
                    $recordHeaderByte
                    & self::COMPRESSED_HEADER_MASK
                )
            ) {
                $localMessageNumber = (
                    $recordHeaderByte
                    >> self::COMPRESSED_LOCAL_MESSAGE_NUMBER_SHIFT
                ) & self::COMPRESSED_LOCAL_MESSAGE_NUMBER_MASK;

                $definition = $this->definitionFor(
                    definitions: $definitions,
                    localMessageNumber: $localMessageNumber,
                    byteOffset: $byteOffset,
                );

                $message = $this
                    ->readCompressedTimestampDataMessage(
                        reader: $reader,
                        timestamps: $timestamps,
                        sequence: $sequence,
                        byteOffset: $byteOffset,
                        recordHeaderByte: $recordHeaderByte,
                        definition: $definition,
                    );
            } elseif (
                0 !== (
                    $recordHeaderByte
                    & self::DEFINITION_MESSAGE_MASK
                )
            ) {
                $message = $this->readDefinitionMessage(
                    reader: $reader,
                    sequence: $sequence,
                    byteOffset: $byteOffset,
                    recordHeaderByte: $recordHeaderByte,
                );

                $definitions[$message->definition->localMessageNumber] = $message->definition;
            } else {
                $localMessageNumber = $recordHeaderByte
                    & self::NORMAL_LOCAL_MESSAGE_NUMBER_MASK;

                $definition = $this->definitionFor(
                    definitions: $definitions,
                    localMessageNumber: $localMessageNumber,
                    byteOffset: $byteOffset,
                );

                $message = $this->readDataMessage(
                    reader: $reader,
                    sequence: $sequence,
                    byteOffset: $byteOffset,
                    recordHeaderByte: $recordHeaderByte,
                    definition: $definition,
                );

                $timestamps->observe($message);
            }

            yield $sequence => $message;

            ++$sequence;
        }
    }

    /**
     * @param array<int, MessageDefinition> $definitions
     */
    private function definitionFor(
        array $definitions,
        int $localMessageNumber,
        int $byteOffset,
    ): MessageDefinition {
        $definition = $definitions[$localMessageNumber] ?? null;

        if (null === $definition) {
            throw new FitDecodeException(sprintf('Data message at byte %d references undefined local message %d.', $byteOffset, $localMessageNumber));
        }

        return $definition;
    }

    private function readCompressedTimestampDataMessage(
        BoundedBinaryReader $reader,
        CompressedTimestampAccumulator $timestamps,
        int $sequence,
        int $byteOffset,
        int $recordHeaderByte,
        MessageDefinition $definition,
    ): RawCompressedTimestampDataMessage {
        $reconstructedTimestamp = $timestamps->reconstruct(
            timeOffset: $recordHeaderByte
            & self::COMPRESSED_TIME_OFFSET_MASK,
            byteOffset: $byteOffset,
        );

        [
            $standardFields,
            $developerFields,
        ] = $this->readFields(
            reader: $reader,
            standardDefinitions: $definition->payloadStandardFields(),
            developerDefinitions: $definition->payloadDeveloperFields(),
        );

        $message = RawCompressedTimestampDataMessage::create(
            sequenceNumber: $sequence,
            byteOffset: $byteOffset,
            recordHeaderByte: $recordHeaderByte,
            definition: $definition,
            reconstructedTimestamp: $reconstructedTimestamp,
            standardFields: $standardFields,
            developerFields: $developerFields,
        );

        $timestamps->observe($message);

        return $message;
    }

    /**
     * @param list<StandardFieldDefinition>  $standardDefinitions
     * @param list<DeveloperFieldDefinition> $developerDefinitions
     *
     * @return array{
     *     0: list<RawStandardField>,
     *     1: list<RawDeveloperField>
     * }
     */
    private function readFields(
        BoundedBinaryReader $reader,
        array $standardDefinitions,
        array $developerDefinitions,
    ): array {
        $standardFields = [];

        foreach ($standardDefinitions as $definition) {
            $standardFields[] = new RawStandardField(
                definition: $definition,
                value: RawFieldValue::fromBytes(
                    $reader->readBytes(
                        $definition->size,
                    ),
                ),
            );
        }

        $developerFields = [];

        foreach ($developerDefinitions as $definition) {
            $developerFields[] = new RawDeveloperField(
                definition: $definition,
                value: RawFieldValue::fromBytes(
                    $reader->readBytes(
                        $definition->size,
                    ),
                ),
            );
        }

        return [
            $standardFields,
            $developerFields,
        ];
    }

    private function readDefinitionMessage(
        BoundedBinaryReader $reader,
        int $sequence,
        int $byteOffset,
        int $recordHeaderByte,
    ): RawDefinitionMessage {
        $localMessageNumber = $recordHeaderByte
            & self::NORMAL_LOCAL_MESSAGE_NUMBER_MASK;

        $reservedByte = $reader->readByte();
        $architectureByte = $reader->readByte();

        $architecture = FitArchitecture::tryFrom(
            $architectureByte,
        );

        if (null === $architecture) {
            throw new FitDecodeException(sprintf('Unsupported FIT architecture %d at byte %d.', $architectureByte, $reader->position() - 1));
        }

        $globalMessageNumber = $reader->readUInt16(
            $architecture,
        );

        $standardFieldCount = $reader->readByte();

        $standardFields = [];

        for (
            $index = 0;
            $index < $standardFieldCount;
            ++$index
        ) {
            $standardFields[] = StandardFieldDefinition::create(
                fieldNumber: $reader->readByte(),
                size: $reader->readByte(),
                baseType: FitBaseType::fromDefinitionByte(
                    $reader->readByte(),
                ),
            );
        }

        $developerFields = [];

        if (
            0 !== (
                $recordHeaderByte
                & self::DEVELOPER_DATA_MASK
            )
        ) {
            $developerFieldCount = $reader->readByte();

            for (
                $index = 0;
                $index < $developerFieldCount;
                ++$index
            ) {
                $developerFields[] = DeveloperFieldDefinition::create(
                    fieldNumber: $reader->readByte(),
                    size: $reader->readByte(),
                    developerDataIndex: $reader->readByte(),
                );
            }
        }

        $definition = MessageDefinition::create(
            localMessageNumber: $localMessageNumber,
            architecture: $architecture,
            globalMessageNumber: $globalMessageNumber,
            standardFields: $standardFields,
            developerFields: $developerFields,
        );

        return new RawDefinitionMessage(
            sequenceNumber: $sequence,
            messageByteOffset: $byteOffset,
            recordHeaderByte: $recordHeaderByte,
            reservedByte: $reservedByte,
            definition: $definition,
        );
    }

    private function readDataMessage(
        BoundedBinaryReader $reader,
        int $sequence,
        int $byteOffset,
        int $recordHeaderByte,
        MessageDefinition $definition,
    ): RawDataMessage {
        [
            $standardFields,
            $developerFields,
        ] = $this->readFields(
            reader: $reader,
            standardDefinitions: $definition->payloadStandardFields(),
            developerDefinitions: $definition->payloadDeveloperFields(),
        );

        return RawDataMessage::create(
            sequenceNumber: $sequence,
            byteOffset: $byteOffset,
            recordHeaderByte: $recordHeaderByte,
            definition: $definition,
            standardFields: $standardFields,
            developerFields: $developerFields,
        );
    }
}
