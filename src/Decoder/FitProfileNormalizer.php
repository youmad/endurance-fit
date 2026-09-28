<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Decoder;

use Youmad\Endurance\Fit\Decoded\DecodedDataMessage;
use Youmad\Endurance\Fit\Decoded\DecodedStandardField;
use Youmad\Endurance\Fit\Profile\FieldProfile;
use Youmad\Endurance\Fit\Profile\FitProfileRegistry;
use Youmad\Endurance\Fit\Profile\SubfieldProfile;
use Youmad\Endurance\Fit\Profiled\ProfiledDataMessage;
use Youmad\Endurance\Fit\Profiled\ProfiledStandardField;
use Youmad\Endurance\Fit\Profiled\ProfileNormalizationState;
use Youmad\Endurance\Fit\Typed\TypedDataMessage;

final readonly class FitProfileNormalizer
{
    public function __construct(
        private FitProfileRegistry $profiles,
        private FitSubfieldSelector $subfields = new FitSubfieldSelector(),
        private FitFieldValueTransformer $transforms = new FitFieldValueTransformer(),
    ) {
    }

    /**
     * @param iterable<DecodedDataMessage> $messages
     *
     * @return \Generator<int, ProfiledDataMessage>
     */
    public function normalizeStream(
        iterable $messages,
    ): \Generator {
        foreach ($messages as $sequence => $message) {
            yield $sequence => $this->normalize(
                $message,
            );
        }
    }

    public function normalize(
        DecodedDataMessage $message,
    ): ProfiledDataMessage {
        $messageProfile = $this->profiles->message(
            $message->globalMessageNumber(),
        );

        $profiledFields = [];

        foreach ($message->standardFields() as $field) {
            $fieldProfile = $messageProfile?->field(
                $field->definition->fieldNumber,
            );

            $subfield = null === $fieldProfile
                ? null
                : $this->subfields->select(
                    field: $fieldProfile,
                    message: $message,
                );

            $profiledFields[] = $this->normalizeField(
                field: $field,
                profile: $fieldProfile,
                subfield: $subfield,
            );
        }

        return ProfiledDataMessage::create(
            source: $message,
            profile: $messageProfile,
            standardFields: $profiledFields,
        );
    }

    /**
     * Optimized processor path: normalize and resolve physical types in one
     * field pass. Public standalone normalize()/resolve() keep their validating
     * factories and remain the reference implementation.
     *
     * @return array{ProfiledDataMessage, TypedDataMessage, list<ProfiledStandardField>}
     *
     * @internal optimized decoder pipeline entry point
     */
    public function normalizeWithTypes(
        DecodedDataMessage $message,
        FitTypeValueResolver $types,
    ): array {
        $messageProfile = $this->profiles->message(
            $message->globalMessageNumber(),
        );
        $profiledFields = [];
        $typedFields = [];
        $componentContainers = [];

        foreach ($message->standardFields() as $field) {
            $fieldProfile = $messageProfile?->field(
                $field->definition->fieldNumber,
            );
            $subfield = null === $fieldProfile
                ? null
                : $this->subfields->select(
                    field: $fieldProfile,
                    message: $message,
                );

            $profiledField = $this->normalizeField(
                field: $field,
                profile: $fieldProfile,
                subfield: $subfield,
                trustedPipeline: true,
            );

            $profiledFields[] = $profiledField;
            $typedFields[] = $types->resolveFieldForPipeline(
                $profiledField,
            );

            $components = null !== $profiledField->subfield
                ? $profiledField->subfield->components()
                : $profiledField->profile?->components() ?? [];

            if ([] !== $components) {
                $componentContainers[] = $profiledField;
            }
        }

        $profiled = ProfiledDataMessage::fromPipeline(
            source: $message,
            profile: $messageProfile,
            standardFields: $profiledFields,
        );

        return [
            $profiled,
            TypedDataMessage::fromPipeline(
                source: $profiled,
                standardFields: $typedFields,
            ),
            $componentContainers,
        ];
    }

    /**
     * Record hot path: normalize standard fields and identify component
     * containers without materializing physical type-resolution objects.
     *
     * @return array{ProfiledDataMessage, list<ProfiledStandardField>}
     *
     * @internal optimized Record pipeline entry point
     */
    public function normalizeForRecordPipeline(
        DecodedDataMessage $message,
    ): array {
        $messageProfile = $this->profiles->message(
            $message->globalMessageNumber(),
        );
        $profiledFields = [];
        $componentContainers = [];

        foreach ($message->standardFields() as $field) {
            $fieldProfile = $messageProfile?->field(
                $field->definition->fieldNumber,
            );
            $subfield = null === $fieldProfile
                ? null
                : $this->subfields->select(
                    field: $fieldProfile,
                    message: $message,
                );

            $profiledField = $this->normalizeField(
                field: $field,
                profile: $fieldProfile,
                subfield: $subfield,
                trustedPipeline: true,
            );

            $profiledFields[] = $profiledField;

            $components = null !== $profiledField->subfield
                ? $profiledField->subfield->components()
                : $profiledField->profile?->components() ?? [];

            if ([] !== $components) {
                $componentContainers[] = $profiledField;
            }
        }

        return [
            ProfiledDataMessage::fromPipeline(
                source: $message,
                profile: $messageProfile,
                standardFields: $profiledFields,
            ),
            $componentContainers,
        ];
    }

    private function normalizeField(
        DecodedStandardField $field,
        ?FieldProfile $profile,
        ?SubfieldProfile $subfield,
        bool $trustedPipeline = false,
    ): ProfiledStandardField {
        if (null === $profile) {
            if ($trustedPipeline) {
                return ProfiledStandardField::fromPipeline(
                    source: $field,
                    profile: null,
                    value: $field->value,
                    normalizationState: ProfileNormalizationState::Unavailable,
                );
            }

            return ProfiledStandardField::create(
                source: $field,
                profile: null,
                value: $field->value,
                normalizationState: ProfileNormalizationState::Unavailable,
            );
        }

        $transform = $subfield->transform
            ?? $profile->transform;

        if ($transform->isIdentity()) {
            if ($trustedPipeline) {
                return ProfiledStandardField::fromPipeline(
                    source: $field,
                    profile: $profile,
                    value: $field->value,
                    normalizationState: ProfileNormalizationState::NotRequired,
                    subfield: $subfield,
                );
            }

            return ProfiledStandardField::create(
                source: $field,
                profile: $profile,
                value: $field->value,
                normalizationState: ProfileNormalizationState::NotRequired,
                subfield: $subfield,
            );
        }

        $value = $this->transforms->apply(
            value: $field->value,
            transform: $transform,
        );

        if (null === $value) {
            if ($trustedPipeline) {
                return ProfiledStandardField::fromPipeline(
                    source: $field,
                    profile: $profile,
                    value: $field->value,
                    normalizationState: ProfileNormalizationState::Unavailable,
                    subfield: $subfield,
                );
            }

            return ProfiledStandardField::create(
                source: $field,
                profile: $profile,
                value: $field->value,
                normalizationState: ProfileNormalizationState::Unavailable,
                subfield: $subfield,
            );
        }

        $normalizationState = $this
            ->transforms
            ->containsValidElement($value)
            ? ProfileNormalizationState::Applied
            : ProfileNormalizationState::NotRequired;

        if ($trustedPipeline) {
            return ProfiledStandardField::fromPipeline(
                source: $field,
                profile: $profile,
                value: $value,
                normalizationState: $normalizationState,
                subfield: $subfield,
            );
        }

        return ProfiledStandardField::create(
            source: $field,
            profile: $profile,
            value: $value,
            normalizationState: $normalizationState,
            subfield: $subfield,
        );
    }
}
