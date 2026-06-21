<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\ActivityLog;
use App\Message\ActivityLogMessage;
use App\Repository\AcademicYearRepository;
use App\Repository\TeacherRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ActivityLogHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TeacherRepository $teachers,
        private readonly AcademicYearRepository $years,
    ) {}

    public function __invoke(ActivityLogMessage $message): void
    {
        $activeUser   = $message->activeUserId   ? $this->teachers->find($message->activeUserId)   : null;
        $realUser     = $message->realUserId     ? $this->teachers->find($message->realUserId)     : null;
        $academicYear = $message->academicYearId ? $this->years->find($message->academicYearId)    : null;

        $log = new ActivityLog(
            createdAt:    $message->createdAt,
            ip:           $message->ip,
            actionType:   $message->actionType,
            activeUser:   $activeUser,
            realUser:     $realUser,
            academicYear: $academicYear,
            data:         $message->data,
        );

        $this->em->persist($log);
        $this->em->flush();
    }
}
