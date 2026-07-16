<?php

namespace App\Controller\Module\PosOperatorio;

use App\Entity\User;
use App\Service\PosOperatorio\PosOperatorioQuestionarioListService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pos-operatorio/questionarios')]
#[IsGranted('ROLE_USER')]
final class PosOperatorioQuestionarioController extends AbstractController
{
    private const T = 'modules/pos-operatorio/questionarios/';

    public function __construct(
        private WorkspaceService $workspace,
        private PosOperatorioQuestionarioListService $service,
    ) {}

    #[Route('', name: 'app_pos_operatorio_questionarios')]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $filtro = $request->query->getString('filtro', '');
        $data = $this->service->buildList($empresa);
        if ($filtro === 'alerta') {
            $data['items'] = array_values(array_filter(
                $data['items'],
                static fn (array $row): bool => (bool) ($row['alerta_gerado'] ?? false),
            ));
        }

        return $this->render(self::T . 'index.html.twig', array_merge(
            [
                'empresa' => $empresa,
                'pos_section' => 'questionarios',
                'filter_filtro' => $filtro,
            ],
            $data,
        ));
    }

    private function requireEmpresa(): \App\Entity\Empresa
    {
        /** @var User $user */
        $user = $this->getUser();
        $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();
        if (!$empresa) {
            throw $this->createAccessDeniedException('Área de trabalho indisponível.');
        }

        return $empresa;
    }
}
