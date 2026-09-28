<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Component;

final readonly class ResolvedComponentField
{
    private function __construct(
        public ExtractedComponentField $source,
        public int $componentRawValue,
        public int|float $physicalValue,
        public int|float $targetRawValue,
        public bool $accumulationApplied,
    ) {
    }

    public static function create(
        ExtractedComponentField $source,
        int $componentRawValue,
        int|float $physicalValue,
        int|float $targetRawValue,
        bool $accumulationApplied,
    ): self {
        if (
            !$source->component->signed
            && 0 > $componentRawValue
        ) {
            throw new \InvalidArgumentException('Resolved unsigned FIT component raw value cannot be negative.');
        }

        if (
            !$accumulationApplied
            && $componentRawValue
            !== $source->packedValue
        ) {
            throw new \InvalidArgumentException('Non-accumulated FIT component must preserve its packed value.');
        }

        if (
            $accumulationApplied
            !== $source->isAccumulated()
        ) {
            throw new \InvalidArgumentException('FIT component accumulation state does not match its target profile.');
        }

        self::assertFinite(
            description: 'FIT component physical value',
            value: $physicalValue,
        );

        self::assertFinite(
            description: 'Synthetic FIT target raw value',
            value: $targetRawValue,
        );

        return new self(
            source: $source,
            componentRawValue: $componentRawValue,
            physicalValue: $physicalValue,
            targetRawValue: $targetRawValue,
            accumulationApplied: $accumulationApplied,
        );
    }

    private static function assertFinite(
        string $description,
        int|float $value,
    ): void {
        if (
            is_float($value)
            && !is_finite($value)
        ) {
            throw new \InvalidArgumentException(sprintf('%s must be finite.', $description));
        }
    }

    public function packedValue(): int
    {
        return $this->source->packedValue;
    }

    public function targetFieldNumber(): int
    {
        return $this->source
            ->targetFieldNumber();
    }

    public function name(): string
    {
        return $this->source->name();
    }

    public function typeName(): string
    {
        return $this->source->typeName();
    }

    public function units(): ?string
    {
        return $this->source->units();
    }
}
