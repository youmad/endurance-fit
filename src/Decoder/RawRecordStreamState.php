<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Decoder;

use Youmad\Endurance\Fit\Raw\MessageDefinition;

/** State shared by the member files of one chained FIT input. */
final class RawRecordStreamState
{
    /** @var array<int, MessageDefinition> */
    public array $definitions = [];
    public int $sequence = 0;
    public readonly CompressedTimestampAccumulator $timestamps;

    public function __construct()
    {
        $this->timestamps = new CompressedTimestampAccumulator();
    }
}
