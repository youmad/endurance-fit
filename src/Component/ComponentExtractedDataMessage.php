<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Component;

use Youmad\Endurance\Fit\Profiled\ProfiledDataMessage;

final readonly class ComponentExtractedDataMessage
{
    /**
     * @var list<ExtractedComponentField>
     */
    private array $components;

    /**
     * @param list<ExtractedComponentField> $components
     */
    private function __construct(
        public ProfiledDataMessage $source,
        array $components,
    ) {
        $this->components = $components;
    }

    /**
     * @param array<array-key, ExtractedComponentField> $components
     */
    public static function create(
        ProfiledDataMessage $source,
        array $components,
    ): self {
        $components = array_values($components);
        $sourceFields = $source->standardFields();

        foreach ($components as $component) {
            if (
                !in_array(
                    $component->container,
                    $sourceFields,
                    true,
                )
            ) {
                throw new \InvalidArgumentException('Extracted FIT component container does not belong to its source message.');
            }

            $messageProfile = $source->profile;

            if (
                null === $messageProfile
                || $messageProfile->field(
                    $component->targetFieldNumber(),
                ) !== $component->targetProfile
            ) {
                throw new \InvalidArgumentException('Extracted FIT component target does not belong to its message profile.');
            }
        }

        return new self(
            source: $source,
            components: $components,
        );
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

    /**
     * @return list<ExtractedComponentField>
     */
    public function components(): array
    {
        return $this->components;
    }

    /**
     * @return list<ExtractedComponentField>
     */
    public function componentsForField(
        int $fieldNumber,
    ): array {
        return array_values(
            array_filter(
                $this->components,
                static fn (
                    ExtractedComponentField $component,
                ): bool => $fieldNumber
                    === $component->targetFieldNumber(),
            ),
        );
    }
}
