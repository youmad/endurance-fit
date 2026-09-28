<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Raw;

use Youmad\Endurance\Fit\Exception\InvalidFitDefinition;

final readonly class FitBaseType
{
    private const int BASE_TYPE_MASK = 0x1F;
    private const int MINIMUM_DEFINITION_BYTE = 0;
    private const int MAXIMUM_DEFINITION_BYTE = 255;

    private function __construct(
        private int $definitionByte,
        private int $number,
    ) {
    }

    public static function fromDefinitionByte(
        int $definitionByte,
    ): self {
        if (
            self::MINIMUM_DEFINITION_BYTE > $definitionByte
            || self::MAXIMUM_DEFINITION_BYTE < $definitionByte
        ) {
            throw new InvalidFitDefinition('FIT base type definition must fit into one byte.');
        }

        return new self(
            definitionByte: $definitionByte,
            number: $definitionByte & self::BASE_TYPE_MASK,
        );
    }

    public function definitionByte(): int
    {
        return $this->definitionByte;
    }

    public function number(): int
    {
        return $this->number;
    }

    public function isKnown(): bool
    {
        return null !== $this->kind();
    }

    public function kind(): ?FitBaseTypeKind
    {
        return FitBaseTypeKind::tryFrom(
            $this->number,
        );
    }

    public function equals(self $other): bool
    {
        return $this->definitionByte
            === $other->definitionByte;
    }
}
