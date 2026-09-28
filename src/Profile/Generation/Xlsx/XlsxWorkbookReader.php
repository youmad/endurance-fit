<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Profile\Generation\Xlsx;

use Youmad\Endurance\Fit\Profile\Generation\ProfileGenerationException;

final class XlsxWorkbookReader
{
    private const string WORKBOOK_PATH = 'xl/workbook.xml';
    private const string WORKBOOK_RELATIONSHIPS_PATH =
        'xl/_rels/workbook.xml.rels';
    private const string SHARED_STRINGS_PATH =
        'xl/sharedStrings.xml';

    private \PharData $archive;

    /**
     * @var list<string>|null
     */
    private ?array $sharedStrings = null;

    /**
     * @var array<string, string>|null
     */
    private ?array $sheetPaths = null;

    public function __construct(
        private readonly string $path,
    ) {
        if (!is_file($path)) {
            throw new ProfileGenerationException(sprintf('FIT profile workbook %s does not exist.', $path));
        }

        try {
            $this->archive = new \PharData($path);
        } catch (\Throwable $exception) {
            throw new ProfileGenerationException(sprintf('Cannot open FIT profile workbook %s: %s', $path, $exception->getMessage()), previous: $exception);
        }
    }

    /**
     * @return \Generator<int, SpreadsheetRow>
     */
    public function rows(string $sheetName): \Generator
    {
        $path = $this->sheetPaths()[$sheetName]
            ?? null;

        if (null === $path) {
            throw new ProfileGenerationException(sprintf('FIT profile workbook has no sheet named %s.', $sheetName));
        }

        $xml = $this->entry($path);

        $matched = preg_match_all(
            '~<(?:[A-Za-z_][A-Za-z0-9_.-]*:)?row\b([^>]*)>(.*?)</(?:[A-Za-z_][A-Za-z0-9_.-]*:)?row>~s',
            $xml,
            $rows,
            PREG_SET_ORDER,
        );

        if (false === $matched) {
            throw new ProfileGenerationException(sprintf('Cannot parse rows from XLSX sheet %s.', $sheetName));
        }

        foreach ($rows as $row) {
            $attributes = $this->attributes($row[1]);
            $rowNumber = $this->positiveInteger(
                value: $attributes['r'] ?? null,
                description: sprintf(
                    'row number in sheet %s',
                    $sheetName,
                ),
            );

            $values = [];

            $cellCount = preg_match_all(
                '~<(?:[A-Za-z_][A-Za-z0-9_.-]*:)?c\b([^>]*?)(?:>(.*?)</(?:[A-Za-z_][A-Za-z0-9_.-]*:)?c>|/>)~s',
                $row[2],
                $cells,
                PREG_SET_ORDER,
            );

            if (false === $cellCount) {
                throw new ProfileGenerationException(sprintf('Cannot parse cells from row %d in XLSX sheet %s.', $rowNumber, $sheetName));
            }

            foreach ($cells as $cell) {
                $cellAttributes = $this->attributes(
                    $cell[1],
                );

                $reference = $cellAttributes['r']
                    ?? null;

                if (null === $reference) {
                    throw new ProfileGenerationException(sprintf('A cell in row %d of sheet %s has no reference.', $rowNumber, $sheetName));
                }

                $columnIndex = $this->columnIndex(
                    $reference,
                );

                while (count($values) <= $columnIndex) {
                    $values[] = '';
                }

                $values[$columnIndex] = $this->cellValue(
                    type: $cellAttributes['t'] ?? null,
                    content: $cell[2] ?? '',
                    sheetName: $sheetName,
                    cellReference: $reference,
                );
            }

            yield $rowNumber => new SpreadsheetRow(
                number: $rowNumber,
                values: $values,
            );
        }
    }

    /**
     * @return array<string, string>
     */
    private function sheetPaths(): array
    {
        if (null !== $this->sheetPaths) {
            return $this->sheetPaths;
        }

        $relationships = [];
        $relationshipsXml = $this->entry(
            self::WORKBOOK_RELATIONSHIPS_PATH,
        );

        $relationshipCount = preg_match_all(
            '~<(?:[A-Za-z_][A-Za-z0-9_.-]*:)?Relationship\b([^>]*)/?>~s',
            $relationshipsXml,
            $relationshipTags,
            PREG_SET_ORDER,
        );

        if (false === $relationshipCount) {
            throw new ProfileGenerationException('Cannot parse XLSX workbook relationships.');
        }

        foreach ($relationshipTags as $tag) {
            $attributes = $this->attributes($tag[1]);
            $id = $attributes['Id'] ?? null;
            $target = $attributes['Target'] ?? null;

            if (
                null === $id
                || null === $target
            ) {
                continue;
            }

            $relationships[$id] = $this->normalizeTarget(
                $target,
            );
        }

        $sheetPaths = [];
        $workbookXml = $this->entry(
            self::WORKBOOK_PATH,
        );

        $sheetCount = preg_match_all(
            '~<(?:[A-Za-z_][A-Za-z0-9_.-]*:)?sheet\b([^>]*)/?>~s',
            $workbookXml,
            $sheetTags,
            PREG_SET_ORDER,
        );

        if (false === $sheetCount) {
            throw new ProfileGenerationException('Cannot parse XLSX workbook sheets.');
        }

        foreach ($sheetTags as $tag) {
            $attributes = $this->attributes($tag[1]);
            $name = $attributes['name'] ?? null;
            $relationshipId = $attributes['r:id']
                ?? $attributes['id']
                ?? null;

            if (
                null === $name
                || null === $relationshipId
            ) {
                throw new ProfileGenerationException('An XLSX workbook sheet has incomplete metadata.');
            }

            $sheetPath = $relationships[$relationshipId]
                ?? null;

            if (null === $sheetPath) {
                throw new ProfileGenerationException(sprintf('XLSX sheet %s references unknown relationship %s.', $name, $relationshipId));
            }

            $sheetPaths[$name] = $sheetPath;
        }

        $this->sheetPaths = $sheetPaths;

        return $this->sheetPaths;
    }

    private function normalizeTarget(string $target): string
    {
        if (str_starts_with($target, '/')) {
            return ltrim($target, '/');
        }

        return 'xl/'.ltrim($target, '/');
    }

    private function cellValue(
        ?string $type,
        string $content,
        string $sheetName,
        string $cellReference,
    ): string {
        if ('inlineStr' === $type) {
            return $this->textContent($content);
        }

        $value = $this->elementValue(
            content: $content,
            elementName: 'v',
        );

        if (null === $value) {
            return '';
        }

        if ('s' !== $type) {
            return $this->decodeXml($value);
        }

        $index = $this->nonNegativeInteger(
            value: trim($value),
            description: sprintf(
                'shared string index at %s!%s',
                $sheetName,
                $cellReference,
            ),
        );

        $strings = $this->sharedStrings();

        if (!array_key_exists($index, $strings)) {
            throw new ProfileGenerationException(sprintf('XLSX cell %s!%s references unknown shared string %d.', $sheetName, $cellReference, $index));
        }

        return $strings[$index];
    }

    /**
     * @return list<string>
     */
    private function sharedStrings(): array
    {
        if (null !== $this->sharedStrings) {
            return $this->sharedStrings;
        }

        if (!isset($this->archive[self::SHARED_STRINGS_PATH])) {
            $this->sharedStrings = [];

            return $this->sharedStrings;
        }

        $xml = $this->entry(
            self::SHARED_STRINGS_PATH,
        );

        $count = preg_match_all(
            '~<(?:[A-Za-z_][A-Za-z0-9_.-]*:)?si\b[^>]*>(.*?)</(?:[A-Za-z_][A-Za-z0-9_.-]*:)?si>~s',
            $xml,
            $items,
            PREG_SET_ORDER,
        );

        if (false === $count) {
            throw new ProfileGenerationException('Cannot parse XLSX shared strings.');
        }

        $strings = [];

        foreach ($items as $item) {
            $strings[] = $this->textContent(
                $item[1],
            );
        }

        $this->sharedStrings = $strings;

        return $this->sharedStrings;
    }

    private function textContent(string $xml): string
    {
        $count = preg_match_all(
            '~<(?:[A-Za-z_][A-Za-z0-9_.-]*:)?t\b[^>]*(?:>(.*?)</(?:[A-Za-z_][A-Za-z0-9_.-]*:)?t>|/>)~s',
            $xml,
            $texts,
            PREG_SET_ORDER,
        );

        if (false === $count) {
            throw new ProfileGenerationException('Cannot parse XLSX text content.');
        }

        $value = '';

        foreach ($texts as $text) {
            $value .= $this->decodeXml(
                $text[1] ?? '',
            );
        }

        return $value;
    }

    private function elementValue(
        string $content,
        string $elementName,
    ): ?string {
        $matched = preg_match(
            sprintf(
                '~<(?:[A-Za-z_][A-Za-z0-9_.-]*:)?%1$s\b[^>]*>(.*?)</(?:[A-Za-z_][A-Za-z0-9_.-]*:)?%1$s>~s',
                preg_quote($elementName, '~'),
            ),
            $content,
            $matches,
        );

        if (false === $matched) {
            throw new ProfileGenerationException(sprintf('Cannot parse XLSX %s element.', $elementName));
        }

        return 1 === $matched
            ? $matches[1]
            : null;
    }

    /**
     * @return array<string, string>
     */
    private function attributes(string $source): array
    {
        $count = preg_match_all(
            '~([A-Za-z_][A-Za-z0-9_.-]*(?::[A-Za-z_][A-Za-z0-9_.-]*)?)="([^"]*)"~s',
            $source,
            $matches,
            PREG_SET_ORDER,
        );

        if (false === $count) {
            throw new ProfileGenerationException('Cannot parse XLSX XML attributes.');
        }

        $attributes = [];

        foreach ($matches as $match) {
            $attributes[$match[1]] = $this->decodeXml(
                $match[2],
            );
        }

        return $attributes;
    }

    private function columnIndex(string $reference): int
    {
        if (
            1 !== preg_match(
                '/^([A-Z]+)[1-9][0-9]*$/',
                $reference,
                $matches,
            )
        ) {
            throw new ProfileGenerationException(sprintf('Invalid XLSX cell reference %s.', $reference));
        }

        $number = 0;

        foreach (str_split($matches[1]) as $letter) {
            $number = ($number * 26)
                + ord($letter)
                - ord('A')
                + 1;
        }

        return $number - 1;
    }

    private function positiveInteger(
        ?string $value,
        string $description,
    ): int {
        $integer = $this->nonNegativeInteger(
            value: $value,
            description: $description,
        );

        if (1 > $integer) {
            throw new ProfileGenerationException(sprintf('XLSX %s must be positive.', $description));
        }

        return $integer;
    }

    private function nonNegativeInteger(
        ?string $value,
        string $description,
    ): int {
        if (
            null === $value
            || 1 !== preg_match('/^[0-9]+$/', $value)
        ) {
            throw new ProfileGenerationException(sprintf('XLSX %s must be a non-negative integer.', $description));
        }

        $integer = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 0,
                ],
            ],
        );

        if (false === $integer) {
            throw new ProfileGenerationException(sprintf('XLSX %s exceeds the supported integer range.', $description));
        }

        return $integer;
    }

    private function decodeXml(string $value): string
    {
        return html_entity_decode(
            $value,
            ENT_QUOTES | ENT_XML1,
            'UTF-8',
        );
    }

    private function entry(string $path): string
    {
        if (!isset($this->archive[$path])) {
            throw new ProfileGenerationException(sprintf('XLSX archive has no entry %s.', $path));
        }

        $content = file_get_contents(
            $this->archive[$path]->getPathName(),
        );

        if (false === $content) {
            throw new ProfileGenerationException(sprintf('Cannot read XLSX archive entry %s.', $path));
        }

        return preg_replace('/^\xEF\xBB\xBF/', '', $content)
            ?? $content;
    }
}
