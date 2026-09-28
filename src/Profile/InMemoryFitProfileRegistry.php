<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Profile;

use Youmad\Endurance\Fit\Exception\InvalidFitProfile;

final readonly class InMemoryFitProfileRegistry implements FitProfileRegistry
{
    /**
     * @var array<int, MessageProfile>
     */
    private array $messages;

    public function __construct(
        MessageProfile ...$messages,
    ) {
        $messagesByNumber = [];

        foreach ($messages as $message) {
            if (
                isset(
                    $messagesByNumber[$message->globalMessageNumber],
                )
            ) {
                throw new InvalidFitProfile(sprintf('FIT profile message %d is registered more than once.', $message->globalMessageNumber));
            }

            $messagesByNumber[$message->globalMessageNumber] = $message;
        }

        $this->messages = $messagesByNumber;
    }

    public function message(
        int $globalMessageNumber,
    ): ?MessageProfile {
        return $this->messages[$globalMessageNumber] ?? null;
    }
}
