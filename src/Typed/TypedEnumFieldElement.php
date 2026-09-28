<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Typed;

use Youmad\Endurance\Fit\Decoded\ValidFieldElement;
use Youmad\Endurance\Fit\Profile\TypeValueProfile;

final readonly class TypedEnumFieldElement implements TypedFieldElement
{
    private function __construct(
        public ValidFieldElement $source,
        public int $value,
        public ?TypeValueProfile $profile,
    ) {
    }

    public static function create(
        ValidFieldElement $source,
        ?TypeValueProfile $profile,
    ): self {
        if (!is_int($source->value)) {
            throw new \InvalidArgumentException('A symbolic FIT type can only resolve an integer value.');
        }

        if (
            null !== $profile
            && $profile->value !== $source->value
        ) {
            throw new \InvalidArgumentException('FIT symbolic type value does not match its numeric source.');
        }

        return new self(
            source: $source,
            value: $source->value,
            profile: $profile,
        );
    }

    public function name(): ?string
    {
        return $this->profile?->name;
    }

    public function isKnown(): bool
    {
        return null !== $this->profile;
    }
}
