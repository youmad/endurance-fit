<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Decoder;

use Youmad\Endurance\Fit\Decoded\DecodedDataMessage;
use Youmad\Endurance\Fit\IO\FitInput;
use Youmad\Endurance\Fit\Profile\FitProfileRegistry;
use Youmad\Endurance\Fit\Profile\FitTypeRegistry;
use Youmad\Endurance\Fit\Profile\Generated\GeneratedFitProfileSet;
use Youmad\Endurance\Fit\Raw\RawDataRecord;
use Youmad\Endurance\Fit\Raw\RawFitMessage;
use Youmad\Endurance\Fit\Unified\UnifiedDataMessage;

final readonly class FitDecoder
{
    public static function standard(
        GeneratedFitProfileSet $profileSet,
    ): self {
        return new self(
            profiles: $profileSet->profiles,
            types: $profileSet->types,
        );
    }

    public function __construct(
        private FitProfileRegistry $profiles,
        private FitTypeRegistry $types,
        private FitFileReader $files = new FitFileReader(),
    ) {
    }

    /**
     * Decodes one standalone message with fresh component and
     * developer profile state.
     */
    public function decode(
        RawDataRecord|DecodedDataMessage $message,
    ): UnifiedDataMessage {
        return $this->newMessageProcessor()
            ->process($message);
    }

    /**
     * Every invocation creates an independent FIT stream session.
     *
     * @param iterable<RawFitMessage|DecodedDataMessage> $messages
     *
     * @return \Generator<int, UnifiedDataMessage>
     */
    public function decodeStream(
        iterable $messages,
    ): \Generator {
        yield from $this->newMessageProcessor()
            ->processStream($messages);
    }

    public function open(
        FitInput $input,
    ): UnifiedFitFileStream {
        return new UnifiedFitFileStream(
            source: $this->files->open($input),
            processor: $this->newMessageProcessor(),
        );
    }

    /**
     * Creates a stateful processor for callers that receive one
     * FIT message at a time. The returned processor must not be
     * shared by independent files.
     */
    public function newMessageProcessor(): FitDataMessageProcessor
    {
        return (new FitDecodingSession(
            profiles: $this->profiles,
            types: $this->types,
        ))->messages;
    }
}
