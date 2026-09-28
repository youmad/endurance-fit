<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Decoded;

enum DecodedFieldOrigin: string
{
    case MessagePayload = 'message_payload';
    case CompressedTimestampHeader = 'compressed_timestamp_header';
}
