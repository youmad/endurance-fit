<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Profile\Generated;

use Youmad\Endurance\Fit\Exception\InvalidFitProfile;
use Youmad\Endurance\Fit\Profile\FitTypeProfile;
use Youmad\Endurance\Fit\Profile\FitTypeRegistry;
use Youmad\Endurance\Fit\Profile\TypeValueProfile;

final readonly class GeneratedFitTypeRegistry implements FitTypeRegistry
{
    /**
     * @var array<string, FitTypeProfile>
     */
    private array $types;

    public string $sourceSha256;

    public function __construct(string $dataFile)
    {
        if (!is_file($dataFile)) {
            throw new InvalidFitProfile(sprintf('Generated FIT type data file %s does not exist.', $dataFile));
        }

        $data = require $dataFile;

        if (!is_array($data)) {
            throw new InvalidFitProfile('Generated FIT type data must return an array.');
        }

        $sourceSha256 = $data['source_sha256']
            ?? null;
        $typeData = $data['types']
            ?? null;

        if (
            !is_string($sourceSha256)
            || 1 !== preg_match('/^[a-f0-9]{64}$/', $sourceSha256)
            || !is_array($typeData)
        ) {
            throw new InvalidFitProfile('Generated FIT type data has invalid metadata.');
        }

        $types = [];

        foreach ($typeData as $typeName => $definition) {
            if (
                !is_string($typeName)
                || !is_array($definition)
            ) {
                throw new InvalidFitProfile('Generated FIT type definition has invalid shape.');
            }

            $baseType = $definition['base_type']
                ?? null;
            $valuesData = $definition['values']
                ?? null;

            if (
                !is_string($baseType)
                || !is_array($valuesData)
            ) {
                throw new InvalidFitProfile(sprintf('Generated FIT type %s has invalid definition.', $typeName));
            }

            $values = [];

            foreach ($valuesData as $valueData) {
                if (!is_array($valueData)) {
                    throw new InvalidFitProfile(sprintf('Generated FIT type %s has invalid value definition.', $typeName));
                }

                $value = $valueData['value']
                    ?? null;
                $name = $valueData['name']
                    ?? null;
                $aliases = $valueData['aliases']
                    ?? null;

                if (
                    !is_int($value)
                    || !is_string($name)
                    || !is_array($aliases)
                    || !array_is_list($aliases)
                ) {
                    throw new InvalidFitProfile(sprintf('Generated FIT type %s has invalid symbolic value.', $typeName));
                }

                foreach ($aliases as $alias) {
                    if (!is_string($alias)) {
                        throw new InvalidFitProfile(sprintf('Generated FIT type %s has a non-string alias.', $typeName));
                    }
                }

                $values[] = TypeValueProfile::create(
                    value: $value,
                    name: $name,
                    aliases: $aliases,
                );
            }

            if (isset($types[$typeName])) {
                throw new InvalidFitProfile(sprintf('Generated FIT type %s is registered more than once.', $typeName));
            }

            $types[$typeName] = FitTypeProfile::create(
                name: $typeName,
                values: $values,
                baseType: $baseType,
            );
        }

        $this->sourceSha256 = $sourceSha256;
        $this->types = $types;
    }

    public function type(string $name): ?FitTypeProfile
    {
        return $this->types[$name] ?? null;
    }

    public function count(): int
    {
        return count($this->types);
    }
}
