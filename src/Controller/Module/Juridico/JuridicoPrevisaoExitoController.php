<?php

namespace App\Controller\Module\Juridico;

use App\Service\Juridico\PrevisaoExitoService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Painel "Previsão de Êxito" — visão de carteira do score heurístico de todos os
 * processos ativos, com filtros por área/nível e ranking de maior risco.
 */
#[Route('/juridico/previsao-de-exito')]
#[IsGranted('ROLE_USER')]
class JuridicoPrevisaoExitoController extends AbstractController
{
    use JuridicoEmpresaScopeTrait;

    public function __construct(
        private WorkspaceService $workspace,
        private PrevisaoExitoService $previsaoExito,
    ) {
    }

    protected function getWorkspace(): WorkspaceService
    {
        return $this->workspace;
    }

    #[Route('', name: 'app_juridico_previsao_exito')]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $area = (string) $request->query->get('area', '');
        $nivel = (string) $request->query->get('nivel', '');
        $ordenar = (string) $request->query->get('ordenar', 'score_asc');

        $overview = $this->previsaoExito->overview($empresa, $area ?: null, $nivel ?: null, $ordenar);

        return $this->render('modules/juridico/previsao_exito_list.html.twig', [
            'overview' => $overview,
            'filter_area' => $area,
            'filter_nivel' => $nivel,
            'filter_ordenar' => $ordenar,
        ]);
    }
}
