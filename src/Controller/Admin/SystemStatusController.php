<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Teacher;
use App\Service\ActivityLogService;
use App\Service\Backup\AutomaticBackup;
use App\Service\FailedMessages;
use App\Service\MercureStatus;
use App\Service\SystemStatus;
use App\Service\TestEmailSender;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Administración → Estado del sistema: si el worker está vivo, qué ha fallado en segundo plano, si el correo funciona y cuánto
 * espacio ocupan los datos. Todo lo que cambia algo es un POST con token CSRF y queda en el registro de actividad.
 */
#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/estado')]
class SystemStatusController extends AbstractController
{
    public function __construct(
        private readonly SystemStatus $status,
        private readonly FailedMessages $failed,
        private readonly AutomaticBackup $automaticBackup,
        private readonly TestEmailSender $testEmail,
        private readonly MercureStatus $mercure,
        private readonly RateLimiterFactoryInterface $adminTestEmailLimiter,
        private readonly ActivityLogService $activityLog,
        private readonly TranslatorInterface $translator,
    ) {}

    #[Route('', name: 'app_admin_system_status', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();

        return $this->render('admin/system_status/index.html.twig', [
            'worker'       => $this->status->worker(),
            'storage'      => $this->status->storage(),
            'failed_count' => $this->failed->count(),
            'failed'       => $this->failed->list(),
            'backup'       => $this->automaticBackup->status(),
            'email'        => $user instanceof Teacher ? $user->getEmail() : null,
        ]);
    }

    #[Route('/fallidos/{id}/reintentar', name: 'app_admin_system_failed_retry', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function retry(string $id, Request $request): Response
    {
        $this->requireCsrf('system_failed_' . $id, $request);

        $class = $this->failed->retry($id);
        if ($class === null) {
            $this->addFlash('error', $this->translator->trans('status.failed.gone', [], 'admin'));
        } else {
            $this->activityLog->log('system.failed_retried', ['message' => $class]);
            $this->addFlash('success', $this->translator->trans('status.failed.retried', ['%message%' => $class], 'admin'));
        }

        return $this->redirectToRoute('app_admin_system_status');
    }

    #[Route('/fallidos/{id}/descartar', name: 'app_admin_system_failed_discard', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function discard(string $id, Request $request): Response
    {
        $this->requireCsrf('system_failed_' . $id, $request);

        $class = $this->failed->discard($id);
        if ($class === null) {
            $this->addFlash('error', $this->translator->trans('status.failed.gone', [], 'admin'));
        } else {
            $this->activityLog->log('system.failed_discarded', ['message' => $class]);
            $this->addFlash('success', $this->translator->trans('status.failed.discarded', ['%message%' => $class], 'admin'));
        }

        return $this->redirectToRoute('app_admin_system_status');
    }

    /** Envía un correo de prueba solo a la dirección del propio administrador (nunca a una de la petición), como mucho tres veces cada 15 minutos. */
    #[Route('/correo-prueba', name: 'app_admin_system_test_email', methods: ['POST'])]
    public function testEmail(Request $request): Response
    {
        $this->requireCsrf('system_test_email', $request);

        $user  = $this->getUser();
        $email = $user instanceof Teacher ? $user->getEmail() : null;
        if ($user === null || $email === null || $email === '') {
            $this->addFlash('error', $this->translator->trans('status.email.no_address', [], 'admin'));

            return $this->redirectToRoute('app_admin_system_status');
        }

        if (!$this->adminTestEmailLimiter->create($user->getUserIdentifier())->consume()->isAccepted()) {
            $this->addFlash('error', $this->translator->trans('status.email.throttled', [], 'admin'));

            return $this->redirectToRoute('app_admin_system_status');
        }

        $error = $this->testEmail->send($email);
        $this->activityLog->log('system.test_email', ['ok' => $error === null]);

        $this->addFlash(
            $error === null ? 'success' : 'error',
            $error === null
                ? $this->translator->trans('status.email.sent', ['%email%' => $email], 'admin')
                : $this->translator->trans('status.email.failed', ['%reason%' => $error], 'admin'),
        );

        return $this->redirectToRoute('app_admin_system_status');
    }

    /** Publica un aviso de prueba en el hub de Mercure y dice si lo ha aceptado. */
    #[Route('/mercure', name: 'app_admin_system_mercure', methods: ['POST'])]
    public function mercure(Request $request): Response
    {
        $this->requireCsrf('system_mercure', $request);

        $result = $this->mercure->check();
        $this->activityLog->log('system.mercure_check', ['state' => $result['state']]);

        match ($result['state']) {
            MercureStatus::OK => $this->addFlash('success', $this->translator->trans('status.mercure.ok', ['%url%' => $result['url']], 'admin')),
            MercureStatus::NOT_CONFIG => $this->addFlash('error', $this->translator->trans('status.mercure.not_configured', [], 'admin')),
            default => $this->addFlash('error', $this->translator->trans('status.mercure.failed', ['%url%' => $result['url'], '%reason%' => $result['error'] ?? ''], 'admin')),
        };

        return $this->redirectToRoute('app_admin_system_status');
    }

    private function requireCsrf(string $id, Request $request): void
    {
        if (!$this->isCsrfTokenValid($id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
    }
}
