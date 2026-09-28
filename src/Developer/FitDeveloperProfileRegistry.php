<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Developer;

final class FitDeveloperProfileRegistry
{
    /**
     * @var array<int, DeveloperDataProfile>
     */
    private array $developerData = [];

    /**
     * @var array<string, DeveloperFieldProfile>
     */
    private array $fields = [];

    public function registerDeveloperData(
        DeveloperDataProfile $profile,
    ): void {
        $this->developerData[
            $profile->developerDataIndex
        ] = $profile;

        $this->forgetFieldsForDeveloperDataIndex(
            $profile->developerDataIndex,
        );
    }

    public function registerField(
        DeveloperFieldProfile $profile,
    ): void {
        if (
            !isset(
                $this->developerData[
                    $profile->developerDataIndex
                ],
            )
        ) {
            return;
        }

        $this->fields[
            $this->fieldKey(
                developerDataIndex: $profile->developerDataIndex,
                fieldDefinitionNumber: $profile->fieldDefinitionNumber,
            )
        ] = $profile;
    }

    public function developerData(
        int $developerDataIndex,
    ): ?DeveloperDataProfile {
        return $this->developerData[
            $developerDataIndex
        ] ?? null;
    }

    public function field(
        int $developerDataIndex,
        int $fieldDefinitionNumber,
    ): ?DeveloperFieldProfile {
        return $this->fields[
            $this->fieldKey(
                developerDataIndex: $developerDataIndex,
                fieldDefinitionNumber: $fieldDefinitionNumber,
            )
        ] ?? null;
    }

    public function reset(): void
    {
        $this->developerData = [];
        $this->fields = [];
    }

    private function forgetFieldsForDeveloperDataIndex(
        int $developerDataIndex,
    ): void {
        $prefix = $developerDataIndex.':';

        foreach (array_keys($this->fields) as $key) {
            if (str_starts_with($key, $prefix)) {
                unset($this->fields[$key]);
            }
        }
    }

    private function fieldKey(
        int $developerDataIndex,
        int $fieldDefinitionNumber,
    ): string {
        return sprintf(
            '%d:%d',
            $developerDataIndex,
            $fieldDefinitionNumber,
        );
    }
}
