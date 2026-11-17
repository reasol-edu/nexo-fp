<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller\Admin;

use App\Entity\ActivityLog;
use App\Entity\EducationalCentre;
use App\Entity\PersonName;
use App\Entity\Teacher;
use App\Service\Backup\BackupLibrary;
use App\Tests\Integration\ControllerTestCase;
use App\Tests\Support\CleansSystemFiles;

/** Copias de seguridad desde el panel de administración: descargar una nueva, guardarla en el servidor y descargar o borrar las guardadas. */
final class SystemControllerTest extends ControllerTestCase
{
    use CleansSystemFiles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanSystemFiles();
    }

    protected function tearDown(): void
    {
        $this->cleanSystemFiles();

        parent::tearDown();
    }

    private function loginAsGlobalAdmin(): void
    {
        $admin = (new Teacher(new PersonName('Admin', 'Sys')))->setUsername('admin.system')->setAdmin(true);
        $this->persist($admin);
        $this->loginAs($admin);
    }

    private function library(): BackupLibrary
    {
        /** @var BackupLibrary $library */
        $library = self::getContainer()->get(BackupLibrary::class);

        return $library;
    }

    /**
     * Ejecuta el test con el registro de actividad activado (en los tests está desactivado) y devuelve las acciones anotadas.
     *
     * @param \Closure(): void $test
     *
     * @return list<string>
     */
    private function loggedActions(\Closure $test): array
    {
        putenv('APP_LOG=true');
        $_ENV['APP_LOG']    = 'true';
        $_SERVER['APP_LOG'] = 'true';

        try {
            $test();
        } finally {
            putenv('APP_LOG=false');
            $_ENV['APP_LOG']    = 'false';
            $_SERVER['APP_LOG'] = 'false';
        }

        $this->em->clear();

        return array_values(array_map(static fn (ActivityLog $log): string => $log->getActionType(), $this->em->getRepository(ActivityLog::class)->findAll()));
    }

    /** El token CSRF del formulario de la página cuya acción termina así: el que usaría el botón. */
    private function token(string $actionSuffix): string
    {
        $crawler = $this->client->request('GET', '/admin/sistema');
        $field   = $crawler->filter(\sprintf('form[action$="%s"] input[name="_token"]', $actionSuffix));
        self::assertCount(1, $field, $actionSuffix);

        return (string) $field->attr('value');
    }

    // ── acceso ────────────────────────────────────────────────────────────────

    public function testAnonymousUsersAreRedirectedToLogin(): void
    {
        $this->client->request('GET', '/admin/sistema');
        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $this->client->getResponse()->headers->get('Location'));

        $this->client->request('POST', '/admin/sistema/copia');
        self::assertResponseRedirects();
    }

    public function testACentreAdminWithoutTheGlobalRoleIsForbidden(): void
    {
        $centre  = (new EducationalCentre())->setCode('41000777')->setName('IES')->setCity('Sevilla');
        $teacher = (new Teacher(new PersonName('Cen', 'Admin')))->setUsername('centre.admin.sys');
        $centre->addAdmin($teacher);
        $this->persist($centre, $teacher);
        $this->loginAs($teacher);

        foreach (['GET /admin/sistema', 'POST /admin/sistema/copia', 'POST /admin/sistema/copias/guardar', 'POST /admin/sistema/copias/x.zip/descargar', 'POST /admin/sistema/copias/x.zip/eliminar'] as $route) {
            [$method, $path] = explode(' ', $route);
            $this->client->request($method, $path, ['_token' => 'x']);
            self::assertResponseStatusCodeSame(403, $route);
        }
    }

    public function testTheDashboardLinksToThePage(): void
    {
        $this->loginAsGlobalAdmin();

        $this->client->request('GET', '/admin');

        self::assertSelectorExists('a[href="/admin/sistema"]');
        self::assertSelectorExists('a[href="/admin/estado"]');
    }

    // ── página ────────────────────────────────────────────────────────────────

    public function testThePageOffersTheBackupToAnAdmin(): void
    {
        $this->loginAsGlobalAdmin();

        $crawler = $this->client->request('GET', '/admin/sistema');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Copias de seguridad');
        self::assertCount(1, $crawler->filter('form[action$="/admin/sistema/copia"] input[name="_token"]'));
        self::assertCount(1, $crawler->filter('form[action$="/admin/sistema/copia"] input[name="password"]'));
        self::assertCount(1, $crawler->filter('form[action$="/admin/sistema/copias/guardar"] input[name="_token"]'));
    }

    public function testThePageAndTheDashboardWarnWhenThereIsNoBackupAndExplainTheAutomaticOnes(): void
    {
        $this->loginAsGlobalAdmin();

        $this->client->request('GET', '/admin/sistema');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'No hay ninguna copia de seguridad guardada en el servidor');
        self::assertSelectorTextContains('main', 'Copias automáticas desactivadas');
        self::assertSelectorExists('main a[href$="/admin/ajustes"]');

        $this->client->request('GET', '/admin');
        self::assertSelectorTextContains('main', 'No hay ninguna copia de seguridad guardada en el servidor');
    }

    // ── descargar una copia nueva ─────────────────────────────────────────────

    public function testDownloadingABackupReturnsAZipAndIsLogged(): void
    {
        $actions = $this->loggedActions(function (): void {
            $this->loginAsGlobalAdmin();
            $this->client->request('POST', '/admin/sistema/copia', ['_token' => $this->token('/admin/sistema/copia')]);
        });

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('application/zip', (string) $this->client->getResponse()->headers->get('Content-Type'));
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertMatchesRegularExpression('/nexo-fp-backup-\d{8}-\d{4}\.zip/', (string) $this->client->getResponse()->headers->get('Content-Disposition'));

        // El contenido es un .zip legible con el manifiesto de Nexo FP.
        $file = tempnam(sys_get_temp_dir(), 'nexo-test-');
        self::assertIsString($file);
        file_put_contents($file, $this->client->getInternalResponse()->getContent());
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($file));
        self::assertNotFalse($zip->getFromName('manifest.json'));
        $zip->close();
        @unlink($file);

        self::assertSame(['backup.downloaded'], $actions);
    }

    public function testDownloadingWithAPasswordEncryptsTheZip(): void
    {
        $this->loginAsGlobalAdmin();

        $this->client->request('POST', '/admin/sistema/copia', ['_token' => $this->token('/admin/sistema/copia'), 'password' => 'una-clave']);
        self::assertResponseIsSuccessful();

        $file = tempnam(sys_get_temp_dir(), 'nexo-test-');
        self::assertIsString($file);
        file_put_contents($file, $this->client->getInternalResponse()->getContent());
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($file));
        self::assertFalse($zip->getFromName('manifest.json'), 'sin la contraseña no se puede leer');
        $zip->setPassword('una-clave');
        self::assertNotFalse($zip->getFromName('manifest.json'));
        $zip->close();
        @unlink($file);
    }

    public function testDownloadingRequiresAValidToken(): void
    {
        $this->loginAsGlobalAdmin();

        $this->client->request('POST', '/admin/sistema/copia', ['_token' => 'falso']);

        self::assertResponseStatusCodeSame(403);
    }

    public function testDownloadingIsNotAvailableWithGet(): void
    {
        $this->loginAsGlobalAdmin();

        $this->client->request('GET', '/admin/sistema/copia');

        self::assertResponseStatusCodeSame(405);
    }

    // ── guardar en el servidor ────────────────────────────────────────────────

    public function testSavingABackupKeepsItInTheServerFolderAndListsIt(): void
    {
        $actions = $this->loggedActions(function (): void {
            $this->loginAsGlobalAdmin();
            $this->client->request('POST', '/admin/sistema/copias/guardar', ['_token' => $this->token('/admin/sistema/copias/guardar')]);
        });
        self::assertResponseRedirects('/admin/sistema');

        $copies = $this->library()->list();
        self::assertCount(1, $copies);
        self::assertFileExists($this->library()->path($copies[0]));

        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('main', $copies[0]->name);
        self::assertSelectorTextContains('main', 'Copia guardada en el servidor');
        self::assertCount(1, $crawler->filter('form[action$="/descargar"]'));
        self::assertCount(1, $crawler->filter('form[action$="/eliminar"]'));

        self::assertSame(['backup.saved'], $actions);
    }

    public function testSavingRequiresAValidToken(): void
    {
        $this->loginAsGlobalAdmin();

        $this->client->request('POST', '/admin/sistema/copias/guardar', ['_token' => 'falso']);

        self::assertResponseStatusCodeSame(403);
        self::assertSame([], $this->library()->list());
    }

    // ── descargar y borrar una copia guardada ─────────────────────────────────

    public function testAStoredBackupCanBeDownloaded(): void
    {
        $stored  = $this->library()->save();
        $actions = $this->loggedActions(function () use ($stored): void {
            $this->loginAsGlobalAdmin();
            $this->client->request('POST', '/admin/sistema/copias/' . $stored->name . '/descargar', ['_token' => $this->token('/' . $stored->name . '/descargar')]);
        });

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('application/zip', (string) $this->client->getResponse()->headers->get('Content-Type'));
        self::assertStringContainsString($stored->name, (string) $this->client->getResponse()->headers->get('Content-Disposition'));
        self::assertSame(['backup.downloaded'], $actions);
    }

    public function testDownloadingAStoredBackupRequiresAValidToken(): void
    {
        $this->loginAsGlobalAdmin();
        $stored = $this->library()->save();

        $this->client->request('POST', '/admin/sistema/copias/' . $stored->name . '/descargar', ['_token' => 'falso']);

        self::assertResponseStatusCodeSame(403);
    }

    public function testAStoredBackupCanBeDeleted(): void
    {
        $this->loginAsGlobalAdmin();
        $stored = $this->library()->save();

        $this->client->request('POST', '/admin/sistema/copias/' . $stored->name . '/eliminar', ['_token' => $this->token('/' . $stored->name . '/eliminar')]);

        self::assertResponseRedirects('/admin/sistema');
        self::assertSame([], $this->library()->list());
    }

    public function testDeletingRequiresAValidToken(): void
    {
        $this->loginAsGlobalAdmin();
        $stored = $this->library()->save();

        $this->client->request('POST', '/admin/sistema/copias/' . $stored->name . '/eliminar', ['_token' => 'falso']);

        self::assertResponseStatusCodeSame(403);
        self::assertCount(1, $this->library()->list());
    }

    public function testUnknownOrForeignNamesAreNotFound(): void
    {
        $this->loginAsGlobalAdmin();
        $this->library()->save();
        file_put_contents(sys_get_temp_dir() . '/nexo-fuera.zip', 'x');

        foreach (['no-existe.zip', 'nexo-fuera.zip', '..%2Fnexo-fuera.zip', 'notas.txt'] as $name) {
            $this->client->request('POST', '/admin/sistema/copias/' . $name . '/descargar', ['_token' => 'x']);
            self::assertResponseStatusCodeSame(404, $name);

            $this->client->request('POST', '/admin/sistema/copias/' . $name . '/eliminar', ['_token' => 'x']);
            self::assertResponseStatusCodeSame(404, $name);
        }

        self::assertFileExists(sys_get_temp_dir() . '/nexo-fuera.zip');
        @unlink(sys_get_temp_dir() . '/nexo-fuera.zip');
    }
}
