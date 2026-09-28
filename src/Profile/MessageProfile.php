<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Profile;

use Youmad\Endurance\Fit\Exception\InvalidFitProfile;

final readonly class MessageProfile
{
    private const int MINIMUM_GLOBAL_MESSAGE_NUMBER = 0;
    private const int MAXIMUM_GLOBAL_MESSAGE_NUMBER = 65_535;

    /**
     * @var list<FieldProfile>
     */
    private array $fields;

    /**
     * @var array<int, FieldProfile>
     */
    private array $fieldsByNumber;

    /**
     * @param list<FieldProfile>       $fields
     * @param array<int, FieldProfile> $fieldsByNumber
     */
    private function __construct(
        public int $globalMessageNumber,
        public string $name,
        array $fields,
        array $fieldsByNumber,
    ) {
        $this->fields = $fields;
        $this->fieldsByNumber = $fieldsByNumber;
    }

    /**
     * @param array<array-key, FieldProfile> $fields
     */
    public static function create(
        int $globalMessageNumber,
        string $name,
        array $fields = [],
    ): self {
        if (
            self::MINIMUM_GLOBAL_MESSAGE_NUMBER
            > $globalMessageNumber
            || self::MAXIMUM_GLOBAL_MESSAGE_NUMBER
            < $globalMessageNumber
        ) {
            throw new InvalidFitProfile('FIT profile global message number must fit into an unsigned 16-bit integer.');
        }

        if (
            1 !== preg_match(
                '/^[a-z][a-z0-9_]*$/',
                $name,
            )
        ) {
            throw new InvalidFitProfile('FIT profile message name must be a snake_case identifier.');
        }

        $fields = array_values($fields);
        $fieldsByNumber = [];

        foreach ($fields as $field) {
            if (
                isset(
                    $fieldsByNumber[$field->fieldNumber],
                )
            ) {
                throw new InvalidFitProfile(sprintf('FIT profile message %d defines field %d more than once.', $globalMessageNumber, $field->fieldNumber));
            }

            $fieldsByNumber[$field->fieldNumber] = $field;
        }

        return new self(
            globalMessageNumber: $globalMessageNumber,
            name: $name,
            fields: $fields,
            fieldsByNumber: $fieldsByNumber,
        );
    }

    /**
     * @return list<FieldProfile>
     */
    public function fields(): array
    {
        return $this->fields;
    }

    public function field(
        int $fieldNumber,
    ): ?FieldProfile {
        return $this->fieldsByNumber[$fieldNumber] ?? null;
    }
}
