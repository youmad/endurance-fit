<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Developer;

use Youmad\Endurance\Fit\Exception\FitDecodeException;
use Youmad\Endurance\Fit\Profile\FieldTransform;
use Youmad\Endurance\Fit\Profile\FitProfileRegistry;
use Youmad\Endurance\Fit\Raw\FitBaseType;
use Youmad\Endurance\Fit\Typed\TypedDataMessage;
use Youmad\Endurance\Fit\Typed\TypedEnumFieldElement;
use Youmad\Endurance\Fit\Typed\TypedFieldElements;
use Youmad\Endurance\Fit\Typed\TypedInvalidFieldElement;
use Youmad\Endurance\Fit\Typed\TypedScalarFieldElement;

final readonly class FitDeveloperProfileCollector
{
    private const int FIELD_DESCRIPTION_MESSAGE = 206;
    private const int DEVELOPER_DATA_ID_MESSAGE = 207;

    public function __construct(
        private FitDeveloperProfileRegistry $registry,
        private FitProfileRegistry $profiles,
    ) {
    }

    public function observe(
        TypedDataMessage $message,
    ): void {
        match ($message->globalMessageNumber()) {
            self::DEVELOPER_DATA_ID_MESSAGE => $this->collectDeveloperData($message),

            self::FIELD_DESCRIPTION_MESSAGE => $this->collectFieldDescription($message),

            default => null,
        };
    }

    private function collectDeveloperData(
        TypedDataMessage $message,
    ): void {
        $developerDataIndex = $this->requiredInt(
            message: $message,
            fieldNumber: 3,
            description: 'developer_data_id.developer_data_index',
        );

        $this->registry->registerDeveloperData(
            DeveloperDataProfile::create(
                developerDataIndex: $developerDataIndex,
                developerId: $this->optionalBytes(
                    message: $message,
                    fieldNumber: 0,
                    description: 'developer_data_id.developer_id',
                ),
                applicationId: $this->optionalBytes(
                    message: $message,
                    fieldNumber: 1,
                    description: 'developer_data_id.application_id',
                ),
                manufacturerId: $this->optionalInt(
                    message: $message,
                    fieldNumber: 2,
                    description: 'developer_data_id.manufacturer_id',
                ),
                applicationVersion: $this->optionalInt(
                    message: $message,
                    fieldNumber: 4,
                    description: 'developer_data_id.application_version',
                ),
            ),
        );
    }

    private function collectFieldDescription(
        TypedDataMessage $message,
    ): void {
        $developerDataIndex = $this->requiredInt(
            message: $message,
            fieldNumber: 0,
            description: 'field_description.developer_data_index',
        );

        if (
            null === $this->registry
                ->developerData($developerDataIndex)
        ) {
            return;
        }

        $fieldDefinitionNumber = $this->requiredInt(
            message: $message,
            fieldNumber: 1,
            description: 'field_description.field_definition_number',
        );

        $baseTypeId = $this->requiredInt(
            message: $message,
            fieldNumber: 2,
            description: 'field_description.fit_base_type_id',
        );

        $name = $this->optionalFirstString(
            message: $message,
            fieldNumber: 3,
            description: 'field_description.field_name',
        ) ?? sprintf(
            'developer_%d_field_%d',
            $developerDataIndex,
            $fieldDefinitionNumber,
        );

        $scale = $this->optionalInt(
            message: $message,
            fieldNumber: 6,
            description: 'field_description.scale',
        ) ?? 1;

        $offset = $this->optionalInt(
            message: $message,
            fieldNumber: 7,
            description: 'field_description.offset',
        ) ?? 0;

        $nativeMessageNumber = $this->optionalInt(
            message: $message,
            fieldNumber: 14,
            description: 'field_description.native_mesg_num',
        );

        $nativeFieldNumber = $this->optionalInt(
            message: $message,
            fieldNumber: 15,
            description: 'field_description.native_field_num',
        );

        $nativeField = null;

        if (
            null !== $nativeMessageNumber
            && null !== $nativeFieldNumber
        ) {
            $nativeField = $this->profiles
                ->message($nativeMessageNumber)
                ?->field($nativeFieldNumber);
        }

        $this->registry->registerField(
            DeveloperFieldProfile::create(
                developerDataIndex: $developerDataIndex,
                fieldDefinitionNumber: $fieldDefinitionNumber,
                baseType: FitBaseType::fromDefinitionByte(
                    $baseTypeId,
                ),
                name: $name,
                isArray: 0 !== (
                    $this->optionalInt(
                        message: $message,
                        fieldNumber: 4,
                        description: 'field_description.array',
                    ) ?? 0
                ),
                transform: FieldTransform::scaleAndOffset(
                    scale: $scale,
                    offset: $offset,
                ),
                units: $this->optionalFirstString(
                    message: $message,
                    fieldNumber: 8,
                    description: 'field_description.units',
                ),
                components: $this->optionalString(
                    message: $message,
                    fieldNumber: 5,
                    description: 'field_description.components',
                ),
                bits: $this->optionalString(
                    message: $message,
                    fieldNumber: 9,
                    description: 'field_description.bits',
                ),
                accumulate: $this->optionalString(
                    message: $message,
                    fieldNumber: 10,
                    description: 'field_description.accumulate',
                ),
                fitBaseUnitId: $this->optionalInt(
                    message: $message,
                    fieldNumber: 13,
                    description: 'field_description.fit_base_unit_id',
                ),
                nativeMessageNumber: $nativeMessageNumber,
                nativeFieldNumber: $nativeFieldNumber,
                nativeField: $nativeField,
            ),
        );
    }

    private function requiredInt(
        TypedDataMessage $message,
        int $fieldNumber,
        string $description,
    ): int {
        $value = $this->optionalInt(
            message: $message,
            fieldNumber: $fieldNumber,
            description: $description,
        );

        if (null === $value) {
            throw new FitDecodeException(sprintf('Required FIT metadata field %s is unavailable.', $description));
        }

        return $value;
    }

    private function optionalInt(
        TypedDataMessage $message,
        int $fieldNumber,
        string $description,
    ): ?int {
        $values = $this->validValues(
            message: $message,
            fieldNumber: $fieldNumber,
            description: $description,
        );

        if ([] === $values) {
            return null;
        }

        if (
            1 !== count($values)
            || !is_int($values[0])
        ) {
            throw new FitDecodeException(sprintf('FIT metadata field %s must contain one integer.', $description));
        }

        return $values[0];
    }

    /**
     * @return list<int>|null
     */
    private function optionalBytes(
        TypedDataMessage $message,
        int $fieldNumber,
        string $description,
    ): ?array {
        $values = $this->validValues(
            message: $message,
            fieldNumber: $fieldNumber,
            description: $description,
        );

        if ([] === $values) {
            return null;
        }

        foreach ($values as $value) {
            if (
                !is_int($value)
                || 0 > $value
                || 255 < $value
            ) {
                throw new FitDecodeException(sprintf('FIT metadata field %s must contain bytes.', $description));
            }
        }

        /* @var list<int> $values */
        return $values;
    }

    private function optionalFirstString(
        TypedDataMessage $message,
        int $fieldNumber,
        string $description,
    ): ?string {
        $values = $this->validValues(
            message: $message,
            fieldNumber: $fieldNumber,
            description: $description,
        );

        if ([] === $values) {
            return null;
        }

        if (!is_string($values[0])) {
            throw new FitDecodeException(sprintf('FIT metadata field %s must start with a string.', $description));
        }

        return $values[0];
    }

    private function optionalString(
        TypedDataMessage $message,
        int $fieldNumber,
        string $description,
    ): ?string {
        $values = $this->validValues(
            message: $message,
            fieldNumber: $fieldNumber,
            description: $description,
        );

        if ([] === $values) {
            return null;
        }

        if (
            1 !== count($values)
            || !is_string($values[0])
        ) {
            throw new FitDecodeException(sprintf('FIT metadata field %s must contain one string.', $description));
        }

        return $values[0];
    }

    /**
     * @return list<int|float|string>
     */
    private function validValues(
        TypedDataMessage $message,
        int $fieldNumber,
        string $description,
    ): array {
        $field = $message->standardField(
            $fieldNumber,
        );

        if (null === $field) {
            return [];
        }

        if (!$field->value instanceof TypedFieldElements) {
            throw new FitDecodeException(sprintf('FIT metadata field %s could not be decoded.', $description));
        }

        $values = [];

        foreach ($field->value->elements() as $element) {
            if ($element instanceof TypedInvalidFieldElement) {
                continue;
            }

            if ($element instanceof TypedScalarFieldElement) {
                $values[] = $element->value();

                continue;
            }

            if ($element instanceof TypedEnumFieldElement) {
                $values[] = $element->value;

                continue;
            }

            throw new FitDecodeException(sprintf('FIT metadata field %s contains unsupported element %s.', $description, $element::class));
        }

        return $values;
    }
}
