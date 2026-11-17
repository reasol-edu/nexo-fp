<?php

namespace App\Controller;

use App\Repository\ActivityLogRepository;
use App\Repository\EducationalCentreRepository;
use App\Repository\TeacherRepository;
use App\Service\Backup\AutomaticBackup;
use App\Service\FailedMessages;
use App\Service\SystemStatus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    public function __construct(
        private readonly TeacherRepository $teachers,
        private readonly EducationalCentreRepository $centres,
        private readonly ActivityLogRepository $activityLogs,
        private readonly AutomaticBackup $automaticBackup,
        private readonly SystemStatus $systemStatus,
        private readonly FailedMessages $failedMessages,
    ) {}

    #[Route('/admin', name: 'app_admin')]
    public function index(): Response
    {
        return $this->render('admin/index.html.twig', [
            'backup' => $this->automaticBackup->status(),
            'worker' => $this->systemStatus->worker(),
            'failed_count' => $this->failedMessages->count(),
            'stats' => [
                'teachers_total'  => $this->teachers->countAll(),
                'teachers_active' => $this->teachers->countActive(),
                'teachers_admin'  => $this->teachers->countAdmins(),
                'centres_total'   => $this->centres->countAll(),
                'logs_today'      => $this->activityLogs->countToday(),
            ],
        ]);
    }
}
