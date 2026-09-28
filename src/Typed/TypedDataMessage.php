<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Typed;

use Youmad\Endurance\Fit\Profiled\ProfiledDataMessage;
use Youmad\Endurance\Fit\Raw\FitArchitecture;
use Youmad\Endurance\Fit\Raw\RawDeveloperField;

final readonly class TypedDataMessage
{
    /**
     * @var list<TypedStandardField>
     */
    private array $standardFields;

    /**
     * @param list<TypedStandardField> $standardFields
     */
    private function __construct(
        public ProfiledDataMessage $source,
        array $standardFields,
    ) {
        $this->standardFields = $standardFields;
    }

    /**
     * @param array<array-key, TypedStandardField> $standardFields
     */
    public static function create(
        ProfiledDataMessage $source,
        array $standardFields,
    ): self {
        $standardFields = array_values(
            $standardFields,
        );

        $sourceFields = $source->standardFields();

        if (
            count($sourceFields)
            !== count($standardFields)
        ) {
            throw new \InvalidArgumentException('Typed FIT message must contain every profiled standard field.');
        }

        foreach (
            $sourceFields as $index => $sourceField
        ) {
            if (
                $standardFields[$index]->source
                !== $sourceField
            ) {
                throw new \InvalidArgumentException(sprintf('Typed FIT field at position %d does not match its profiled source.', $index));
            }
        }

        return new self(
            source: $source,
            standardFields: $standardFields,
        );
    }

    /**
     * @param list<TypedStandardField> $standardFields
     *
     * @internal
     */
    public static function fromPipeline(
        ProfiledDataMessage $source,
        array $standardFields,
    ): self {
        return new self(
            source: $source,
            standardFields: $standardFields,
        );
    }

    /**
     * @return list<TypedStandardField>
     */
    public function standardFields(): array
    {
        return $this->standardFields;
    }

    public function sequence(): int
    {
        return $this->source->sequence();
    }

    public function byteOffset(): int
    {
        return $this->source->byteOffset();
    }

    public function globalMessageNumber(): int
    {
        return $this->source
            ->globalMessageNumber();
    }

    public function architecture(): FitArchitecture
    {
        return $this->source->architecture();
    }

    public function name(): ?string
    {
        return $this->source->name();
    }

    public function standardField(
        int $fieldNumber,
    ): ?TypedStandardField {
        foreach ($this->standardFields as $field) {
            if ($fieldNumber === $field->fieldNumber()) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @return list<RawDeveloperField>
     */
    public function developerFields(): array
    {
        return $this->source
            ->developerFields();
    }
}
