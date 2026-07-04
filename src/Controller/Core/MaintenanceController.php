<?php

namespace App\Controller\Core;

use App\Entity\User;
use App\Service\PlatformConfigService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MaintenanceController extends AbstractController
{
    /**
     * Página pública exibida apenas enquanto o modo manutenção estiver ativo.
     * Não expõe dados sensíveis — somente a mensagem configurada pelo tenant.
     */
    #[Route('/manutencao', name: 'app_manutencao', methods: ['GET'])]
    public function manutencao(PlatformConfigService $config): Response
    {
        if (!$config->isMaintenanceMode()) {
            return $this->redirectToRoute($this->getUser() ? 'app_dashboard' : 'app_login');
        }

        $user = $this->getUser();
        if ($user instanceof User && $user->hasPlatformAccess()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $msg = trim((string) $config->get('msg_manutencao'));
        if ($msg === '') {
            $msg = 'Estamos em manutenção. Retornamos em breve.';
        }

        $response = $this->render('core/manutencao.html.twig', [
            'msg' => $msg,
        ], new Response('', Response::HTTP_SERVICE_UNAVAILABLE));

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');
        $response->headers->set('Retry-After', '3600');

        return $response;
    }
}
