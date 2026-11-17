<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\ActivityLogService;
use App\Service\Backup\AutomaticBackup;
use App\Service\Backup\BackupLibrary;
use App\Service\Backup\DatabaseBackupService;
use App\Service\Backup\StoredBackup;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Copias de seguridad de la plataforma, solo para administradores globales: descargar una copia nueva (opcionalmente cifrada con
 * contraseña), guardarla en la carpeta de copias del servidor y descargar o borrar las que ya están allí.
 *
 * Restaurar no se hace desde la web: sustituye TODOS los datos, y se hace con `php bin/console app:restore` en el servidor.
 */
#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/sistema')]
class SystemController extends AbstractController
{
    public function __construct(
        private readonly DatabaseBackupService $backup,
        private readonly BackupLibrary $library,
        private readonly AutomaticBackup $automaticBackup,
        private readonly ActivityLogService $activityLog,
        private readonly ClockInterface $clock,
        private readonly TranslatorInterface $translator,
        #[Autowire('%app.version%')]
        private readonly string $appVersion,
    ) {}

    #[Route('', name: 'app_admin_system', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/system/index.html.twig', [
            'copies'     => $this->library->list(),
            'backup'     => $this->automaticBackup->status(),
            'backup_dir' => $this->library->directory(),
        ]);
    }

    /**
     * Descarga una copia de seguridad completa. Es un POST con token CSRF para que ningún enlace (ni una precarga del navegador)
     * pueda lanzarla, y queda en el registro de actividad: la copia lleva datos personales del alumnado y hashes de contraseñas.
     * Con una contraseña, el .zip se cifra (AES-256) y solo lo abre quien la conozca.
     */
    #[Route('/copia', name: 'app_admin_system_backup', methods: ['POST'])]
    public function backup(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('system_backup', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        // Con muchos datos puede tardar más que el límite por defecto.
        set_time_limit(0);

        $password = $request->request->getString('password');
        $password = $password === '' ? null : $password;

        $directory = sys_get_temp_dir();
        $name      = 'nexo-fp-backup-' . bin2hex(random_bytes(6)) . '.zip';

        try {
            $result = $this->backup->create($directory, $name, $password);
        } catch (\Throwable $e) {
            $this->addFlash('error', $this->translator->trans('system.backup.error', ['%reason%' => $e->getMessage()], 'admin'));

            return $this->redirectToRoute('app_admin_system');
        }

        $this->activityLog->log('backup.downloaded', [
            'version'   => $this->appVersion,
            'tables'    => $result->tableCount(),
            'rows'      => $result->totalRows(),
            'encrypted' => $result->encrypted,
        ]);

        $response = new BinaryFileResponse($result->path);
        $response->deleteFileAfterSend(true);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            \sprintf('%s-%s.zip', BackupLibrary::PREFIX, $this->clock->now()->format('Ymd-Hi')),
        );
        $response->headers->set('Content-Type', 'application/zip');
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    /** Guarda una copia de los datos actuales en la carpeta de copias del servidor. */
    #[Route('/copias/guardar', name: 'app_admin_system_backup_save', methods: ['POST'])]
    public function save(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('system_backup_save', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        set_time_limit(0);

        try {
            $stored = $this->library->save();
        } catch (\Throwable $e) {
            $this->addFlash('error', $this->translator->trans('system.backup.error', ['%reason%' => $e->getMessage()], 'admin'));

            return $this->redirectToRoute('app_admin_system');
        }

        $manifest = $stored->manifest;
        $this->activityLog->log('backup.saved', [
            'version' => $this->appVersion,
            'tables'  => $manifest?->tableCount() ?? 0,
            'rows'    => $manifest?->totalRows() ?? 0,
        ]);
        $this->addFlash('success', $this->translator->trans('system.copies.saved', ['%name%' => $stored->name], 'admin'));

        return $this->redirectToRoute('app_admin_system');
    }

    /**
     * Descarga una copia guardada en el servidor (para llevarla a otra máquina). Mismas garantías que la descarga de una copia nueva:
     * POST con token CSRF, anotada en el registro de actividad y sin caché; la copia se localiza por su nombre exacto en la carpeta.
     */
    #[Route('/copias/{name}/descargar', name: 'app_admin_system_backup_download_stored', methods: ['POST'], requirements: ['name' => '[^/]+'])]
    public function downloadStored(string $name, Request $request): Response
    {
        $stored = $this->requireCopy($name);
        if (!$this->isCsrfTokenValid('system_backup_download_' . $stored->name, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $manifest = $stored->manifest;
        $this->activityLog->log('backup.downloaded', [
            'version' => $manifest->appVersion ?? $this->appVersion,
            'tables'  => $manifest?->tableCount() ?? 0,
            'rows'    => $manifest?->totalRows() ?? 0,
        ]);

        $response = new BinaryFileResponse($this->library->path($stored));
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $stored->name);
        $response->headers->set('Content-Type', 'application/zip');
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    #[Route('/copias/{name}/eliminar', name: 'app_admin_system_backup_delete', methods: ['POST'], requirements: ['name' => '[^/]+'])]
    public function delete(string $name, Request $request): Response
    {
        $stored = $this->requireCopy($name);
        if (!$this->isCsrfTokenValid('system_backup_delete_' . $stored->name, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $this->library->delete($stored->name);
        $this->addFlash('success', $this->translator->trans('system.copies.deleted', ['%name%' => $stored->name], 'admin'));

        return $this->redirectToRoute('app_admin_system');
    }

    private function requireCopy(string $name): StoredBackup
    {
        return $this->library->find($name) ?? throw $this->createNotFoundException();
    }
}
