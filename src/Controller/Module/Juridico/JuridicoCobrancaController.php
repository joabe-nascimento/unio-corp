<?php

namespace App\Controller\Module\Juridico;

use App\Exception\JuridicoProcessException;
use App\Repository\JuridicoClienteRepository;
use App\Repository\JuridicoHonorarioLancamentoRepository;
use App\Service\Juridico\JuridicoCobrancaService;
use App\Service\Juridico\JuridicoModuleMetricsService;
use App\Service\Juridico\JuridicoProcessoService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/juridico/cobranca')]
#[IsGranted('ROLE_USER')]
class JuridicoCobrancaController extends AbstractController
{
    use JuridicoEmpresaScopeTrait;

    public function __construct(
        private WorkspaceService $workspace,
        private JuridicoCobrancaService $cobrancas,
        private JuridicoClienteRepository $clienteRepo,
        private JuridicoProcessoService $processos,
        private JuridicoHonorarioLancamentoRepository $honorarioRepo,
        private JuridicoModuleMetricsService $moduleMetrics,
    ) {
    }

    protected function getWorkspace(): WorkspaceService
    {
        return $this->workspace;
    }

    #[Route('', name: 'app_juridico_cobranca')]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $this->cobrancas->atualizarVencidos($empresa);

        $status = (string) $request->query->get('status', '');
        $q = (string) $request->query->get('q', '');
        $titulos = $this->cobrancas->findForEmpresa($empresa, $status ?: null, $q ?: null);

        $metricas = $this->moduleMetrics->cobranca($empresa);

        return $this->render('modules/juridico/cobranca_list.html.twig', [
            'titulos' => $titulos,
            'metricas' => $metricas,
            'total_aberto' => $metricas['em_aberto'],
            'total_vencido' => $metricas['vencidos'],
            'clientes' => $this->clienteRepo->findBy(['empresa' => $empresa], ['nome' => 'ASC']),
            'processos' => $this->processos->listForSelect($empresa),
            'filter_status' => $status,
            'filter_q' => $q,
            'open_novo' => $request->query->getBoolean('open_novo'),
        ]);
    }

    #[Route('/novo', name: 'app_juridico_cobranca_novo', methods: ['POST'])]
    public function novo(Request $request): Response
    {
        $empresa = $this->requireEmpresa();

        try {
            $this->requireCsrf($request, 'juridico_cobranca_form');
            $this->cobrancas->create($empresa, $request->request->all());
            $this->addFlash('success', 'Título de cobrança criado.');
        } catch (JuridicoProcessException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_juridico_cobranca');
    }

    #[Route('/gerar-de-honorarios', name: 'app_juridico_cobranca_gerar_honorarios', methods: ['POST'])]
    public function gerarDeHonorarios(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'juridico_cobranca_gerar');

        $mes = (string) $request->request->get('mes', (new \DateTimeImmutable('today'))->format('Y-m'));
        $gerados = 0;

        foreach ($this->honorarioRepo->findForEmpresa($empresa, null, $mes) as $lancamento) {
            if (!$lancamento->isFaturavel()) {
                continue;
            }
            try {
                $this->cobrancas->gerarDeLancamento($lancamento);
                ++$gerados;
            } catch (JuridicoProcessException) {
            }
        }

        $this->addFlash('success', sprintf('%d título(s) gerado(s) a partir dos honorários de %s.', $gerados, $mes));

        return $this->redirectToRoute('app_juridico_cobranca');
    }

    #[Route('/{id}/pagar', name: 'app_juridico_cobranca_pagar', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function pagar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $titulo = $this->cobrancas->loadForEmpresa($empresa, $id);
        $this->requireCsrf($request, 'juridico_cobranca_pagar_' . $id);
        $this->cobrancas->marcarPago($titulo);
        $this->addFlash('success', 'Título marcado como pago.');

        return $this->redirectToRoute('app_juridico_cobranca');
    }

    #[Route('/{id}/whatsapp', name: 'app_juridico_cobranca_whatsapp', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function whatsapp(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $titulo = $this->cobrancas->loadForEmpresa($empresa, $id);
        $this->requireCsrf($request, 'juridico_cobranca_whatsapp_' . $id);

        try {
            $this->cobrancas->enviarCobrancaWhatsapp($titulo);
            $this->addFlash('success', 'Cobrança enviada por WhatsApp.');
        } catch (JuridicoProcessException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_juridico_cobranca');
    }

    #[Route('/{id}/cancelar', name: 'app_juridico_cobranca_cancelar', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancelar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $titulo = $this->cobrancas->loadForEmpresa($empresa, $id);
        $this->requireCsrf($request, 'juridico_cobranca_cancelar_' . $id);
        $this->cobrancas->cancelar($titulo);
        $this->addFlash('success', 'Título cancelado.');

        return $this->redirectToRoute('app_juridico_cobranca');
    }
}
