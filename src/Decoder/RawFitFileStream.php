<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Decoder;

use Youmad\Endurance\Fit\Checksum\FitCrc16;
use Youmad\Endurance\Fit\Exception\FitDecodeException;
use Youmad\Endurance\Fit\IO\FitInput;
use Youmad\Endurance\Fit\Raw\FitFileHeader;
use Youmad\Endurance\Fit\Raw\FitFileTrailer;
use Youmad\Endurance\Fit\Raw\RawFitMessage;

final class RawFitFileStream
{
    private bool $started = false;

    private bool $completed = false;

    private ?FitFileTrailer $trailer = null;

    public function __construct(
        public readonly FitFileHeader $header,
        private readonly FitInput $input,
        private readonly FitInput $dataInput,
        private readonly FitCrc16 $fileCrc,
        private readonly RawRecordStreamReader $records,
    ) {
    }

    /**
     * @return \Generator<int, RawFitMessage>
     */
    public function messages(): \Generator
    {
        if ($this->started) {
            throw new FitDecodeException('FIT file message stream can only be consumed once.');
        }

        $this->started = true;

        $state = new RawRecordStreamState();
        $member = $this;
        $files = new FitFileReader($this->records);

        while (true) {
            yield from $member->readMember($state);
            $this->trailer = $member->trailer;

            if ($this->input->isAtEnd()) {
                $this->completed = true;

                return;
            }

            $state->timestamps->beginNextFile();
            $member = $files->open($this->input);
        }
    }

    /** @return \Generator<int, RawFitMessage> */
    private function readMember(RawRecordStreamState $state): \Generator
    {
        foreach (
            $this->records->read(
                input: $this->dataInput,
                dataSize: $this->header->dataSize,
                state: $state,
            ) as $sequence => $message
        ) {
            yield $sequence => $message;
        }

        $trailerOffset = $this->input->position();

        $declaredCrc = $this->decodeUInt16(
            $this->input->readExact(2),
        );

        $this->trailer = new FitFileTrailer(
            byteOffset: $trailerOffset,
            declaredCrc: $declaredCrc,
            calculatedCrc: $this->fileCrc->value(),
        );
    }

    private function decodeUInt16(
        string $bytes,
    ): int {
        $value = unpack(
            'vvalue',
            $bytes,
        );

        if (
            false === $value
            || !isset($value['value'])
        ) {
            throw new FitDecodeException('Failed to decode FIT file CRC.');
        }

        return $value['value'];
    }

    public function isCompleted(): bool
    {
        return $this->completed;
    }

    public function trailer(): FitFileTrailer
    {
        if (!$this->completed || null === $this->trailer) {
            throw new FitDecodeException('FIT file trailer is unavailable until the message stream is fully consumed.');
        }

        return $this->trailer;
    }
}
