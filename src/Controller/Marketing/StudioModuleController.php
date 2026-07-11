<?php

namespace App\Controller\Marketing;

use App\Service\Marketing\ClinicLandingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class StudioModuleController extends AbstractController
{
    #[Route('/modulo/{id}', name: 'app_marketing_modulo_show', methods: ['GET'])]
    public function show(string $id, ClinicLandingService $landing): Response
    {
        $hub = $landing->hubById($id);
        if ($hub === null) {
            throw new NotFoundHttpException('Módulo não encontrado.');
        }

        return $this->render('marketing/modulo_show.html.twig', [
            'hub' => $hub,
            'pulso_api_url' => $this->generateUrl('api_marketing_modulo_pulso', ['id' => $id]),
            'like_api_url' => $this->generateUrl('api_marketing_modulo_curtir', ['id' => $id]),
            'comment_api_url' => $this->generateUrl('api_marketing_modulo_comentario', ['id' => $id]),
        ]);
    }
}
