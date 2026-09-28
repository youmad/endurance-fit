<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Tests\Profile\Generated;

use PHPUnit\Framework\TestCase;
use Youmad\Endurance\Fit\Exception\InvalidFitProfile;
use Youmad\Endurance\Fit\Profile\Generated\GeneratedFitProfileSet;

final class GeneratedFitProfileSetTest extends TestCase
{
    /** @var list<string> */
    private array $temporaryFiles = [];

    public function testRejectsRegistriesGeneratedFromDifferentProfiles(): void
    {
        $messagesFile = $this->temporaryPhpFile(
            [
                'source_sha256' => str_repeat('a', 64),
                'messages' => [],
            ],
        );

        $typesFile = $this->temporaryPhpFile(
            [
                'source_sha256' => str_repeat('b', 64),
                'types' => [],
            ],
        );

        $this->expectException(
            InvalidFitProfile::class,
        );

        $this->expectExceptionMessage(
            'originate from different Profile.xlsx files',
        );

        GeneratedFitProfileSet::load(
            messagesFile: $messagesFile,
            typesFile: $typesFile,
        );
    }

    public function testMissingExplicitMessagesFileDoesNotFallBackToBundledData(): void
    {
        $this->expectException(InvalidFitProfile::class);
        $this->expectExceptionMessage('Generated FIT message data file');

        GeneratedFitProfileSet::load(
            messagesFile: __DIR__.'/missing/messages.php',
            typesFile: __DIR__.'/missing/types.php',
        );
    }

    public function testMissingExplicitTypesFileDoesNotFallBackToBundledData(): void
    {
        $messagesFile = $this->temporaryPhpFile([
            'source_sha256' => str_repeat('a', 64),
            'messages' => [],
        ]);

        $this->expectException(InvalidFitProfile::class);
        $this->expectExceptionMessage('Generated FIT type data file');

        GeneratedFitProfileSet::load(
            messagesFile: $messagesFile,
            typesFile: __DIR__.'/missing/types.php',
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function temporaryPhpFile(array $data): string
    {
        $path = tempnam(
            sys_get_temp_dir(),
            'fit-profile-',
        );

        self::assertIsString($path);

        file_put_contents(
            $path,
            "<?php\n\ndeclare(strict_types=1);\n\nreturn "
                .var_export($data, true)
                .";\n",
        );

        $this->temporaryFiles[] = $path;

        return $path;
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            @unlink($path);
        }

        $this->temporaryFiles = [];
    }
}
