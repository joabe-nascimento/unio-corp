<?php

namespace App\Controller\Module\Juridico;

use App\Exception\JuridicoProcessException;
use App\Repository\JuridicoPublicacaoCapturaRepository;
use App\Repository\JuridicoPublicacaoConfigRepository;
use App\Service\Juridico\JuridicoProcessoService;
use App\Service\Juridico\JuridicoPublicacaoCapturaService;
use App\Service\Juridico\JuridicoPublicacaoService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/juridico/publicacoes')]
#[IsGranted('ROLE_USER')]
class JuridicoPublicacaoController extends AbstractController
{
    use JuridicoEmpresaScopeTrait;

    public function __construct(
        private WorkspaceService $workspace,
        private JuridicoPublicacaoService $publicacoes,
        private JuridicoPublicacaoCapturaService $captura,
        private JuridicoPublicacaoCapturaRepository $capturaRepo,
        private JuridicoPublicacaoConfigRepository $configRepo,
        private JuridicoProcessoService $processos,
    ) {
    }

    protected function getWorkspace(): WorkspaceService
    {
        return $this->workspace;
    }

    #[Route('', name: 'app_juridico_publicacoes')]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $status = (string) $request->query->get('status', '');
        $prioridade = (string) $request->query->get('prioridade', '');
        $q = (string) $request->query->get('q', '');

        return $this->render('modules/juridico/publicacoes_list.html.twig', [
            'publicacoes' => $this->publicacoes->findForEmpresa($empresa, $status ?: null, $prioridade ?: null, $q ?: null),
            'capturas' => $this->capturaRepo->findByEmpresa($empresa),
            'config' => $this->configRepo->getOrCreate($empresa),
            'metricas' => $this->publicacoes->metricas($empresa),
            'filter_status' => $status,
            'filter_prioridade' => $prioridade,
            'filter_q' => $q,
            'open_config' => $request->query->getBoolean('config'),
            'open_manual' => $request->query->getBoolean('manual'),
        ]);
    }

    #[Route('/captura/oab', name: 'app_juridico_publicacao_oab_add', methods: ['POST'])]
    #[IsGranted('ROLE_GESTOR')]
    public function adicionarOab(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'juridico_publicacao_oab');

        try {
            $this->captura->adicionarOab(
                $empresa,
                (string) $request->request->get('numero_oab', ''),
                (string) $request->request->get('uf_oab', ''),
            );
            $this->addFlash('success', 'OAB adicionada para monitoramento DJEN.');
        } catch (JuridicoProcessException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_juridico_publicacoes', ['config' => 1]);
    }

    #[Route('/captura/oab/{id}/remover', name: 'app_juridico_publicacao_oab_remove', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_GESTOR')]
    public function removerOab(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $captura = $this->capturaRepo->findOneBy(['id' => $id, 'empresa' => $empresa]);
        if ($captura) {
            $this->requireCsrf($request, 'juridico_publicacao_oab_remove_' . $id);
            $this->captura->removerOab($captura);
            $this->addFlash('success', 'OAB removida do monitoramento.');
        }

        return $this->redirectToRoute('app_juridico_publicacoes', ['config' => 1]);
    }

    #[Route('/captura/executar', name: 'app_juridico_publicacao_capturar', methods: ['POST'])]
    #[IsGranted('ROLE_GESTOR')]
    public function capturarAgora(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'juridico_publicacao_capturar');

        $stats = $this->captura->capturarEmpresa($empresa, 3, true);
        $this->addFlash('success', sprintf(
            'Captura concluída: %d nova(s), %d atualizada(s), %d triada(s), %d prazo(s) automático(s).',
            $stats['novas'],
            $stats['atualizadas'],
            $stats['triadas'],
            $stats['prazos'],
        ));

        return $this->redirectToRoute('app_juridico_publicacoes');
    }

    #[Route('/config/salvar', name: 'app_juridico_publicacao_config_salvar', methods: ['POST'])]
    #[IsGranted('ROLE_GESTOR')]
    public function salvarConfig(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'juridico_publicacao_config');
        $this->publicacoes->salvarConfig($empresa, $request->request->all());
        $this->addFlash('success', 'Configuração de automação salva.');

        return $this->redirectToRoute('app_juridico_publicacoes', ['config' => 1]);
    }

    #[Route('/manual/novo', name: 'app_juridico_publicacao_manual', methods: ['POST'])]
    public function criarManual(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'juridico_publicacao_manual');

        try {
            $pub = $this->publicacoes->criarManual($empresa, $request->request->all());
            $this->addFlash('success', 'Publicação registrada manualmente.');

            return $this->redirectToRoute('app_juridico_publicacao_show', ['id' => $pub->getId()]);
        } catch (JuridicoProcessException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_juridico_publicacoes', ['manual' => 1]);
        }
    }

    #[Route('/{id}', name: 'app_juridico_publicacao_show', requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $empresa = $this->requireEmpresa();
        $publicacao = $this->publicacoes->loadForEmpresa($empresa, $id);

        if (!$publicacao->isLida()) {
            $this->publicacoes->marcarLida($publicacao);
        }

        return $this->render('modules/juridico/publicacao_show.html.twig', [
            'publicacao' => $publicacao,
            'processos' => $this->processos->listForSelect($empresa),
        ]);
    }

    #[Route('/{id}/triar', name: 'app_juridico_publicacao_triar', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function triar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $publicacao = $this->publicacoes->loadForEmpresa($empresa, $id);
        $this->requireCsrf($request, 'juridico_publicacao_triar_' . $id);

        try {
            /** @var \App\Entity\User $user */
            $user = $this->getUser();
            $this->publicacoes->triarComIa($publicacao, $user);
            $this->addFlash('success', 'Sasha concluiu a triagem desta publicação.');
        } catch (JuridicoProcessException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_juridico_publicacao_show', ['id' => $id]);
    }

    #[Route('/{id}/vincular', name: 'app_juridico_publicacao_vincular', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function vincular(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $publicacao = $this->publicacoes->loadForEmpresa($empresa, $id);
        $this->requireCsrf($request, 'juridico_publicacao_vincular_' . $id);

        try {
            $this->publicacoes->vincularProcesso($publicacao, (int) $request->request->get('processo_id', 0));
            $this->addFlash('success', 'Publicação vinculada ao processo.');
        } catch (JuridicoProcessException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_juridico_publicacao_show', ['id' => $id]);
    }

    #[Route('/{id}/criar-prazo', name: 'app_juridico_publicacao_criar_prazo', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function criarPrazo(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $publicacao = $this->publicacoes->loadForEmpresa($empresa, $id);
        $this->requireCsrf($request, 'juridico_publicacao_prazo_' . $id);

        try {
            $this->publicacoes->criarPrazoSugerido($publicacao, $request->request->all());
            $this->addFlash('success', 'Prazo criado com base na sugestão da Sasha.');
        } catch (JuridicoProcessException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_juridico_publicacao_show', ['id' => $id]);
    }

    #[Route('/{id}/arquivar', name: 'app_juridico_publicacao_arquivar', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function arquivar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $publicacao = $this->publicacoes->loadForEmpresa($empresa, $id);
        $this->requireCsrf($request, 'juridico_publicacao_arquivar_' . $id);
        $this->publicacoes->arquivar($publicacao);
        $this->addFlash('success', 'Publicação arquivada.');

        return $this->redirectToRoute('app_juridico_publicacoes');
    }

    #[Route('/{id}/certidao', name: 'app_juridico_publicacao_certidao', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function certidao(int $id): Response
    {
        $empresa = $this->requireEmpresa();
        $publicacao = $this->publicacoes->loadForEmpresa($empresa, $id);

        try {
            $pdf = $this->publicacoes->baixarCertidao($publicacao);
        } catch (JuridicoProcessException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_juridico_publicacao_show', ['id' => $id]);
        }

        return new Response($pdf['content'], 200, [
            'Content-Type' => $pdf['content_type'],
            'Content-Disposition' => 'attachment; filename="' . $pdf['filename'] . '"',
        ]);
    }
}
