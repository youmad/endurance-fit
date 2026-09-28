<?php

declare(strict_types=1);

namespace Youmad\Endurance\Fit\Developer;

use Youmad\Endurance\Fit\Exception\InvalidFitProfile;

final readonly class DeveloperDataProfile
{
    private const int MINIMUM_BYTE_VALUE = 0;
    private const int MAXIMUM_BYTE_VALUE = 255;

    /**
     * @var list<int>|null
     */
    private ?array $developerId;

    /**
     * @var list<int>|null
     */
    private ?array $applicationId;

    /**
     * @param list<int>|null $developerId
     * @param list<int>|null $applicationId
     */
    private function __construct(
        public int $developerDataIndex,
        ?array $developerId,
        ?array $applicationId,
        public ?int $manufacturerId,
        public ?int $applicationVersion,
    ) {
        $this->developerId = $developerId;
        $this->applicationId = $applicationId;
    }

    /**
     * @param array<array-key, int>|null $developerId
     * @param array<array-key, int>|null $applicationId
     */
    public static function create(
        int $developerDataIndex,
        ?array $developerId = null,
        ?array $applicationId = null,
        ?int $manufacturerId = null,
        ?int $applicationVersion = null,
    ): self {
        self::assertByte(
            name: 'FIT developer data index',
            value: $developerDataIndex,
        );

        self::assertByteArray(
            name: 'FIT developer id',
            value: $developerId,
        );

        self::assertByteArray(
            name: 'FIT developer application id',
            value: $applicationId,
        );

        if (
            null !== $manufacturerId
            && (
                0 > $manufacturerId
                || 0xFFFF < $manufacturerId
            )
        ) {
            throw new InvalidFitProfile('FIT developer manufacturer id must fit into an unsigned 16-bit integer.');
        }

        if (
            null !== $applicationVersion
            && (
                0 > $applicationVersion
                || 0xFFFFFFFF < $applicationVersion
            )
        ) {
            throw new InvalidFitProfile('FIT developer application version must fit into an unsigned 32-bit integer.');
        }

        return new self(
            developerDataIndex: $developerDataIndex,
            developerId: null === $developerId
                ? null
                : array_values($developerId),
            applicationId: null === $applicationId
                ? null
                : array_values($applicationId),
            manufacturerId: $manufacturerId,
            applicationVersion: $applicationVersion,
        );
    }

    /**
     * @return list<int>|null
     */
    public function developerId(): ?array
    {
        return $this->developerId;
    }

    /**
     * @return list<int>|null
     */
    public function applicationId(): ?array
    {
        return $this->applicationId;
    }

    public function equals(self $other): bool
    {
        return $this->developerDataIndex
                === $other->developerDataIndex
            && $this->developerId === $other->developerId
            && $this->applicationId === $other->applicationId
            && $this->manufacturerId === $other->manufacturerId
            && $this->applicationVersion
                === $other->applicationVersion;
    }

    private static function assertByte(
        string $name,
        int $value,
    ): void {
        if (
            self::MINIMUM_BYTE_VALUE > $value
            || self::MAXIMUM_BYTE_VALUE < $value
        ) {
            throw new InvalidFitProfile(sprintf('%s must fit into one byte.', $name));
        }
    }

    /**
     * @param array<array-key, int>|null $value
     */
    private static function assertByteArray(
        string $name,
        ?array $value,
    ): void {
        if (null === $value) {
            return;
        }

        if ([] === $value) {
            throw new InvalidFitProfile(sprintf('%s cannot be empty when present.', $name));
        }

        foreach ($value as $byte) {
            self::assertByte(
                name: $name.' element',
                value: $byte,
            );
        }
    }
}
