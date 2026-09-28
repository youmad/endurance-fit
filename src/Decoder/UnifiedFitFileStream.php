<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Decoder;

use Youmad\Endurance\Fit\Exception\FitDecodeException;
use Youmad\Endurance\Fit\Raw\FitFileHeader;
use Youmad\Endurance\Fit\Raw\FitFileTrailer;
use Youmad\Endurance\Fit\Unified\UnifiedDataMessage;

final class UnifiedFitFileStream
{
    public readonly FitFileHeader $header;
    private bool $started = false;

    public function __construct(
        private readonly RawFitFileStream $source,
        private readonly FitDataMessageProcessor $processor,
    ) {
        $this->header = $source->header;
    }

    /**
     * @return \Generator<int, UnifiedDataMessage>
     */
    public function messages(): \Generator
    {
        if ($this->started) {
            throw new FitDecodeException('Unified FIT file message stream can only be consumed once.');
        }

        $this->started = true;

        yield from $this->processor->processStream(
            $this->source->messages(),
        );
    }

    public function isCompleted(): bool
    {
        return $this->source->isCompleted();
    }

    public function trailer(): FitFileTrailer
    {
        return $this->source->trailer();
    }
}
