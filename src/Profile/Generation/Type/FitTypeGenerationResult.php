<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Profile\Generation\Type;

final readonly class FitTypeGenerationResult
{
    public function __construct(
        public int $typeCount,
        public int $valueNameCount,
        public string $sourceSha256,
        public string $content,
    ) {
    }
}
