<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Entity\AcademicYear;
use App\Entity\EducationalCentre;
use App\Entity\PersonName;
use App\Entity\Teacher;
use App\Tests\Integration\ControllerTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class YearSelectionControllerTest extends ControllerTestCase
{
    /** @return iterable<string, array{string}> */
    public static function provideUnsafeReturnPaths(): iterable
    {
        yield 'ruta relativa al protocolo' => ['//evil.example/phish'];
        yield 'barra invertida' => ['/\\evil.example'];
        yield 'otro esquema' => ['javascript:alert(1)'];
        yield 'URL absoluta' => ['https://evil.example/'];
        yield 'salto de línea' => ["/ok\r\nSet-Cookie: x=1"];
    }

    #[DataProvider('provideUnsafeReturnPaths')]
    public function testSelectYearNeverRedirectsOutsideTheApplication(string $returnTo): void
    {
        [, $pastYear] = $this->setUpAdminViewingCentre();

        $crawler = $this->client->request('GET', '/curso/año');
        $token   = $crawler->filter('form[action*="' . $pastYear->getId()->toRfc4122() . '"] input[name="_token"]')->attr('value');

        $this->client->request('POST', '/curso/año/' . $pastYear->getId()->toRfc4122(), [
            '_token'     => $token,
            '_return_to' => $returnTo,
        ]);

        self::assertResponseRedirects();
        $location = (string) $this->client->getResponse()->headers->get('Location');
        self::assertStringStartsWith('/', $location);
        self::assertStringNotContainsString('evil.example', $location);
        self::assertStringNotContainsString("\n", $location);
    }

    public function testSelectYearRedirectsBackToALocalPath(): void
    {
        [, $pastYear] = $this->setUpAdminViewingCentre();

        $crawler = $this->client->request('GET', '/curso/año');
        $token   = $crawler->filter('form[action*="' . $pastYear->getId()->toRfc4122() . '"] input[name="_token"]')->attr('value');

        $this->client->request('POST', '/curso/año/' . $pastYear->getId()->toRfc4122(), [
            '_token'     => $token,
            '_return_to' => '/estancias?page=2',
        ]);

        self::assertResponseRedirects('/estancias?page=2');
    }

    public function testYearPageDoesNotRenderAnUnsafeReturnLink(): void
    {
        $this->setUpAdminViewingCentre();

        $crawler = $this->client->request('GET', '/curso/año?return_to=' . rawurlencode('javascript:alert(1)'));

        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('javascript:', (string) $this->client->getResponse()->getContent());
        self::assertSame(0, $crawler->filter('a[href^="javascript:"]')->count());
    }

    /** @return array{0: EducationalCentre, 1: AcademicYear} */
    public function testSwitchingYearFromAPageThatDoesNotExistThereGoesToTheDashboardWithANotice(): void
    {
        [$centre, $pastYear] = $this->setUpAdminViewingCentre();
        $active    = $centre->getActiveAcademicYear();
        $family    = (new \App\Entity\ProfessionalFamily())->setName('Informática')->setAcademicYear($active);
        $programme = (new \App\Entity\Programme())->setName('DAW')->setProfessionalFamily($family)->setAcademicYear($active);
        $stay      = (new \App\Entity\Stay())->setName('Estancia actual')->setAcademicYear($active)->addProgramme($programme)
            ->setStartDate(new \DateTimeImmutable('2026-03-01'))->setEndDate(new \DateTimeImmutable('2026-06-30'));
        $this->persist($family, $programme, $stay);
        $stayPath = '/estancias/' . $stay->getId()->toRfc4122();

        $crawler = $this->client->request('GET', '/curso/año');
        $token   = $crawler->filter('form[action*="' . $pastYear->getId()->toRfc4122() . '"] input[name="_token"]')->attr('value');
        $this->client->request('POST', '/curso/año/' . $pastYear->getId()->toRfc4122(), ['_token' => $token, '_return_to' => $stayPath]);
        self::assertResponseRedirects($stayPath);

        // En el curso anterior esa estancia no existe (404): se sale al inicio con un aviso, sin mostrar el error.
        $this->client->followRedirect();
        self::assertResponseRedirects('/');
        $crawler = $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Esa página no está disponible en el curso académico seleccionado', $crawler->text());

        // El aviso vale solo para esa primera petición: un 404 posterior se muestra como siempre.
        $this->client->request('GET', $stayPath);
        self::assertResponseStatusCodeSame(404);
    }

    public function testSwitchingYearToAForbiddenPageAlsoGoesToTheDashboard(): void
    {
        [$centre, $pastYear] = $this->setUpAdminViewingCentre();
        $director = (new Teacher(new PersonName('Dir', 'Centro')))->setUsername('director.years');
        $this->persist($director);
        $centre->addAdmin($director);
        $this->flush();
        $this->loginAs($director, $centre);

        $crawler = $this->client->request('GET', '/curso/año');
        $token   = $crawler->filter('form[action*="' . $pastYear->getId()->toRfc4122() . '"] input[name="_token"]')->attr('value');
        // /admin solo es para administradores globales: 403 para la dirección del centro.
        $this->client->request('POST', '/curso/año/' . $pastYear->getId()->toRfc4122(), ['_token' => $token, '_return_to' => '/admin']);
        $this->client->followRedirect();

        self::assertResponseRedirects('/');
    }

    private function setUpAdminViewingCentre(): array
    {
        $admin      = (new Teacher(new PersonName('Admin', 'Cursos')))->setUsername('admin.years')->setAdmin(true);
        $centre     = (new EducationalCentre())->setCode('41000777')->setName('IES Cursos')->setCity('Sevilla');
        $activeYear = (new AcademicYear())->setName('2025-2026')->setEducationalCentre($centre);
        $pastYear   = (new AcademicYear())->setName('2024-2025')->setEducationalCentre($centre);

        $this->persist($admin, $centre, $activeYear, $pastYear);
        $centre->setActiveAcademicYear($activeYear);
        $centre->addAdmin($admin);
        $this->flush();
        $this->loginAs($admin, $centre);

        return [$centre, $pastYear];
    }
}
