<?php

namespace App\Controller\Module\Juridico;

use App\Entity\JuridicoTribunalConfig;
use App\Repository\JuridicoTribunalConfigRepository;
use App\Service\Juridico\DataJud\DataJudClient;
use App\Service\Juridico\DataJud\DataJudException;
use App\Service\WorkspaceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Hub de integração com tribunais — configura a chave da API Pública do DataJud (CNJ),
 * que agrega oficialmente os dados de PJe, e-SAJ, Projudi, EPROC e demais sistemas.
 */
#[Route('/juridico/tribunais')]
#[IsGranted('ROLE_USER')]
class JuridicoTribunaisController extends AbstractController
{
    use JuridicoEmpresaScopeTrait;

    public function __construct(
        private WorkspaceService $workspace,
        private JuridicoTribunalConfigRepository $configRepo,
        private DataJudClient $datajud,
        private EntityManagerInterface $em,
    ) {
    }

    protected function getWorkspace(): WorkspaceService
    {
        return $this->workspace;
    }

    #[Route('', name: 'app_juridico_tribunais')]
    public function index(): Response
    {
        $empresa = $this->requireEmpresa();
        $config = $this->configRepo->findByEmpresa($empresa);

        return $this->render('modules/juridico/tribunais.html.twig', [
            'config' => $config,
        ]);
    }

    #[Route('/configurar', name: 'app_juridico_tribunais_configurar', methods: ['POST'])]
    #[IsGranted('ROLE_GESTOR')]
    public function configurar(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'juridico_tribunal_config');

        $config = $this->configRepo->findByEmpresa($empresa) ?? (new JuridicoTribunalConfig())->setEmpresa($empresa);
        $apiKey = trim((string) $request->request->get('datajud_api_key', ''));

        if ($apiKey !== '') {
            $config->setDatajudApiKey($apiKey);
            $config->touch();
            $this->em->persist($config);
            $this->em->flush();

            $teste = $this->datajud->testarChave($apiKey);
            $config->registrarTeste($teste['ok'], $teste['mensagem']);
            $this->em->flush();

            $this->addFlash($teste['ok'] ? 'success' : 'error', $teste['mensagem']);
        } else {
            $this->addFlash('error', 'Informe a chave de API do DataJud.');
        }

        return $this->redirectToRoute('app_juridico_tribunais');
    }

    #[Route('/testar', name: 'app_juridico_tribunais_testar', methods: ['POST'])]
    #[IsGranted('ROLE_GESTOR')]
    public function testar(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'juridico_tribunal_testar');

        $config = $this->configRepo->findByEmpresa($empresa);
        if ($config === null || !$config->isConfigurado()) {
            $this->addFlash('error', 'Configure a chave de API do DataJud antes de testar.');

            return $this->redirectToRoute('app_juridico_tribunais');
        }

        $teste = $this->datajud->testarChave((string) $config->getDatajudApiKey());
        $config->registrarTeste($teste['ok'], $teste['mensagem']);
        $this->em->flush();
        $this->addFlash($teste['ok'] ? 'success' : 'error', $teste['mensagem']);

        return $this->redirectToRoute('app_juridico_tribunais');
    }

    #[Route('/consultar', name: 'app_juridico_tribunais_consultar', methods: ['POST'])]
    public function consultar(Request $request): JsonResponse
    {
        $empresa = $this->requireEmpresa();
        $numero = trim((string) $request->request->get('numero', ''));

        if (!$this->isCsrfTokenValid('juridico_datajud_ajax', (string) $request->request->get('_token'))) {
            return $this->json(['error' => 'Token de segurança inválido — recarregue a página.'], Response::HTTP_FORBIDDEN);
        }

        $config = $this->configRepo->findByEmpresa($empresa);
        if ($config === null || !$config->isConfigurado()) {
            return $this->json(['error' => 'Configure a chave de API do DataJud primeiro.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $resultado = $this->datajud->consultarProcesso($numero, (string) $config->getDatajudApiKey());
            $config->registrarConsulta();
            $this->em->flush();

            return $this->json($resultado);
        } catch (DataJudException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
