<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\TeacherRepository;
use App\Service\ProfileMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class PasswordResetController extends AbstractController
{
    public function __construct(
        private readonly TeacherRepository $teacherRepository,
        private readonly EntityManagerInterface $em,
        private readonly ProfileMailer $mailer,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly TranslatorInterface $translator,
        private readonly RateLimiterFactoryInterface $passwordResetLimiter,
    ) {}

    #[Route('/contrasena/recuperar', name: 'app_password_reset_request', methods: ['GET', 'POST'])]
    public function request(Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('password_reset_request', $request->request->getString('_csrf_token'))) {
                return $this->render('security/password_reset_request.html.twig', [
                    'error' => $this->translator->trans('ui.error.invalid_csrf', [], 'messages'),
                    'sent'  => false,
                ]);
            }

            $limiter = $this->passwordResetLimiter->create($request->getClientIp() ?? 'anon');
            if (!$limiter->consume()->isAccepted()) {
                return $this->render('security/password_reset_request.html.twig', [
                    'error' => $this->translator->trans('password_reset.error.too_many_requests', [], 'messages'),
                    'sent'  => false,
                ], new Response(status: Response::HTTP_TOO_MANY_REQUESTS));
            }

            $username = trim($request->request->getString('username'));
            $teacher  = $this->teacherRepository->findByUsername($username);

            if (
                $teacher !== null
                && !$teacher->isExternal()
                && $teacher->getEmail() !== null
            ) {
                $token = bin2hex(random_bytes(32));
                $teacher->setPasswordResetToken($token)
                        ->setPasswordResetTokenExpiresAt(new \DateTimeImmutable('+1 hour'));
                $this->em->flush();
                $this->mailer->sendPasswordReset($teacher, $token);
            }

            // Redirect to same page with ?sent=1 — same message regardless of whether the user exists
            return $this->redirectToRoute('app_password_reset_request', ['sent' => 1]);
        }

        return $this->render('security/password_reset_request.html.twig', [
            'error' => null,
            'sent'  => $request->query->getBoolean('sent'),
        ]);
    }

    #[Route('/contrasena/restablecer/{token}', name: 'app_password_reset', methods: ['GET', 'POST'])]
    public function reset(Request $request, string $token): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $teacher = $this->teacherRepository->findByPasswordResetToken($token);

        if ($teacher === null || $teacher->isPasswordResetTokenExpired()) {
            if ($teacher !== null) {
                $teacher->setPasswordResetToken(null)
                        ->setPasswordResetTokenExpiresAt(null);
                $this->em->flush();
            }

            return $this->render('security/password_reset_request.html.twig', [
                'error' => $this->translator->trans('password_reset.flash.token_invalid', [], 'messages'),
                'sent'  => false,
            ]);
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('password_reset', $request->request->getString('_csrf_token'))) {
                return $this->render('security/password_reset.html.twig', [
                    'token' => $token,
                    'error' => $this->translator->trans('ui.error.invalid_csrf', [], 'messages'),
                ]);
            }

            $newPassword = $request->request->getString('password');
            $confirm     = $request->request->getString('password_confirm');

            if ($newPassword === '') {
                return $this->render('security/password_reset.html.twig', [
                    'token' => $token,
                    'error' => $this->translator->trans('password_reset.error.password_required', [], 'messages'),
                ]);
            }

            if ($newPassword !== $confirm) {
                return $this->render('security/password_reset.html.twig', [
                    'token' => $token,
                    'error' => $this->translator->trans('profile.error.password_mismatch', [], 'messages'),
                ]);
            }

            $teacher->setPassword($this->passwordHasher->hashPassword($teacher, $newPassword))
                    ->setPasswordResetToken(null)
                    ->setPasswordResetTokenExpiresAt(null);
            $this->em->flush();

            return $this->redirectToRoute('app_login', ['password_reset' => 'success']);
        }

        return $this->render('security/password_reset.html.twig', [
            'token' => $token,
            'error' => null,
        ]);
    }
}
