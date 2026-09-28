<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Profile\Generated;

use Youmad\Endurance\Fit\Exception\InvalidFitProfile;

final readonly class GeneratedFitProfileSet
{
    private function __construct(
        public GeneratedFitProfileRegistry $profiles,
        public GeneratedFitTypeRegistry $types,
    ) {
        if ($profiles->sourceSha256 !== $types->sourceSha256) {
            throw new InvalidFitProfile(sprintf('Generated FIT message and type registries originate from different Profile.xlsx files: %s and %s.', $profiles->sourceSha256, $types->sourceSha256));
        }
    }

    public static function load(
        string $messagesFile,
        string $typesFile,
    ): self {
        return new self(
            profiles: new GeneratedFitProfileRegistry(
                $messagesFile,
            ),
            types: new GeneratedFitTypeRegistry(
                $typesFile,
            ),
        );
    }

    public function sourceSha256(): string
    {
        return $this->profiles->sourceSha256;
    }
}
