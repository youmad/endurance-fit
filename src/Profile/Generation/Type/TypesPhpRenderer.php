<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Profile\Generation\Type;

final readonly class TypesPhpRenderer
{
    public function render(
        GeneratedTypes $types,
        string $sourceSha256,
    ): string {
        $lines = [
            '<?php',
            '',
            'declare(strict_types=1);',
            '',
            '/**',
            ' * This file is generated. Do not edit it manually.',
            ' */',
            '',
            'return [',
            sprintf(
                "    'source_sha256' => '%s',",
                $sourceSha256,
            ),
            "    'types' => [",
        ];

        foreach ($types->types() as $type) {
            $lines[] = sprintf(
                "        '%s' => [",
                $this->escape($type->name),
            );
            $lines[] = sprintf(
                "            'base_type' => '%s',",
                $this->escape($type->baseType),
            );
            $lines[] = "            'values' => [";

            foreach ($type->values() as $value) {
                $lines[] = '                [';
                $lines[] = sprintf(
                    "                    'value' => %d,",
                    $value->value,
                );
                $lines[] = sprintf(
                    "                    'name' => '%s',",
                    $this->escape($value->name),
                );

                if ([] === $value->aliases()) {
                    $lines[] = "                    'aliases' => [],";
                } else {
                    $lines[] = "                    'aliases' => [";

                    foreach ($value->aliases() as $alias) {
                        $lines[] = sprintf(
                            "                        '%s',",
                            $this->escape($alias),
                        );
                    }

                    $lines[] = '                    ],';
                }

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

    private function escape(string $value): string
    {
        return str_replace(
            ['\\', "'"],
            ['\\\\', "\\'"],
            $value,
        );
    }
}
