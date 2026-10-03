<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/registro-correos')]
#[IsGranted('ROLE_ADMIN')]
class EmailNotificationLogController extends AbstractController
{
    #[Route('', name: 'app_admin_email_log')]
    public function index(): Response
    {
        return $this->render('admin/email_log/index.html.twig');
    }
}
