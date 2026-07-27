<?php

namespace App\Controller\Module\Juridico;

use App\Entity\User;
use App\Exception\JuridicoProcessException;
use App\Service\Juridico\JuridicoJurisprudenciaService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/juridico/jurisprudencia')]
#[IsGranted('ROLE_USER')]
class JuridicoJurisprudenciaController extends AbstractController
{
    use JuridicoEmpresaScopeTrait;

    public function __construct(
        private WorkspaceService $workspace,
        private JuridicoJurisprudenciaService $jurisprudencia,
    ) {}

    protected function getWorkspace(): WorkspaceService
    {
        return $this->workspace;
    }

    #[Route('', name: 'app_juridico_jurisprudencia')]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $relevancia = (string) $request->query->get('relevancia', '');
        $q = (string) $request->query->get('q', '');
        $tribunal = (string) $request->query->get('tribunal', '');
        $favoritos = $request->query->getBoolean('favoritos');
        $historicoPagina = $this->jurisprudencia->historicoPagina($empresa, 0, 5);

        return $this->render('modules/juridico/jurisprudencia_list.html.twig', [
            'itens' => $this->jurisprudencia->findForEmpresa($empresa, $relevancia ?: null, $q ?: null, $tribunal ?: null, $favoritos),
            'filter_relevancia' => $relevancia,
            'filter_q' => $q,
            'filter_tribunal' => $tribunal,
            'filter_favoritos' => $favoritos,
            'open_novo' => $request->query->getBoolean('open_novo'),
            'stats' => $this->jurisprudencia->estatisticas($empresa),
            'historico' => $historicoPagina['itens'],
            'historico_tem_mais' => $historicoPagina['hasMore'],
        ]);
    }

    #[Route('/historico', name: 'app_juridico_jurisprudencia_historico', methods: ['GET'])]
    public function historico(Request $request): JsonResponse
    {
        $empresa = $this->requireEmpresa();
        $offset = max(0, $request->query->getInt('offset', 0));
        $pagina = $this->jurisprudencia->historicoPagina($empresa, $offset, 5);

        $itens = array_map(static function ($h) {
            return [
                'tema' => $h->getTema(),
                'tribunal' => $h->getTribunal(),
                'periodo' => $h->getPeriodo(),
                'resultadosCount' => $h->getResultadosCount(),
                'criadoEm' => $h->getCriadoEm()->format('d/m'),
            ];
        }, $pagina['itens']);

        return $this->json(['itens' => $itens, 'hasMore' => $pagina['hasMore']]);
    }

    #[Route('/pesquisar-ia', name: 'app_juridico_jurisprudencia_pesquisar_ia', methods: ['POST'])]
    public function pesquisarIa(Request $request): JsonResponse
    {
        $empresa = $this->requireEmpresa();
        $payload = json_decode($request->getContent(), true);
        $payload = \is_array($payload) ? $payload : [];

        if (!$this->isCsrfTokenValid('juridico_jurisprudencia_ia', (string) ($payload['_token'] ?? ''))) {
            return $this->json(['error' => 'Token de segurança inválido.'], Response::HTTP_FORBIDDEN);
        }

        $tema = trim((string) ($payload['tema'] ?? ''));
        $tribunal = trim((string) ($payload['tribunal'] ?? 'Todos'));
        $periodo = trim((string) ($payload['periodo'] ?? ''));

        /** @var User|null $user */
        $user = $this->getUser();

        try {
            $resultado = $this->jurisprudencia->buscarComIA($empresa, $tema, $tribunal ?: 'Todos', $periodo, 'Geral', $user);

            return $this->json($resultado);
        } catch (JuridicoProcessException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/{id}/favoritar', name: 'app_juridico_jurisprudencia_favoritar', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function favoritar(int $id, Request $request): JsonResponse
    {
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'juridico_jurisprudencia_favoritar_' . $id);

        try {
            $item = $this->jurisprudencia->toggleFavorito($empresa, $id);

            return $this->json(['favorito' => $item->isFavorito()]);
        } catch (JuridicoProcessException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('/salvar-ia', name: 'app_juridico_jurisprudencia_salvar_ia', methods: ['POST'])]
    public function salvarIa(Request $request): JsonResponse
    {
        $empresa = $this->requireEmpresa();
        $payload = json_decode($request->getContent(), true);
        $payload = \is_array($payload) ? $payload : [];

        if (!$this->isCsrfTokenValid('juridico_jurisprudencia_ia', (string) ($payload['_token'] ?? ''))) {
            return $this->json(['error' => 'Token de segurança inválido.'], Response::HTTP_FORBIDDEN);
        }

        /** @var User|null $user */
        $user = $this->getUser();

        try {
            $item = $this->jurisprudencia->salvarSugestao($empresa, $payload['sugestao'] ?? [], $user);

            return $this->json(['id' => $item->getId()]);
        } catch (JuridicoProcessException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/novo', name: 'app_juridico_jurisprudencia_novo', methods: ['GET', 'POST'])]
    public function novo(Request $request): Response
    {
        $empresa = $this->requireEmpresa();

        if ($request->isMethod('GET')) {
            return $this->redirectToRoute('app_juridico_jurisprudencia', ['open_novo' => 1]);
        }

        /** @var User|null $user */
        $user = $this->getUser();

        try {
            $this->requireCsrf($request, 'juridico_jurisprudencia_form');
            $this->jurisprudencia->create($empresa, $request->request->all(), $user);
            $this->addFlash('success', 'Registro adicionado.');
        } catch (JuridicoProcessException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_juridico_jurisprudencia');
    }

    #[Route('/{id}/editar', name: 'app_juridico_jurisprudencia_editar', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function editar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $item = $this->jurisprudencia->loadForEmpresa($empresa, $id);

        if ($request->isMethod('POST')) {
            try {
                $this->requireCsrf($request, 'juridico_jurisprudencia_form');
                $this->jurisprudencia->update($item, $request->request->all());
                $this->addFlash('success', 'Registro atualizado.');

                return $this->redirectToRoute('app_juridico_jurisprudencia');
            } catch (JuridicoProcessException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('modules/juridico/jurisprudencia_editar.html.twig', [
            'item' => $item,
        ]);
    }

    #[Route('/{id}/excluir', name: 'app_juridico_jurisprudencia_excluir', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function excluir(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $item = $this->jurisprudencia->loadForEmpresa($empresa, $id);
        $this->requireCsrf($request, 'juridico_jurisprudencia_excluir_' . $id);
        $this->jurisprudencia->delete($item);
        $this->addFlash('success', 'Registro excluído.');

        return $this->redirectToRoute('app_juridico_jurisprudencia');
    }
}
