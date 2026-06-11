<?php

namespace App\Controller;

use App\Entity\PersonName;
use App\Entity\Teacher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/perfil')]
#[IsGranted('ROLE_TEACHER')]
class ProfileController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly TranslatorInterface $translator,
    ) {}

    #[Route('', name: 'app_profile')]
    public function edit(Request $request): Response
    {
        $teacher = $this->getUser();
        if (!$teacher instanceof Teacher) {
            throw $this->createAccessDeniedException();
        }

        $errors = [];
        $values = [
            'first_name'           => $teacher->getName()->getFirstName(),
            'last_name'            => $teacher->getName()->getLastName(),
            'email'                => $teacher->getEmail() ?? '',
            'current_password'     => '',
            'new_password'         => '',
            'new_password_confirm' => '',
        ];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('edit_profile', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $values = [
                'first_name'           => trim($request->request->getString('first_name')),
                'last_name'            => trim($request->request->getString('last_name')),
                'email'                => trim($request->request->getString('email')),
                'current_password'     => $request->request->getString('current_password'),
                'new_password'         => $request->request->getString('new_password'),
                'new_password_confirm' => $request->request->getString('new_password_confirm'),
            ];

            if ($values['first_name'] === '') {
                $errors['first_name'] = $this->t('profile.error.first_name_required');
            }

            if ($values['last_name'] === '') {
                $errors['last_name'] = $this->t('profile.error.last_name_required');
            }

            if ($values['email'] !== '' && !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = $this->t('profile.error.email_invalid');
            }

            if (!$teacher->isExternal() && $values['new_password'] !== '') {
                if ($values['current_password'] === '') {
                    $errors['current_password'] = $this->t('profile.error.current_password_required');
                } elseif (!$this->hasher->isPasswordValid($teacher, $values['current_password'])) {
                    $errors['current_password'] = $this->t('profile.error.current_password_invalid');
                }

                if ($values['new_password'] !== $values['new_password_confirm']) {
                    $errors['new_password_confirm'] = $this->t('profile.error.password_mismatch');
                }
            }

            if (empty($errors)) {
                $teacher->setName(new PersonName($values['first_name'], $values['last_name']))
                    ->setEmail($values['email'] !== '' ? $values['email'] : null);

                if (!$teacher->isExternal() && $values['new_password'] !== '') {
                    $teacher->setPassword($this->hasher->hashPassword($teacher, $values['new_password']));
                }

                $this->em->flush();

                $this->addFlash('success', $this->t('profile.flash.saved'));

                return $this->redirectToRoute('app_profile');
            }
        }

        return $this->render('profile/edit.html.twig', [
            'teacher' => $teacher,
            'errors'  => $errors,
            'values'  => $values,
        ]);
    }

    #[Route('/ajustes', name: 'app_profile_settings')]
    public function settings(): Response
    {
        return $this->render('profile/settings.html.twig');
    }

    private function t(string $key): string
    {
        return $this->translator->trans($key, [], 'messages');
    }
}
