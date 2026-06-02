<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\EducationalCentre;
use App\Entity\Group;
use App\Entity\PersonName;
use App\Entity\Student;
use App\Repository\EducationalCentreRepository;
use App\Repository\GroupRepository;
use App\Repository\StudentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/centros/{centreId}/estudiantes')]
#[IsGranted('ROLE_ADMIN')]
class StudentController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EducationalCentreRepository $centres,
        private readonly StudentRepository $students,
        private readonly GroupRepository $groups,
        private readonly TranslatorInterface $translator,
    ) {}

    #[Route('', name: 'app_admin_students_index')]
    public function index(string $centreId): Response
    {
        $centre = $this->requireCentre($centreId);

        return $this->render('admin/student/index.html.twig', ['centre' => $centre]);
    }

    #[Route('/nuevo', name: 'app_admin_students_new')]
    public function new(string $centreId, Request $request): Response
    {
        $centre = $this->requireCentreWithActiveYear($centreId);
        $errors = [];
        $values = ['firstName' => '', 'lastName' => '', 'studentId' => '', 'details' => ''];
        $selectedGroupIds = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('new_student', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $values = [
                'firstName' => trim($request->request->getString('firstName')),
                'lastName'  => trim($request->request->getString('lastName')),
                'studentId' => trim($request->request->getString('studentId')),
                'details'   => trim($request->request->getString('details')),
            ];
            $selectedGroupIds = $request->request->all('groups');

            if ($values['firstName'] === '') {
                $errors['firstName'] = $this->t('student.error.first_name_required');
            }
            if ($values['lastName'] === '') {
                $errors['lastName'] = $this->t('student.error.last_name_required');
            }
            if ($values['studentId'] === '') {
                $errors['studentId'] = $this->t('student.error.student_id_required');
            } elseif ($this->students->findByStudentId($values['studentId']) !== null) {
                $errors['studentId'] = $this->t('student.error.student_id_duplicate');
            }

            if (empty($errors)) {
                $student = new Student(new PersonName($values['firstName'], $values['lastName']));
                $student->setStudentId($values['studentId'])
                        ->setDetails($values['details'] !== '' ? $values['details'] : null);

                $centreGroupsById = $this->indexGroupsById($centre);
                foreach ($selectedGroupIds as $groupId) {
                    if (isset($centreGroupsById[$groupId])) {
                        $student->addGroup($centreGroupsById[$groupId]);
                    }
                }

                $this->em->persist($student);
                $this->em->flush();

                $this->addFlash('success', $this->t('student.flash.created'));

                return $this->redirectToRoute('app_admin_students_index', ['centreId' => $centre->getId()]);
            }
        }

        return $this->render('admin/student/new.html.twig', [
            'centre'           => $centre,
            'errors'           => $errors,
            'values'           => $values,
            'availableGroups'  => $this->groups->findByActiveYearOfCentreOrderedByName($centre),
            'selectedGroupIds' => $selectedGroupIds,
        ]);
    }

    #[Route('/{id}/editar', name: 'app_admin_students_edit')]
    public function edit(string $centreId, string $id, Request $request): Response
    {
        $centre  = $this->requireCentre($centreId);
        $student = $this->requireStudent($id);

        $centreGroupsById = $this->indexGroupsById($centre);

        $errors = [];
        $values = [
            'firstName' => $student->getName()->getFirstName(),
            'lastName'  => $student->getName()->getLastName(),
            'studentId' => $student->getStudentId(),
            'details'   => $student->getDetails() ?? '',
        ];

        $selectedGroupIds = [];
        foreach ($centreGroupsById as $gId => $group) {
            if ($student->getGroups()->contains($group)) {
                $selectedGroupIds[] = $gId;
            }
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('edit_student_' . $student->getId(), $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $values = [
                'firstName' => trim($request->request->getString('firstName')),
                'lastName'  => trim($request->request->getString('lastName')),
                'studentId' => trim($request->request->getString('studentId')),
                'details'   => trim($request->request->getString('details')),
            ];
            $selectedGroupIds = $request->request->all('groups');

            if ($values['firstName'] === '') {
                $errors['firstName'] = $this->t('student.error.first_name_required');
            }
            if ($values['lastName'] === '') {
                $errors['lastName'] = $this->t('student.error.last_name_required');
            }
            if ($values['studentId'] === '') {
                $errors['studentId'] = $this->t('student.error.student_id_required');
            } else {
                $existing = $this->students->findByStudentId($values['studentId']);
                if ($existing !== null && !$existing->getId()->equals($student->getId())) {
                    $errors['studentId'] = $this->t('student.error.student_id_duplicate');
                }
            }

            if (empty($errors)) {
                $student->setName(new PersonName($values['firstName'], $values['lastName']))
                        ->setStudentId($values['studentId'])
                        ->setDetails($values['details'] !== '' ? $values['details'] : null);

                foreach ($student->getGroups()->toArray() as $group) {
                    if (isset($centreGroupsById[$group->getId()->toRfc4122()])) {
                        $student->removeGroup($group);
                    }
                }
                foreach ($selectedGroupIds as $groupId) {
                    if (isset($centreGroupsById[$groupId])) {
                        $student->addGroup($centreGroupsById[$groupId]);
                    }
                }

                $this->em->flush();

                $this->addFlash('success', $this->t('student.flash.saved'));

                return $this->redirectToRoute('app_admin_students_index', ['centreId' => $centre->getId()]);
            }
        }

        return $this->render('admin/student/edit.html.twig', [
            'centre'           => $centre,
            'student'          => $student,
            'errors'           => $errors,
            'values'           => $values,
            'availableGroups'  => array_values($centreGroupsById),
            'selectedGroupIds' => $selectedGroupIds,
        ]);
    }

    #[Route('/{id}/eliminar', name: 'app_admin_students_delete', methods: ['POST'])]
    public function delete(string $centreId, string $id, Request $request): Response
    {
        $centre  = $this->requireCentre($centreId);
        $student = $this->requireStudent($id);

        if (!$this->isCsrfTokenValid('delete_student_' . $student->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $this->em->remove($student);
        $this->em->flush();

        $this->addFlash('success', $this->t('student.flash.deleted'));

        return $this->redirectToRoute('app_admin_students_index', ['centreId' => $centre->getId()]);
    }

    private function requireCentre(string $centreId): EducationalCentre
    {
        $centre = $this->centres->findById($centreId);
        if ($centre === null) {
            throw $this->createNotFoundException();
        }

        return $centre;
    }

    private function requireCentreWithActiveYear(string $centreId): EducationalCentre
    {
        $centre = $this->requireCentre($centreId);
        if ($centre->getActiveAcademicYear() === null) {
            throw $this->createNotFoundException('No active academic year');
        }

        return $centre;
    }

    private function requireStudent(string $id): Student
    {
        $student = $this->students->findById($id);
        if ($student === null) {
            throw $this->createNotFoundException();
        }

        return $student;
    }

    /** @return array<string, Group> keyed by UUID string */
    private function indexGroupsById(EducationalCentre $centre): array
    {
        $result = [];
        foreach ($this->groups->findByActiveYearOfCentreOrderedByName($centre) as $group) {
            $result[$group->getId()->toRfc4122()] = $group;
        }

        return $result;
    }

    private function t(string $key): string
    {
        return $this->translator->trans($key, [], 'admin');
    }
}
