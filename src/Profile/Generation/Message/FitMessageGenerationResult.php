<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Profile\Generation\Message;

final readonly class FitMessageGenerationResult
{
    public function __construct(
        public int $messageCount,
        public int $fieldCount,
        public int $subfieldCount,
        public int $componentCount,
        public string $sourceSha256,
        public string $content,
    ) {
    }
}
