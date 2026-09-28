<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Profile\Generation\Message;

use Youmad\Endurance\Fit\Profile\Generation\ProfileGenerationException;

final readonly class GeneratedMessages
{
    /** @var list<GeneratedMessageDefinition> */
    private array $messages;

    /** @param list<GeneratedMessageDefinition> $messages */
    private function __construct(array $messages)
    {
        $this->messages = $messages;
    }

    /** @param array<array-key, mixed> $messages */
    public static function create(array $messages): self
    {
        $messages = array_values($messages);
        $numbers = [];
        $names = [];

        foreach ($messages as $message) {
            if (!$message instanceof GeneratedMessageDefinition) {
                throw new ProfileGenerationException('FIT generated messages must contain GeneratedMessageDefinition objects.');
            }

            if (isset($numbers[$message->globalMessageNumber])) {
                throw new ProfileGenerationException(sprintf('FIT generated message number %d is defined more than once.', $message->globalMessageNumber));
            }

            if (isset($names[$message->name])) {
                throw new ProfileGenerationException(sprintf('FIT generated message %s is defined more than once.', $message->name));
            }

            $numbers[$message->globalMessageNumber] = true;
            $names[$message->name] = true;
        }

        /* @var list<GeneratedMessageDefinition> $messages Validated above. */
        return new self($messages);
    }

    /** @return list<GeneratedMessageDefinition> */
    public function messages(): array
    {
        return $this->messages;
    }

    public function count(): int
    {
        return count($this->messages);
    }

    public function fieldCount(): int
    {
        $count = 0;

        foreach ($this->messages as $message) {
            $count += count($message->fields());
        }

        return $count;
    }

    public function subfieldCount(): int
    {
        $count = 0;

        foreach ($this->messages as $message) {
            foreach ($message->fields() as $field) {
                $count += count($field->subfields());
            }
        }

        return $count;
    }

    public function componentCount(): int
    {
        $count = 0;

        foreach ($this->messages as $message) {
            foreach ($message->fields() as $field) {
                $count += count($field->components());

                foreach ($field->subfields() as $subfield) {
                    $count += count($subfield->components());
                }
            }
        }

        return $count;
    }
}
