<?php

namespace App\Controller\Module\Juridico;

use App\Entity\JuridicoTemplatePeca;
use App\Exception\JuridicoProcessException;
use App\Repository\JuridicoTemplatePecaRepository;
use App\Service\Juridico\JuridicoProcessoService;
use App\Service\Juridico\JuridicoTemplateService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/juridico/templates')]
#[IsGranted('ROLE_USER')]
class JuridicoTemplateController extends AbstractController
{
    use JuridicoEmpresaScopeTrait;

    public function __construct(
        private WorkspaceService $workspace,
        private JuridicoTemplateService $templates,
        private JuridicoTemplatePecaRepository $repo,
        private JuridicoProcessoService $processos,
    ) {
    }

    protected function getWorkspace(): WorkspaceService
    {
        return $this->workspace;
    }

    #[Route('', name: 'app_juridico_templates')]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $q = (string) $request->query->get('q', '');
        $lista = $this->repo->findForEmpresa($empresa, $q ?: null);
        $aprovados = \count(array_filter($lista, static fn (JuridicoTemplatePeca $t) => $t->isAprovado()));

        return $this->render('modules/juridico/templates_pecas_list.html.twig', [
            'templates' => $lista,
            'processos' => $this->processos->listForSelect($empresa),
            'metricas' => [
                'total' => \count($lista),
                'aprovados' => $aprovados,
            ],
            'filter_q' => $q,
            'preview' => $request->query->get('preview', ''),
            'open_novo' => $request->query->getBoolean('open_novo'),
        ]);
    }

    #[Route('/novo', name: 'app_juridico_template_novo', methods: ['POST'])]
    public function novo(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        try {
            $this->requireCsrf($request, 'juridico_template_form');
            $this->templates->create($empresa, $request->request->all());
            $this->addFlash('success', 'Template salvo como rascunho.');
        } catch (JuridicoProcessException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_juridico_templates');
    }

    #[Route('/{id}/aprovar', name: 'app_juridico_template_aprovar', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function aprovar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'juridico_template_aprovar_'.$id);
        $user = $this->getUser();
        $tpl = $this->templates->loadForEmpresa($empresa, $id);
        if ($user instanceof \App\Entity\User) {
            $this->templates->aprovar($tpl, $user);
            $this->addFlash('success', 'Template aprovado para uso da equipe.');
        }

        return $this->redirectToRoute('app_juridico_templates');
    }

    #[Route('/{id}/gerar', name: 'app_juridico_template_gerar', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function gerar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'juridico_template_gerar_'.$id);
        $tpl = $this->templates->loadForEmpresa($empresa, $id);
        $processoId = (int) $request->request->get('processo_id', 0);
        $processo = $processoId > 0 ? $this->processos->loadForEmpresa($empresa, $processoId) : null;
        $texto = $this->templates->render($tpl, $processo);

        return $this->render('modules/juridico/templates_pecas_list.html.twig', [
            'templates' => $this->repo->findForEmpresa($empresa),
            'processos' => $this->processos->listForSelect($empresa),
            'metricas' => ['total' => 0, 'aprovados' => 0],
            'filter_q' => '',
            'preview' => $texto,
            'open_novo' => false,
        ]);
    }
}
