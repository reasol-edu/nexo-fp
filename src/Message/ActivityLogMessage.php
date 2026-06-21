<?php

declare(strict_types=1);

namespace App\Message;

final class ActivityLogMessage
{
    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(
        public readonly \DateTimeImmutable $createdAt,
        public readonly string $ip,
        public readonly string $actionType,
        public readonly ?string $activeUserId,
        public readonly ?string $realUserId,
        public readonly ?string $academicYearId,
        public readonly ?array $data,
    ) {}
}
