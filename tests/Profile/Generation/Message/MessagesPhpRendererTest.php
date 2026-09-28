<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Tests\Profile\Generation\Message;

use PHPUnit\Framework\TestCase;
use Youmad\Endurance\Fit\Profile\Generation\Message\GeneratedComponentDefinition;
use Youmad\Endurance\Fit\Profile\Generation\Message\GeneratedMessageDefinition;
use Youmad\Endurance\Fit\Profile\Generation\Message\GeneratedMessageFieldDefinition;
use Youmad\Endurance\Fit\Profile\Generation\Message\GeneratedMessages;
use Youmad\Endurance\Fit\Profile\Generation\Message\GeneratedSubfieldConditionDefinition;
use Youmad\Endurance\Fit\Profile\Generation\Message\GeneratedSubfieldDefinition;
use Youmad\Endurance\Fit\Profile\Generation\Message\MessagesPhpRenderer;

final class MessagesPhpRendererTest extends TestCase
{
    public function testRendersDeterministicExecutablePhpData(): void
    {
        $messages = GeneratedMessages::create([
            GeneratedMessageDefinition::create(
                globalMessageNumber: 20,
                name: 'record',
                fields: [
                    GeneratedMessageFieldDefinition::create(
                        fieldNumber: 6,
                        name: 'speed',
                        typeName: 'uint16',
                        scale: 1000,
                        units: 'm/s',
                        subfields: [
                            GeneratedSubfieldDefinition::create(
                                name: 'running_speed',
                                typeName: 'uint16',
                                scale: 1000,
                                units: 'm/s',
                                conditions: [
                                    GeneratedSubfieldConditionDefinition::create(
                                        referenceFieldNumber: 4,
                                        acceptedRawValues: [1, 2],
                                    ),
                                ],
                            ),
                        ],
                        components: [
                            GeneratedComponentDefinition::create(
                                targetFieldNumber: 73,
                                bits: 16,
                                scale: 1000,
                                units: 'm/s',
                                accumulated: true,
                                signed: true,
                            ),
                        ],
                    ),
                ],
            ),
        ]);

        $renderer = new MessagesPhpRenderer();
        $hash = str_repeat('b', 64);
        $first = $renderer->render($messages, $hash);

        self::assertSame(
            $first,
            $renderer->render($messages, $hash),
        );
        self::assertStringNotContainsString(
            'generated_at',
            $first,
        );

        $path = tempnam(
            sys_get_temp_dir(),
            'fit-messages-',
        );
        self::assertNotFalse($path);

        try {
            file_put_contents($path, $first);
            $data = require $path;
            $field = $data['messages'][20]['fields'][0];

            self::assertSame(1000, $field['scale']);
            self::assertSame(
                73,
                $field['components'][0]['target_field_number'],
            );
            self::assertTrue(
                $field['components'][0]['accumulated'],
            );
            self::assertTrue(
                $field['components'][0]['signed'],
            );
            self::assertSame(
                [1, 2],
                $field['subfields'][0]['conditions'][0]['accepted_raw_values'],
            );
        } finally {
            @unlink($path);
        }
    }
}
