<?php

namespace App\Controller\Module\Juridico;

use App\Entity\JuridicoMeta;
use App\Entity\User;
use App\Exception\JuridicoProcessException;
use App\Repository\EmpresaRepository;
use App\Repository\UserRepository;
use App\Service\Juridico\JuridicoAnalyticsService;
use App\Service\Juridico\JuridicoMetaService;
use App\Service\WorkspaceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/juridico/analytics')]
#[IsGranted('ROLE_USER')]
class JuridicoAnalyticsController extends AbstractController
{
    use JuridicoEmpresaScopeTrait;

    public function __construct(
        private WorkspaceService $workspace,
        private JuridicoAnalyticsService $analytics,
        private JuridicoMetaService $metas,
        private EmpresaRepository $empresaRepo,
        private UserRepository $userRepo,
        private EntityManagerInterface $em,
    ) {
    }

    protected function getWorkspace(): WorkspaceService
    {
        return $this->workspace;
    }

    #[Route('', name: 'app_juridico_analytics')]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $temGrupo = $empresa->isMatriz() || $empresa->isFilial();
        $consolidado = $temGrupo && $request->query->getBoolean('grupo', $empresa->isMatriz());
        $periodoMetas = (string) $request->query->get('periodo_metas', '') ?: (new \DateTimeImmutable('today'))->format('Y-m');

        return $this->render('modules/juridico/analytics.html.twig', [
            'kpis' => $this->analytics->kpis($empresa, $consolidado),
            'chart_sections' => $this->analytics->buildSections($empresa, $consolidado),
            'tem_grupo' => $temGrupo,
            'consolidado' => $consolidado,
            'e_matriz' => $empresa->isMatriz(),
            'e_filial' => $empresa->isFilial(),
            'codigo_grupo' => $empresa->getCodigoGrupo(),
            'filiais' => $empresa->isMatriz() ? $empresa->getFiliais() : [],
            'empresa_matriz' => $empresa->getEmpresaMatriz(),
            'metas' => $this->metas->progresso($empresa, $periodoMetas),
            'periodo_metas' => $periodoMetas,
            'responsaveis' => $this->userRepo->findBy(['empresa' => $empresa], ['nome' => 'ASC']),
        ]);
    }

    #[Route('/metas', name: 'app_juridico_analytics_meta_nova', methods: ['POST'])]
    public function novaMeta(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'juridico_meta_form');

        $usuario = $this->getUser();

        try {
            $this->metas->create($empresa, $request->request->all(), $usuario instanceof User ? $usuario : null);
            $this->addFlash('success', 'Meta cadastrada.');
        } catch (JuridicoProcessException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_juridico_analytics', array_filter([
            'periodo_metas' => (string) $request->request->get('periodo', ''),
        ]));
    }

    #[Route('/metas/{id}/excluir', name: 'app_juridico_analytics_meta_excluir', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function excluirMeta(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'juridico_meta_excluir_' . $id);

        $meta = $this->em->getRepository(JuridicoMeta::class)->findOneBy(['id' => $id, 'empresa' => $empresa]);
        if ($meta) {
            $this->metas->delete($meta);
            $this->addFlash('success', 'Meta removida.');
        }

        return $this->redirectToRoute('app_juridico_analytics', array_filter([
            'periodo_metas' => (string) $request->request->get('periodo', ''),
        ]));
    }

    #[Route('/grupo/gerar-codigo', name: 'app_juridico_analytics_gerar_codigo', methods: ['POST'])]
    public function gerarCodigo(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'juridico_grupo_codigo');

        if ($empresa->getCodigoGrupo() === null) {
            do {
                $codigo = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            } while ($this->empresaRepo->findByCodigoGrupo($codigo) !== null);

            $empresa->setCodigoGrupo($codigo);
            $this->em->flush();
        }

        $this->addFlash('success', 'Código do grupo gerado: ' . $empresa->getCodigoGrupo());

        return $this->redirectToRoute('app_juridico_analytics');
    }

    #[Route('/grupo/vincular', name: 'app_juridico_analytics_vincular', methods: ['POST'])]
    public function vincular(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'juridico_grupo_vincular');

        $codigo = strtoupper(trim((string) $request->request->get('codigo', '')));
        if ($codigo === '') {
            $this->addFlash('error', 'Informe o código do grupo da matriz.');

            return $this->redirectToRoute('app_juridico_analytics');
        }

        $matriz = $this->empresaRepo->findByCodigoGrupo($codigo);
        if ($matriz === null) {
            $this->addFlash('error', 'Código de grupo não encontrado.');

            return $this->redirectToRoute('app_juridico_analytics');
        }

        if ($matriz->getId() === $empresa->getId()) {
            $this->addFlash('error', 'Você não pode vincular o escritório a ele mesmo.');

            return $this->redirectToRoute('app_juridico_analytics');
        }

        $empresa->setEmpresaMatriz($matriz);
        $this->em->flush();
        $this->addFlash('success', 'Escritório vinculado ao grupo de "' . $matriz->getNome() . '".');

        return $this->redirectToRoute('app_juridico_analytics');
    }

    #[Route('/grupo/desvincular', name: 'app_juridico_analytics_desvincular', methods: ['POST'])]
    public function desvincular(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'juridico_grupo_desvincular');

        $empresa->setEmpresaMatriz(null);
        $this->em->flush();
        $this->addFlash('success', 'Escritório desvinculado do grupo.');

        return $this->redirectToRoute('app_juridico_analytics');
    }
}
