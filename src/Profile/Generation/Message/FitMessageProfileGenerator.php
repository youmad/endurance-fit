<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Profile\Generation\Message;

use Youmad\Endurance\Fit\Profile\Generation\ProfileGenerationException;
use Youmad\Endurance\Fit\Profile\Generation\Type\TypesSheetParser;
use Youmad\Endurance\Fit\Profile\Generation\Xlsx\XlsxWorkbookReader;

final readonly class FitMessageProfileGenerator
{
    public function __construct(
        private TypesSheetParser $types = new TypesSheetParser(),
        private MessagesSheetParser $messages = new MessagesSheetParser(),
        private MessagesPhpRenderer $renderer = new MessagesPhpRenderer(),
    ) {
    }

    public function build(string $inputPath): FitMessageGenerationResult
    {
        $hash = hash_file('sha256', $inputPath);

        if (false === $hash) {
            throw new ProfileGenerationException(sprintf('Cannot calculate SHA-256 for FIT profile workbook %s.', $inputPath));
        }

        $workbook = new XlsxWorkbookReader($inputPath);
        $types = $this->types->parse($workbook->rows('Types'));
        $messages = $this->messages->parse($workbook->rows('Messages'), $types);

        return new FitMessageGenerationResult(
            messageCount: $messages->count(),
            fieldCount: $messages->fieldCount(),
            subfieldCount: $messages->subfieldCount(),
            componentCount: $messages->componentCount(),
            sourceSha256: $hash,
            content: $this->renderer->render($messages, $hash),
        );
    }

    public function write(string $inputPath, string $outputPath): FitMessageGenerationResult
    {
        $result = $this->build($inputPath);
        $directory = dirname($outputPath);

        if (!is_dir($directory) && !mkdir($directory, recursive: true) && !is_dir($directory)) {
            throw new ProfileGenerationException(sprintf('Cannot create generated FIT profile directory %s.', $directory));
        }

        $temporaryPath = $outputPath.'.tmp.'.bin2hex(random_bytes(6));
        $written = file_put_contents($temporaryPath, $result->content, LOCK_EX);

        if (strlen($result->content) !== $written) {
            @unlink($temporaryPath);
            throw new ProfileGenerationException(sprintf('Cannot write generated FIT message registry %s.', $outputPath));
        }

        if (!rename($temporaryPath, $outputPath)) {
            @unlink($temporaryPath);
            throw new ProfileGenerationException(sprintf('Cannot replace generated FIT message registry %s.', $outputPath));
        }

        return $result;
    }

    public function assertUpToDate(string $inputPath, string $outputPath): FitMessageGenerationResult
    {
        $result = $this->build($inputPath);
        $actual = is_file($outputPath) ? file_get_contents($outputPath) : false;

        if ($result->content !== $actual) {
            throw new ProfileGenerationException(sprintf('Generated FIT message registry %s is out of date. Run fit-generate-messages.', $outputPath));
        }

        return $result;
    }
}
