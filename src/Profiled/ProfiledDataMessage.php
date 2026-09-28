<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Profiled;

use Youmad\Endurance\Fit\Decoded\DecodedDataMessage;
use Youmad\Endurance\Fit\Profile\MessageProfile;
use Youmad\Endurance\Fit\Raw\FitArchitecture;
use Youmad\Endurance\Fit\Raw\RawDeveloperField;

final readonly class ProfiledDataMessage
{
    /**
     * @var list<ProfiledStandardField>
     */
    private array $standardFields;

    /**
     * @param list<ProfiledStandardField> $standardFields
     */
    private function __construct(
        public DecodedDataMessage $source,
        public ?MessageProfile $profile,
        array $standardFields,
    ) {
        $this->standardFields = $standardFields;
    }

    /**
     * @param array<array-key, ProfiledStandardField> $standardFields
     */
    public static function create(
        DecodedDataMessage $source,
        ?MessageProfile $profile,
        array $standardFields,
    ): self {
        $standardFields = array_values(
            $standardFields,
        );

        if (
            null !== $profile
            && $profile->globalMessageNumber
            !== $source->globalMessageNumber()
        ) {
            throw new \InvalidArgumentException('FIT message profile does not match the decoded global message number.');
        }

        $sourceFields = $source->standardFields();

        if (
            count($sourceFields)
            !== count($standardFields)
        ) {
            throw new \InvalidArgumentException('Profiled FIT message must contain every decoded standard field.');
        }

        foreach (
            $sourceFields as $index => $sourceField
        ) {
            if (
                $standardFields[$index]->source
                !== $sourceField
            ) {
                throw new \InvalidArgumentException(sprintf('Profiled FIT field at position %d does not match its decoded source.', $index));
            }
        }

        return new self(
            source: $source,
            profile: $profile,
            standardFields: $standardFields,
        );
    }

    /**
     * @param list<ProfiledStandardField> $standardFields
     *
     * @internal
     */
    public static function fromPipeline(
        DecodedDataMessage $source,
        ?MessageProfile $profile,
        array $standardFields,
    ): self {
        return new self(
            source: $source,
            profile: $profile,
            standardFields: $standardFields,
        );
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

    /**
     * @return list<ProfiledStandardField>
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

    public function name(): ?string
    {
        return $this->profile?->name;
    }

    public function isKnown(): bool
    {
        return null !== $this->profile;
    }

    public function standardField(
        int $fieldNumber,
    ): ?ProfiledStandardField {
        foreach ($this->standardFields as $field) {
            if (
                $fieldNumber
                === $field->fieldNumber()
            ) {
                return $field;
            }
        }

        return null;
    }

    /**
     * Developer fields remain raw until their field_description
     * messages have populated the developer profile registry.
     *
     * @return list<RawDeveloperField>
     */
    public function developerFields(): array
    {
        return $this->source
            ->developerFields();
    }
}
