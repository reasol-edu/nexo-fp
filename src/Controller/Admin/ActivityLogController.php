<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Teacher;
use App\Repository\TeacherRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/registro-actividad')]
#[IsGranted('ROLE_ADMIN')]
class ActivityLogController extends AbstractController
{
    public function __construct(
        private readonly TeacherRepository $teachers,
    ) {}

    #[Route('', name: 'app_admin_activity_log')]
    public function index(): Response
    {
        return $this->render('admin/activity_log/index.html.twig');
    }

    /**
     * Endpoint para el autocompletar de usuarios en el filtro del listado.
     * Devuelve hasta 20 docentes cuyo nombre o usuario contenga la búsqueda.
     */
    #[Route('/usuarios', name: 'app_admin_activity_log_users')]
    public function users(Request $request): JsonResponse
    {
        $q       = trim($request->query->getString('q'));
        $results = [];

        if ($q !== '') {
            $teachers = $this->teachers->createFilteredOrderedByNameQuery($q)
                ->setMaxResults(20)
                ->getResult();

            foreach ($teachers as $teacher) {
                $results[] = [
                    'id'   => $teacher->getId()->toRfc4122(),
                    'text' => $teacher->getName()->getLastName() . ', ' . $teacher->getName()->getFirstName()
                              . ' (' . $teacher->getUsername() . ')',
                ];
            }
        }

        return $this->json($results);
    }
}
