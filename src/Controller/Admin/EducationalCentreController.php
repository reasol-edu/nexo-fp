<?php

namespace App\Controller\Admin;

use App\Entity\AcademicYear;
use App\Entity\EducationalCentre;
use App\Repository\AcademicYearRepository;
use App\Repository\EducationalCentreRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/centros')]
#[IsGranted('ROLE_ADMIN')]
class EducationalCentreController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EducationalCentreRepository $centres,
        private readonly AcademicYearRepository $years,
    ) {}

    #[Route('', name: 'app_admin_centres_index')]
    public function index(): Response
    {
        return $this->render('admin/educational_centre/index.html.twig', [
            'centres' => $this->centres->findAllWithActiveYear(),
        ]);
    }

    #[Route('/nuevo', name: 'app_admin_centres_new')]
    public function new(Request $request): Response
    {
        $errors = [];
        $values = ['code' => '', 'name' => '', 'city' => ''];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('new_centre', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $values = [
                'code' => trim($request->request->getString('code')),
                'name' => trim($request->request->getString('name')),
                'city' => trim($request->request->getString('city')),
            ];

            $errors = $this->validateCentre($values);

            if (empty($errors) && $this->centres->findByCode($values['code']) !== null) {
                $errors['code'] = 'Ya existe un centro con este código.';
            }

            if (empty($errors)) {
                $centre = (new EducationalCentre())
                    ->setCode($values['code'])
                    ->setName($values['name'])
                    ->setCity($values['city']);

                $year = (int) (new \DateTimeImmutable())->format('Y');
                $academicYear = (new AcademicYear())
                    ->setName($year . '-' . ($year + 1))
                    ->setEducationalCentre($centre);
                $centre->setActiveAcademicYear($academicYear);

                $this->em->persist($centre);
                $this->em->persist($academicYear);
                $this->em->flush();

                $this->addFlash('success', 'Centro educativo creado correctamente.');

                return $this->redirectToRoute('app_admin_centres_index');
            }
        }

        return $this->render('admin/educational_centre/new.html.twig', [
            'errors' => $errors,
            'values' => $values,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_centres_edit')]
    public function edit(string $id, Request $request): Response
    {
        $centre = $this->centres->findByIdWithActiveYear($id);
        if ($centre === null) {
            throw $this->createNotFoundException();
        }

        $errors = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('edit_centre_' . $id, $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $values = [
                'code' => trim($request->request->getString('code')),
                'name' => trim($request->request->getString('name')),
                'city' => trim($request->request->getString('city')),
            ];

            $errors = $this->validateCentre($values);

            $existing = $this->centres->findByCode($values['code']);
            if (empty($errors['code']) && $existing !== null
                && $existing->getId()->toRfc4122() !== $id) {
                $errors['code'] = 'Ya existe un centro con este código.';
            }

            if (empty($errors)) {
                $centre->setCode($values['code'])
                    ->setName($values['name'])
                    ->setCity($values['city']);

                $this->em->flush();

                $this->addFlash('success', 'Centro educativo guardado correctamente.');

                return $this->redirectToRoute('app_admin_centres_edit', ['id' => $id]);
            }
        }

        return $this->render('admin/educational_centre/edit.html.twig', [
            'centre' => $centre,
            'years'  => $this->years->findByCentreOrderedByName($centre),
            'errors' => $errors,
        ]);
    }

    #[Route('/{id}/eliminar', name: 'app_admin_centres_delete', methods: ['POST'])]
    public function delete(string $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('delete_centre_' . $id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $centre = $this->centres->findByIdWithActiveYear($id);
        if ($centre === null) {
            throw $this->createNotFoundException();
        }

        try {
            $centre->setActiveAcademicYear(null);
            $this->em->flush();
            foreach ($this->years->findByCentreOrderedByName($centre) as $year) {
                $this->em->remove($year);
            }
            $this->em->remove($centre);
            $this->em->flush();
            $this->addFlash('success', 'Centro educativo eliminado correctamente.');
        } catch (\Exception) {
            $this->addFlash('error', 'No se puede eliminar este centro porque tiene datos asociados.');
        }

        return $this->redirectToRoute('app_admin_centres_index');
    }

    #[Route('/{id}/cursos', name: 'app_admin_centres_year_add', methods: ['POST'])]
    public function addYear(string $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('add_year_' . $id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $centre = $this->centres->findByIdWithActiveYear($id);
        if ($centre === null) {
            throw $this->createNotFoundException();
        }

        $name = trim($request->request->getString('name'));

        if ($name === '') {
            $this->addFlash('error', 'El nombre del curso es obligatorio.');
        } else {
            $year = (new AcademicYear())
                ->setName($name)
                ->setEducationalCentre($centre);

            $this->em->persist($year);
            $this->em->flush();

            $this->addFlash('success', 'Curso académico añadido correctamente.');
        }

        return $this->redirectToRoute('app_admin_centres_edit', ['id' => $id]);
    }

    #[Route('/{centreId}/cursos/{yearId}', name: 'app_admin_centres_year_edit')]
    public function editYear(string $centreId, string $yearId, Request $request): Response
    {
        $centre = $this->centres->findByIdWithActiveYear($centreId);
        if ($centre === null) {
            throw $this->createNotFoundException();
        }

        $year = $this->years->findByCentreAndId($centre, $yearId);
        if ($year === null) {
            throw $this->createNotFoundException();
        }

        $errors = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('edit_year_' . $yearId, $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $name = trim($request->request->getString('name'));

            if ($name === '') {
                $errors['name'] = 'El nombre es obligatorio.';
            } else {
                $year->setName($name);
                $this->em->flush();

                $this->addFlash('success', 'Curso académico guardado correctamente.');

                return $this->redirectToRoute('app_admin_centres_edit', ['id' => $centreId]);
            }
        }

        return $this->render('admin/educational_centre/edit_year.html.twig', [
            'centre' => $centre,
            'year'   => $year,
            'errors' => $errors,
        ]);
    }

    #[Route('/{centreId}/cursos/{yearId}/eliminar', name: 'app_admin_centres_year_delete', methods: ['POST'])]
    public function deleteYear(string $centreId, string $yearId, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('delete_year_' . $yearId, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $centre = $this->centres->findByIdWithActiveYear($centreId);
        if ($centre === null) {
            throw $this->createNotFoundException();
        }

        $year = $this->years->findByCentreAndId($centre, $yearId);
        if ($year === null) {
            throw $this->createNotFoundException();
        }

        if ($centre->getActiveAcademicYear() === $year) {
            $this->addFlash('error', 'No puedes eliminar el curso activo. Establece otro como activo primero.');
        } else {
            $this->em->remove($year);
            $this->em->flush();
            $this->addFlash('success', 'Curso académico eliminado correctamente.');
        }

        return $this->redirectToRoute('app_admin_centres_edit', ['id' => $centreId]);
    }

    #[Route('/{centreId}/cursos/{yearId}/activar', name: 'app_admin_centres_year_activate', methods: ['POST'])]
    public function activateYear(string $centreId, string $yearId, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('activate_year_' . $yearId, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $centre = $this->centres->findByIdWithActiveYear($centreId);
        if ($centre === null) {
            throw $this->createNotFoundException();
        }

        $year = $this->years->findByCentreAndId($centre, $yearId);
        if ($year === null) {
            throw $this->createNotFoundException();
        }

        $centre->setActiveAcademicYear($year);
        $this->em->flush();

        $this->addFlash('success', 'Curso académico establecido como activo.');

        return $this->redirectToRoute('app_admin_centres_edit', ['id' => $centreId]);
    }

    /**
     * @param  array<string, string> $values
     * @return array<string, string>
     */
    private function validateCentre(array $values): array
    {
        $errors = [];

        if ($values['code'] === '') {
            $errors['code'] = 'El código es obligatorio.';
        } elseif (\strlen($values['code']) > 8) {
            $errors['code'] = 'El código no puede tener más de 8 caracteres.';
        }

        if ($values['name'] === '') {
            $errors['name'] = 'El nombre es obligatorio.';
        }

        if ($values['city'] === '') {
            $errors['city'] = 'La localidad es obligatoria.';
        }

        return $errors;
    }
}
