<?php

namespace App\Controller\Admin;

use App\Entity\AcademicYear;
use App\Entity\EducationalCentre;
use App\Entity\Teacher;
use App\Pagination\Paginator;
use App\Repository\AcademicYearRepository;
use App\Repository\EducationalCentreRepository;
use App\Repository\TeacherRepository;
use App\Service\AcademicYearEraser;
use App\Service\AcademicYearRollover;
use App\Service\ActivityLogService;
use App\Service\ImportOptions;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/centros')]
#[IsGranted('ROLE_ADMIN')]
class EducationalCentreController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EducationalCentreRepository $centres,
        private readonly AcademicYearRepository $years,
        private readonly TeacherRepository $teachers,
        private readonly TranslatorInterface $translator,
        private readonly ClockInterface $clock,
        private readonly AcademicYearRollover $rollover,
        private readonly AcademicYearEraser $eraser,
        private readonly ActivityLogService $activityLog,
    ) {}

    #[Route('', name: 'app_admin_centres_index')]
    public function index(): Response
    {
        return $this->render('admin/educational_centre/index.html.twig');
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
                $errors['code'] = $this->t('centre.error.code_duplicate');
            }

            if (empty($errors)) {
                $centre = (new EducationalCentre())
                    ->setCode($values['code'])
                    ->setName($values['name'])
                    ->setCity($values['city']);

                $year = (int) $this->clock->now()->format('Y');
                $academicYear = (new AcademicYear())
                    ->setName($year . '-' . ($year + 1))
                    ->setEducationalCentre($centre);
                $centre->setActiveAcademicYear($academicYear);

                $this->em->persist($centre);
                $this->em->persist($academicYear);
                $this->em->flush();

                $this->addFlash('success', $this->t('centre.flash.created'));

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

        /** @var Teacher[] $selectedAdmins */
        $selectedAdmins = $centre->getAdmins()->toArray();

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
                $errors['code'] = $this->t('centre.error.code_duplicate');
            }

            $submittedIds = array_values(array_filter(
                array_map(
                    static fn(mixed $v): string => \is_string($v) ? $v : '',
                    $request->request->all('admins')
                ),
                static fn(string $v): bool => $v !== ''
            ));

            if (!empty($errors)) {
                $selectedAdmins = [];
                foreach ($submittedIds as $adminId) {
                    $teacher = $this->teachers->findById($adminId);
                    if ($teacher !== null) {
                        $selectedAdmins[] = $teacher;
                    }
                }
            } else {
                $centre->setCode($values['code'])
                    ->setName($values['name'])
                    ->setCity($values['city']);

                foreach ($centre->getAdmins()->toArray() as $admin) {
                    $centre->removeAdmin($admin);
                }
                foreach ($submittedIds as $adminId) {
                    $teacher = $this->teachers->findById($adminId);
                    if ($teacher !== null) {
                        $centre->addAdmin($teacher);
                    }
                }

                $this->em->flush();

                $this->addFlash('success', $this->t('centre.flash.saved'));

                return $this->redirectToRoute('app_admin_centres_edit', ['id' => $id]);
            }
        }

        return $this->render('admin/educational_centre/edit.html.twig', [
            'centre'         => $centre,
            'years'          => $this->years->findByCentreOrderedByName($centre),
            'errors'         => $errors,
            'selectedAdmins' => $selectedAdmins,
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
            $this->addFlash('success', $this->t('centre.flash.deleted'));
        } catch (\Exception) {
            $this->addFlash('error', $this->t('centre.flash.delete_error'));
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
            $this->addFlash('error', $this->t('year.flash.name_required'));
        } else {
            $year = (new AcademicYear())
                ->setName($name)
                ->setEducationalCentre($centre);

            $this->em->persist($year);
            $this->em->flush();

            $this->addFlash('success', $this->t('year.flash.added'));
        }

        return $this->redirectToRoute('app_admin_centres_edit', ['id' => $id]);
    }

    /** Asistente para preparar el curso siguiente a partir del activo (o del último curso del centro). */
    #[Route('/{centreId}/siguiente-curso', name: 'app_admin_centres_year_rollover')]
    public function rollOverYear(string $centreId, Request $request): Response
    {
        $centre = $this->centres->findByIdWithActiveYear($centreId);
        if ($centre === null) {
            throw $this->createNotFoundException();
        }

        $source = $centre->getActiveAcademicYear();
        if ($source === null) {
            $this->addFlash('error', $this->t('year.rollover.no_source'));

            return $this->redirectToRoute('app_admin_centres_edit', ['id' => $centreId]);
        }

        $errors = [];
        $values = [
            'name'          => $this->rollover->suggestName($source),
            'copy_offer'    => true,
            'copy_heads'    => true,
            'copy_tutors'   => true,
            'copy_teachers' => true,
            'copy_members'  => true,
            'activate'      => false,
        ];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('rollover_year_' . $centreId, $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $values = [
                'name'          => trim($request->request->getString('name')),
                'copy_offer'    => $request->request->has('copy_offer'),
                'copy_heads'    => $request->request->has('copy_heads'),
                'copy_tutors'   => $request->request->has('copy_tutors'),
                'copy_teachers' => $request->request->has('copy_teachers'),
                'copy_members'  => $request->request->has('copy_members'),
                'activate'      => $request->request->has('activate'),
            ];

            if ($values['name'] === '') {
                $errors['name'] = $this->t('year.error.name_required');
            } elseif ($this->years->existsByCentreAndName($centre, $values['name'])) {
                $errors['name'] = $this->t('year.rollover.name_taken');
            }

            if ($errors === []) {
                $result = $this->rollover->rollOver(
                    $source,
                    $values['name'],
                    $values['copy_offer'],
                    new ImportOptions(
                        importHeads: $values['copy_heads'],
                        importTutors: $values['copy_tutors'],
                        importTeachers: $values['copy_teachers'],
                    ),
                    $values['copy_members'],
                    $values['activate'],
                );

                $offer = $result['offer'];
                $this->addFlash('success', $this->translator->trans('year.rollover.done', [
                    '%year%'     => $result['year']->getName(),
                    '%families%' => $offer['families'] ?? 0,
                    '%groups%'   => $offer['groups'] ?? 0,
                    '%teachers%' => $result['teachers'],
                ], 'admin'));

                return $this->redirectToRoute('app_admin_centres_edit', ['id' => $centreId]);
            }
        }

        return $this->render('admin/educational_centre/rollover_year.html.twig', [
            'centre'  => $centre,
            'source'  => $source,
            'preview' => $this->rollover->preview($source),
            'errors'  => $errors,
            'values'  => $values,
        ]);
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
                $errors['name'] = $this->t('year.error.name_required');
            } else {
                $year->setName($name);
                $this->em->flush();

                $this->addFlash('success', $this->t('year.flash.saved'));

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

        $content = $this->years->countContent($year);

        if ($centre->getActiveAcademicYear() === $year) {
            $this->addFlash('error', $this->t('year.flash.delete_active_error'));
        } elseif (array_sum($content) > 0) {
            // Con oferta formativa o estancias se pide una confirmación explícita: se perdería ese historial.
            return $this->redirectToRoute('app_admin_centres_year_delete_confirm', ['centreId' => $centreId, 'yearId' => $yearId]);
        } else {
            try {
                $name = $year->getName();
                $this->em->remove($year);
                $this->em->flush();
                $this->logYearDeletion($centre, $name, $content);
                $this->addFlash('success', $this->t('year.flash.deleted'));
            } catch (ForeignKeyConstraintViolationException) {
                // Algún dato no previsto referencia el curso: no se elimina, en lugar de fallar con un 500.
                $this->addFlash('error', $this->t('year.flash.delete_related_error'));
            }
        }

        return $this->redirectToRoute('app_admin_centres_edit', ['id' => $centreId]);
    }

    /**
     * Confirmación explícita para eliminar un curso con datos: muestra lo que se perdería y exige escribir
     * el nombre del curso.
     */
    #[Route('/{centreId}/cursos/{yearId}/eliminar-con-datos', name: 'app_admin_centres_year_delete_confirm')]
    public function confirmDeleteYear(string $centreId, string $yearId, Request $request): Response
    {
        $centre = $this->centres->findByIdWithActiveYear($centreId);
        $year   = $centre !== null ? $this->years->findByCentreAndId($centre, $yearId) : null;
        if ($centre === null || $year === null) {
            throw $this->createNotFoundException();
        }

        if ($centre->getActiveAcademicYear() === $year) {
            $this->addFlash('error', $this->t('year.flash.delete_active_error'));

            return $this->redirectToRoute('app_admin_centres_edit', ['id' => $centreId]);
        }

        $errors = [];
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('delete_year_data_' . $yearId, $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }

            if (trim($request->request->getString('confirm_name')) !== $year->getName()) {
                $errors['confirm_name'] = $this->t('year.delete_confirm_page.name_mismatch');
            } else {
                $name    = $year->getName();
                $content = $this->years->countContent($year);
                try {
                    $this->eraser->erase($year);
                    $this->logYearDeletion($centre, $name, $content);
                    $this->addFlash('success', $this->translator->trans('year.flash.deleted_with_data', ['%year%' => $name], 'admin'));
                } catch (ForeignKeyConstraintViolationException) {
                    $this->addFlash('error', $this->t('year.flash.delete_related_error'));
                }

                return $this->redirectToRoute('app_admin_centres_edit', ['id' => $centreId]);
            }
        }

        return $this->render('admin/educational_centre/delete_year.html.twig', [
            'centre'  => $centre,
            'year'    => $year,
            'content' => $this->years->countContent($year),
            'errors'  => $errors,
        ]);
    }

    /**
     * Deja constancia en el registro de actividad de la eliminación de un curso (acción irreversible).
     *
     * @param array<string, int> $content
     */
    private function logYearDeletion(EducationalCentre $centre, string $yearName, array $content): void
    {
        $this->activityLog->log('year.deleted', [
            'centre'  => $centre->getName(),
            'year'    => $yearName,
            'deleted' => $content,
        ]);
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

        $this->addFlash('success', $this->t('year.flash.activated'));

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
            $errors['code'] = $this->t('centre.error.code_required');
        } elseif (\strlen($values['code']) > 8) {
            $errors['code'] = $this->t('centre.error.code_too_long');
        }

        if ($values['name'] === '') {
            $errors['name'] = $this->t('centre.error.name_required');
        }

        if ($values['city'] === '') {
            $errors['city'] = $this->t('centre.error.city_required');
        }

        return $errors;
    }

    private function t(string $key): string
    {
        return $this->translator->trans($key, [], 'admin');
    }
}
