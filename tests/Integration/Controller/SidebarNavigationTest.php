<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Entity\EducationalCentre;
use App\Entity\PersonName;
use App\Entity\Teacher;
use App\Tests\Integration\ControllerTestCase;

/**
 * Barra lateral: la sección actual se marca con aria-current y con la franja de acento.
 */
class SidebarNavigationTest extends ControllerTestCase
{
    public function testTheCurrentSectionIsMarkedWithAriaCurrentAndTheAccentBar(): void
    {
        $crawler = $this->openAs('/empresas');

        $active = $crawler->filter('aside nav a[aria-current="page"]');
        self::assertCount(1, $active, 'solo una sección marcada como actual');
        self::assertSame('/empresas', $active->attr('href'));
        self::assertStringContainsString('border-plum-300', (string) $active->attr('class'));

        // Las demás conservan la franja transparente para que el texto no se desplace al activarse.
        foreach ($crawler->filter('aside nav a:not([aria-current])') as $link) {
            self::assertStringContainsString('border-transparent', (string) $link->getAttribute('class'));
        }
    }

    public function testTheLogoLinksToTheDashboard(): void
    {
        $crawler = $this->openAs('/empresas');

        self::assertSame('/', $crawler->filter('aside a:has(img)')->first()->attr('href'));
    }

    public function testSectionPagesStartTheirBreadcrumbWithAHomeLink(): void
    {
        $crawler = $this->openAs('/empresas/importar');

        $home = $crawler->filter('main a[aria-label="Inicio"], .max-w-2xl a[aria-label="Inicio"]');
        self::assertGreaterThanOrEqual(1, $home->count());
        self::assertSame('/', $home->first()->attr('href'));
    }

    private function openAs(string $path): \Symfony\Component\DomCrawler\Crawler
    {
        $admin  = (new Teacher(new PersonName('Admin', 'Menú')))->setUsername('admin.menu')->setAdmin(true);
        $centre = (new EducationalCentre())->setCode('41000555')->setName('IES Menú')->setCity('Sevilla');
        $this->persist($admin, $centre);
        $this->loginAs($admin, $centre);

        return $this->client->request('GET', $path);
    }
}
