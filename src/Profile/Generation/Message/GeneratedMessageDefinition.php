<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Profile\Generation\Message;

use Youmad\Endurance\Fit\Profile\Generation\ProfileGenerationException;

final readonly class GeneratedMessageDefinition
{
    /** @var list<GeneratedMessageFieldDefinition> */
    private array $fields;

    /** @param list<GeneratedMessageFieldDefinition> $fields */
    private function __construct(
        public int $globalMessageNumber,
        public string $name,
        array $fields,
    ) {
        $this->fields = $fields;
    }

    /** @param array<array-key, mixed> $fields */
    public static function create(int $globalMessageNumber, string $name, array $fields = []): self
    {
        if (0 > $globalMessageNumber || 65_535 < $globalMessageNumber) {
            throw new ProfileGenerationException('FIT generated global message number must fit into an unsigned 16-bit integer.');
        }

        if (1 !== preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            throw new ProfileGenerationException('FIT generated message name must be a snake_case identifier.');
        }

        $fields = array_values($fields);
        $numbers = [];
        $names = [];

        foreach ($fields as $field) {
            if (!$field instanceof GeneratedMessageFieldDefinition) {
                throw new ProfileGenerationException('FIT generated message fields must contain GeneratedMessageFieldDefinition objects.');
            }

            if (isset($numbers[$field->fieldNumber])) {
                throw new ProfileGenerationException(sprintf('FIT generated message %s defines field %d more than once.', $name, $field->fieldNumber));
            }

            if (isset($names[$field->name])) {
                throw new ProfileGenerationException(sprintf('FIT generated message %s defines field name %s more than once.', $name, $field->name));
            }

            $numbers[$field->fieldNumber] = true;
            $names[$field->name] = true;
        }

        /* @var list<GeneratedMessageFieldDefinition> $fields Validated above. */
        return new self($globalMessageNumber, $name, $fields);
    }

    /** @return list<GeneratedMessageFieldDefinition> */
    public function fields(): array
    {
        return $this->fields;
    }
}
