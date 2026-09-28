<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Raw;

interface RawDataRecord extends RawFitMessage
{
    public function definition(): MessageDefinition;

    public function recordHeaderByte(): int;

    public function globalMessageNumber(): int;

    /**
     * Fields physically stored in the message payload.
     *
     * A compressed timestamp header contributes a reconstructed
     * timestamp separately and does not change this payload list.
     *
     * @return list<RawStandardField>
     */
    public function standardFields(): array;

    /**
     * @return list<RawDeveloperField>
     */
    public function developerFields(): array;
}
