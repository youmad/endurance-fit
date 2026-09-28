<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Profile\Generation\Type;

use Youmad\Endurance\Fit\Profile\Generation\ProfileGenerationException;
use Youmad\Endurance\Fit\Profile\Generation\Xlsx\XlsxWorkbookReader;

final readonly class FitTypeProfileGenerator
{
    public function __construct(
        private TypesSheetParser $parser = new TypesSheetParser(),
        private TypesPhpRenderer $renderer = new TypesPhpRenderer(),
    ) {
    }

    public function build(string $inputPath): FitTypeGenerationResult
    {
        $hash = hash_file('sha256', $inputPath);

        if (false === $hash) {
            throw new ProfileGenerationException(sprintf('Cannot calculate SHA-256 for FIT profile workbook %s.', $inputPath));
        }

        $types = $this->parser->parse(
            (new XlsxWorkbookReader($inputPath))->rows(
                'Types',
            ),
        );

        return new FitTypeGenerationResult(
            typeCount: $types->count(),
            valueNameCount: $types->valueCount(),
            sourceSha256: $hash,
            content: $this->renderer->render(
                types: $types,
                sourceSha256: $hash,
            ),
        );
    }

    public function write(
        string $inputPath,
        string $outputPath,
    ): FitTypeGenerationResult {
        $result = $this->build($inputPath);
        $directory = dirname($outputPath);

        if (
            !is_dir($directory)
            && !mkdir(
                $directory,
                recursive: true,
            )
            && !is_dir($directory)
        ) {
            throw new ProfileGenerationException(sprintf('Cannot create generated FIT profile directory %s.', $directory));
        }

        $temporaryPath = $outputPath
            .'.tmp.'
            .bin2hex(random_bytes(6));

        $written = file_put_contents(
            $temporaryPath,
            $result->content,
            LOCK_EX,
        );

        if (strlen($result->content) !== $written) {
            @unlink($temporaryPath);

            throw new ProfileGenerationException(sprintf('Cannot write generated FIT type registry %s.', $outputPath));
        }

        if (!rename($temporaryPath, $outputPath)) {
            @unlink($temporaryPath);

            throw new ProfileGenerationException(sprintf('Cannot replace generated FIT type registry %s.', $outputPath));
        }

        return $result;
    }

    public function assertUpToDate(
        string $inputPath,
        string $outputPath,
    ): FitTypeGenerationResult {
        $result = $this->build($inputPath);
        $actual = is_file($outputPath)
            ? file_get_contents($outputPath)
            : false;

        if ($result->content !== $actual) {
            throw new ProfileGenerationException(sprintf('Generated FIT type registry %s is out of date. Run fit-generate-types.', $outputPath));
        }

        return $result;
    }
}
