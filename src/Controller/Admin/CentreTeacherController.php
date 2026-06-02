<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\EducationalCentre;
use App\Entity\PersonName;
use App\Entity\Teacher;
use App\Repository\EducationalCentreRepository;
use App\Repository\TeacherRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/centros/{centreId}/docentes-curso')]
#[IsGranted('ROLE_ADMIN')]
class CentreTeacherController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EducationalCentreRepository $centres,
        private readonly TeacherRepository $teachers,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly TranslatorInterface $translator,
    ) {}

    #[Route('', name: 'app_admin_centre_teachers_index')]
    public function index(string $centreId): Response
    {
        $centre = $this->requireCentre($centreId);

        return $this->render('admin/centre_teacher/index.html.twig', ['centre' => $centre]);
    }

    #[Route('/añadir', name: 'app_admin_centre_teachers_add', methods: ['POST'])]
    public function add(string $centreId, Request $request): Response
    {
        $centre = $this->requireCentreWithActiveYear($centreId);

        if (!$this->isCsrfTokenValid('add_centre_teacher', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $username = trim($request->request->getString('username'));
        $teacher  = $username !== '' ? $this->teachers->findByUsername($username) : null;

        if ($teacher === null) {
            return $this->redirectToRoute('app_admin_centre_teachers_register', [
                'centreId' => $centre->getId(),
                'username' => $username,
            ]);
        }

        $year = $centre->getActiveAcademicYear();
        if (!$year->getTeachers()->contains($teacher)) {
            $year->addTeacher($teacher);
            $this->em->flush();
            $this->addFlash('success', $this->t('centre_teachers.flash.added'));
        }

        return $this->redirectToRoute('app_admin_centre_teachers_index', ['centreId' => $centre->getId()]);
    }

    #[Route('/registrar', name: 'app_admin_centre_teachers_register')]
    public function register(string $centreId, Request $request): Response
    {
        $centre = $this->requireCentreWithActiveYear($centreId);

        $errors = [];
        $values = [
            'first_name' => '',
            'last_name'  => '',
            'username'   => trim($request->query->getString('username')),
            'email'      => '',
            'password'   => '',
        ];
        $flags = ['admin' => false, 'active' => true, 'external' => true];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('register_centre_teacher', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $values = [
                'first_name' => trim($request->request->getString('first_name')),
                'last_name'  => trim($request->request->getString('last_name')),
                'username'   => trim($request->request->getString('username')),
                'email'      => trim($request->request->getString('email')),
                'password'   => $request->request->getString('password'),
            ];
            $flags = [
                'admin'    => $request->request->has('admin'),
                'active'   => $request->request->has('active'),
                'external' => $request->request->has('external'),
            ];

            $errors = $this->validateTeacher($values, !$flags['external']);

            if (empty($errors['username']) && $this->teachers->findByUsername($values['username']) !== null) {
                $errors['username'] = $this->t('teacher.error.username_duplicate');
            }

            if (empty($errors)) {
                $teacher = new Teacher(new PersonName($values['first_name'], $values['last_name']));
                $teacher->setUsername($values['username'])
                    ->setEmail($values['email'] !== '' ? $values['email'] : null)
                    ->setAdmin($flags['admin'])
                    ->setActive($flags['active'])
                    ->setExternal($flags['external']);

                if (!$flags['external']) {
                    $teacher->setPassword($this->hasher->hashPassword($teacher, $values['password']));
                }

                $this->em->persist($teacher);
                $centre->getActiveAcademicYear()->addTeacher($teacher);
                $this->em->flush();

                $this->addFlash('success', $this->t('centre_teachers.flash.registered_and_added'));

                return $this->redirectToRoute('app_admin_centre_teachers_index', ['centreId' => $centre->getId()]);
            }
        }

        return $this->render('admin/centre_teacher/register.html.twig', [
            'centre' => $centre,
            'errors' => $errors,
            'values' => $values,
            'flags'  => $flags,
        ]);
    }

    #[Route('/{teacherId}/quitar', name: 'app_admin_centre_teachers_remove', methods: ['POST'])]
    public function remove(string $centreId, string $teacherId, Request $request): Response
    {
        $centre  = $this->requireCentreWithActiveYear($centreId);
        $teacher = $this->teachers->findById($teacherId);

        if ($teacher === null) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('remove_centre_teacher_' . $teacher->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $year = $centre->getActiveAcademicYear();
        if ($year->getTeachers()->contains($teacher)) {
            $year->removeTeacher($teacher);
            $this->em->flush();
        }

        $this->addFlash('success', $this->t('centre_teachers.flash.removed'));

        return $this->redirectToRoute('app_admin_centre_teachers_index', ['centreId' => $centre->getId()]);
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

    /**
     * @param  array<string, string> $values
     * @return array<string, string>
     */
    private function validateTeacher(array $values, bool $passwordRequired): array
    {
        $errors = [];

        if ($values['first_name'] === '') {
            $errors['first_name'] = $this->t('teacher.error.first_name_required');
        }

        if ($values['last_name'] === '') {
            $errors['last_name'] = $this->t('teacher.error.last_name_required');
        }

        if ($values['username'] === '') {
            $errors['username'] = $this->t('teacher.error.username_required');
        }

        if ($values['email'] !== '' && !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = $this->t('teacher.error.email_invalid');
        }

        if ($passwordRequired && $values['password'] === '') {
            $errors['password'] = $this->t('teacher.error.password_required');
        }

        return $errors;
    }

    private function t(string $key): string
    {
        return $this->translator->trans($key, [], 'admin');
    }
}
