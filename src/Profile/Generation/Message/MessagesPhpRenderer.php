<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Profile\Generation\Message;

final readonly class MessagesPhpRenderer
{
    public function render(
        GeneratedMessages $messages,
        string $sourceSha256,
    ): string {
        $lines = [
            '<?php',
            '',
            'declare(strict_types=1);',
            '',
            'return [',
            sprintf(
                "    'source_sha256' => '%s',",
                $sourceSha256,
            ),
            "    'messages' => [",
        ];

        foreach ($messages->messages() as $message) {
            $lines[] = sprintf(
                '        %d => [',
                $message->globalMessageNumber,
            );
            $lines[] = sprintf(
                "            'name' => '%s',",
                $this->escape($message->name),
            );
            $lines[] = "            'fields' => [";

            foreach ($message->fields() as $field) {
                $lines[] = '                [';
                $lines[] = sprintf(
                    "                    'field_number' => %d,",
                    $field->fieldNumber,
                );
                $lines[] = sprintf(
                    "                    'name' => '%s',",
                    $this->escape($field->name),
                );
                $lines[] = sprintf(
                    "                    'type' => '%s',",
                    $this->escape($field->typeName),
                );
                $lines[] = sprintf(
                    "                    'scale' => %s,",
                    $this->number($field->scale),
                );
                $lines[] = sprintf(
                    "                    'offset' => %s,",
                    $this->number($field->offset),
                );
                $lines[] = sprintf(
                    "                    'units' => %s,",
                    $this->stringOrNull($field->units),
                );
                $lines[] = sprintf(
                    "                    'accumulated' => %s,",
                    $field->accumulated
                        ? 'true'
                        : 'false',
                );
                $this->renderComponents(
                    components: $field->components(),
                    lines: $lines,
                    indentation: 20,
                );
                $this->renderSubfields(
                    subfields: $field->subfields(),
                    lines: $lines,
                    indentation: 20,
                );
                $lines[] = '                ],';
            }

            $lines[] = '            ],';
            $lines[] = '        ],';
        }

        $lines[] = '    ],';
        $lines[] = '];';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * @param list<GeneratedComponentDefinition> $components
     * @param list<string>                       $lines
     */
    private function renderComponents(
        array $components,
        array &$lines,
        int $indentation,
    ): void {
        $indent = str_repeat(' ', $indentation);
        $itemIndent = str_repeat(' ', $indentation + 4);
        $valueIndent = str_repeat(' ', $indentation + 8);

        $lines[] = sprintf(
            "%s'components' => [",
            $indent,
        );

        foreach ($components as $component) {
            $lines[] = $itemIndent.'[';
            $lines[] = sprintf(
                "%s'target_field_number' => %d,",
                $valueIndent,
                $component->targetFieldNumber,
            );
            $lines[] = sprintf(
                "%s'bits' => %d,",
                $valueIndent,
                $component->bits,
            );
            $lines[] = sprintf(
                "%s'scale' => %s,",
                $valueIndent,
                $this->number($component->scale),
            );
            $lines[] = sprintf(
                "%s'offset' => %s,",
                $valueIndent,
                $this->number($component->offset),
            );
            $lines[] = sprintf(
                "%s'units' => %s,",
                $valueIndent,
                $this->stringOrNull($component->units),
            );
            $lines[] = sprintf(
                "%s'accumulated' => %s,",
                $valueIndent,
                $component->accumulated
                    ? 'true'
                    : 'false',
            );
            $lines[] = sprintf(
                "%s'signed' => %s,",
                $valueIndent,
                $component->signed
                    ? 'true'
                    : 'false',
            );
            $lines[] = $itemIndent.'],';
        }

        $lines[] = $indent.'],';
    }

    /**
     * @param list<GeneratedSubfieldDefinition> $subfields
     * @param list<string>                      $lines
     */
    private function renderSubfields(
        array $subfields,
        array &$lines,
        int $indentation,
    ): void {
        $indent = str_repeat(' ', $indentation);
        $itemIndent = str_repeat(' ', $indentation + 4);
        $valueIndent = str_repeat(' ', $indentation + 8);
        $nestedIndent = $indentation + 8;

        $lines[] = sprintf(
            "%s'subfields' => [",
            $indent,
        );

        foreach ($subfields as $subfield) {
            $lines[] = $itemIndent.'[';
            $lines[] = sprintf(
                "%s'name' => '%s',",
                $valueIndent,
                $this->escape($subfield->name),
            );
            $lines[] = sprintf(
                "%s'type' => '%s',",
                $valueIndent,
                $this->escape($subfield->typeName),
            );
            $lines[] = sprintf(
                "%s'scale' => %s,",
                $valueIndent,
                $this->number($subfield->scale),
            );
            $lines[] = sprintf(
                "%s'offset' => %s,",
                $valueIndent,
                $this->number($subfield->offset),
            );
            $lines[] = sprintf(
                "%s'units' => %s,",
                $valueIndent,
                $this->stringOrNull($subfield->units),
            );

            $lines[] = sprintf(
                "%s'conditions' => [",
                $valueIndent,
            );

            foreach ($subfield->conditions() as $condition) {
                $lines[] = str_repeat(' ', $indentation + 12).'[';
                $lines[] = sprintf(
                    "%s'reference_field_number' => %d,",
                    str_repeat(' ', $indentation + 16),
                    $condition->referenceFieldNumber,
                );
                $lines[] = sprintf(
                    "%s'accepted_raw_values' => [%s],",
                    str_repeat(' ', $indentation + 16),
                    implode(', ', $condition->acceptedRawValues()),
                );
                $lines[] = str_repeat(' ', $indentation + 12).'],';
            }

            $lines[] = $valueIndent.'],';
            $this->renderComponents(
                components: $subfield->components(),
                lines: $lines,
                indentation: $nestedIndent,
            );
            $lines[] = $itemIndent.'],';
        }

        $lines[] = $indent.'],';
    }

    private function number(
        int|float|null $value,
    ): string {
        if (null === $value) {
            return 'null';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        return json_encode(
            $value,
            JSON_THROW_ON_ERROR
            | JSON_PRESERVE_ZERO_FRACTION,
        );
    }

    private function stringOrNull(
        ?string $value,
    ): string {
        return null === $value
            ? 'null'
            : "'".$this->escape($value)."'";
    }

    private function escape(
        string $value,
    ): string {
        return str_replace(
            ['\\', "'"],
            ['\\\\', "\\'"],
            $value,
        );
    }
}
