<?php

declare(strict_types=1);

namespace App\Controller\Module\Juridico;

use App\Service\Juridico\AgenteAutonomoJuridicoService;
use App\Service\Juridico\AgenteAutonomoStatusStore;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/juridico/agente-autonomo')]
#[IsGranted('ROLE_USER')]
final class JuridicoAgenteAutonomoController extends AbstractController
{
    public function __construct(
        private AgenteAutonomoStatusStore $statusStore,
        private AgenteAutonomoJuridicoService $agente,
    ) {
    }

    #[Route('', name: 'app_juridico_agente_autonomo', methods: ['GET'])]
    public function index(): Response
    {
        $resumo = $this->statusStore->resumo();
        $estado = $this->statusStore->load();
        $empresas = \is_array($estado['empresas'] ?? null) ? $estado['empresas'] : [];

        uasort($empresas, static function ($a, $b) {
            $ta = (string) ($a['last_run_at'] ?? '');
            $tb = (string) ($b['last_run_at'] ?? '');

            return $tb <=> $ta;
        });

        return $this->render('modules/juridico/agente_autonomo.html.twig', [
            'resumo' => $resumo,
            'empresas' => $empresas,
        ]);
    }

    #[Route('/executar', name: 'app_juridico_agente_autonomo_executar', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function executar(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('agente_autonomo_run', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token inválido.');

            return $this->redirectToRoute('app_juridico_agente_autonomo');
        }

        $resultado = $this->agente->executar();
        if (!($resultado['executado'] ?? false)) {
            $this->addFlash('warning', 'Agente autônomo indisponível neste perfil.');
        } else {
            $this->addFlash(
                'success',
                sprintf(
                    'Varredura concluída: %d escritório(s), %d alerta(s) novo(s).',
                    (int) ($resultado['empresas_processadas'] ?? 0),
                    (int) ($resultado['alertas_gerados'] ?? 0),
                ),
            );
        }

        return $this->redirectToRoute('app_juridico_agente_autonomo');
    }
}
