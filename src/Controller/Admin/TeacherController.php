<?php

namespace App\Controller\Admin;

use App\Entity\PersonName;
use App\Entity\Teacher;
use App\Repository\TeacherRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/docentes')]
#[IsGranted('ROLE_ADMIN')]
class TeacherController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TeacherRepository $teachers,
        private readonly UserPasswordHasherInterface $hasher,
    ) {}

    #[Route('', name: 'app_admin_teachers_index')]
    public function index(): Response
    {
        return $this->render('admin/teacher/index.html.twig', [
            'teachers' => $this->teachers->findAllOrderedByName(),
        ]);
    }

    #[Route('/nuevo', name: 'app_admin_teachers_new')]
    public function new(Request $request): Response
    {
        $errors = [];
        $values = ['first_name' => '', 'last_name' => '', 'username' => '', 'email' => '', 'password' => ''];
        $flags  = ['admin' => false, 'active' => true, 'external' => false];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('new_teacher', $request->request->getString('_token'))) {
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

            $errors = $this->validateTeacher($values, true);

            if (empty($errors['username']) && $this->teachers->findByUsername($values['username']) !== null) {
                $errors['username'] = 'Ya existe un docente con este nombre de usuario.';
            }

            if (empty($errors)) {
                $teacher = new Teacher(new PersonName($values['first_name'], $values['last_name']));
                $teacher->setUsername($values['username'])
                    ->setEmail($values['email'] !== '' ? $values['email'] : null)
                    ->setAdmin($flags['admin'])
                    ->setActive($flags['active'])
                    ->setExternal($flags['external'])
                    ->setPassword($this->hasher->hashPassword($teacher, $values['password']));

                $this->em->persist($teacher);
                $this->em->flush();

                $this->addFlash('success', 'Docente creado correctamente.');

                return $this->redirectToRoute('app_admin_teachers_index');
            }
        }

        return $this->render('admin/teacher/new.html.twig', [
            'errors' => $errors,
            'values' => $values,
            'flags'  => $flags,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_teachers_edit')]
    public function edit(string $id, Request $request): Response
    {
        $teacher = $this->teachers->findById($id);
        if ($teacher === null) {
            throw $this->createNotFoundException();
        }

        $errors = [];
        $values = [
            'first_name' => $teacher->getName()->getFirstName(),
            'last_name'  => $teacher->getName()->getLastName(),
            'username'   => $teacher->getUsername(),
            'email'      => $teacher->getEmail() ?? '',
            'password'   => '',
        ];
        $flags = [
            'admin'    => $teacher->isAdmin(),
            'active'   => $teacher->isActive(),
            'external' => $teacher->isExternal(),
        ];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('edit_teacher_' . $id, $request->request->getString('_token'))) {
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

            $errors = $this->validateTeacher($values, false);

            $existing = $this->teachers->findByUsername($values['username']);
            if (empty($errors['username']) && $existing !== null
                && $existing->getId()->toRfc4122() !== $id) {
                $errors['username'] = 'Ya existe un docente con este nombre de usuario.';
            }

            if (empty($errors)) {
                $teacher->setName(new PersonName($values['first_name'], $values['last_name']))
                    ->setUsername($values['username'])
                    ->setEmail($values['email'] !== '' ? $values['email'] : null)
                    ->setAdmin($flags['admin'])
                    ->setActive($flags['active'])
                    ->setExternal($flags['external']);

                if ($values['password'] !== '') {
                    $teacher->setPassword($this->hasher->hashPassword($teacher, $values['password']));
                }

                $this->em->flush();

                $this->addFlash('success', 'Docente guardado correctamente.');

                return $this->redirectToRoute('app_admin_teachers_edit', ['id' => $id]);
            }
        }

        return $this->render('admin/teacher/edit.html.twig', [
            'teacher' => $teacher,
            'errors'  => $errors,
            'values'  => $values,
            'flags'   => $flags,
        ]);
    }

    #[Route('/{id}/eliminar', name: 'app_admin_teachers_delete', methods: ['POST'])]
    public function delete(string $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('delete_teacher_' . $id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $teacher = $this->teachers->findById($id);
        if ($teacher === null) {
            throw $this->createNotFoundException();
        }

        $currentUser = $this->getUser();
        if ($currentUser instanceof Teacher && $currentUser->getId()->toRfc4122() === $id) {
            $this->addFlash('error', 'No puedes eliminar tu propia cuenta.');

            return $this->redirectToRoute('app_admin_teachers_index');
        }

        try {
            $this->em->remove($teacher);
            $this->em->flush();
            $this->addFlash('success', 'Docente eliminado correctamente.');
        } catch (\Exception) {
            $this->addFlash('error', 'No se puede eliminar este docente porque tiene datos asociados.');
        }

        return $this->redirectToRoute('app_admin_teachers_index');
    }

    /**
     * @param  array<string, string> $values
     * @return array<string, string>
     */
    private function validateTeacher(array $values, bool $passwordRequired): array
    {
        $errors = [];

        if ($values['first_name'] === '') {
            $errors['first_name'] = 'El nombre es obligatorio.';
        }

        if ($values['last_name'] === '') {
            $errors['last_name'] = 'Los apellidos son obligatorios.';
        }

        if ($values['username'] === '') {
            $errors['username'] = 'El nombre de usuario es obligatorio.';
        }

        if ($values['email'] !== '' && !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'El email no tiene un formato válido.';
        }

        if ($passwordRequired && $values['password'] === '') {
            $errors['password'] = 'La contraseña es obligatoria.';
        }

        return $errors;
    }
}
