<?php

namespace App\Controller\Module\Rh;

use App\Entity\User;
use App\Exception\RhProcessException;
use App\Repository\RhFolhaCompetenciaRepository;
use App\Service\Rh\RhFolhaLegalService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rh/folha-legal')]
#[IsGranted('ROLE_USER')]
class RhFolhaLegalController extends AbstractController
{
    use RhEmpresaScopeTrait;

    private const T = 'modules/rh/folha_legal/';

    public function __construct(
        private WorkspaceService $workspace,
        private RhFolhaLegalService $folhaLegal,
        private RhFolhaCompetenciaRepository $competenciaRepo,
    ) {}

    protected function getWorkspace(): WorkspaceService
    {
        return $this->workspace;
    }

    #[Route('', name: 'app_rh_folha_legal', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();

        if ($request->isMethod('POST')) {
            try {
                /** @var User $user */
                $user = $this->getUser();
                $action = (string) $request->request->get('action', '');
                if ($action === 'seed_rubricas') {
                    $this->requireCsrf($request, 'rh_folha_legal_seed');
                    $n = $this->folhaLegal->seedDefaultRubricas($empresa);
                    $this->addFlash('success', $n > 0 ? "Rubricas padrão criadas ({$n})." : 'Rubricas já existem.');
                } elseif ($action === 'gerar_holerites') {
                    $this->requireCsrf($request, 'rh_folha_legal_gerar');
                    $compId = (int) $request->request->get('competencia_id', 0);
                    $comp = $compId > 0 ? $this->competenciaRepo->findOneBy(['id' => $compId, 'empresa' => $empresa]) : null;
                    if (!$comp) {
                        throw new RhProcessException('Selecione uma competência válida.');
                    }
                    $holerites = $this->folhaLegal->generateHolerites($comp, $user);
                    $this->addFlash('success', \count($holerites) . ' holerite(s) gerado(s).');
                }
            } catch (RhProcessException $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('app_rh_folha_legal');
        }

        $competencias = $this->competenciaRepo->findBy(['empresa' => $empresa], ['referencia' => 'DESC']);

        return $this->render(self::T . 'index.html.twig', [
            'rubricas' => $this->folhaLegal->listRubricas($empresa),
            'competencias' => $competencias,
        ]);
    }
}
