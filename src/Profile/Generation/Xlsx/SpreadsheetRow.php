<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Profile\Generation\Xlsx;

final readonly class SpreadsheetRow
{
    /**
     * @var list<string>
     */
    private array $values;

    /**
     * @param array<array-key, string> $values
     */
    public function __construct(
        public int $number,
        array $values,
    ) {
        if (1 > $number) {
            throw new \InvalidArgumentException('Spreadsheet row number must be positive.');
        }

        $this->values = array_values($values);
    }

    public function value(int $columnIndex): string
    {
        if (0 > $columnIndex) {
            throw new \InvalidArgumentException('Spreadsheet column index cannot be negative.');
        }

        return $this->values[$columnIndex] ?? '';
    }

    /**
     * @return list<string>
     */
    public function values(): array
    {
        return $this->values;
    }
}
