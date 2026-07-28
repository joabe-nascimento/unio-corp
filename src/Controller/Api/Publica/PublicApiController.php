<?php

namespace App\Controller\Api\Publica;

use App\Entity\ApiToken;
use App\Repository\JuridicoJurisprudenciaRepository;
use App\Repository\JuridicoPrazoRepository;
use App\Repository\JuridicoProcessoRepository;
use App\Repository\JuridicoProcessoTarefaRepository;
use App\Security\ApiTokenUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * API Pública do Unio Jurídico (v1) — integrações externas autenticadas por token
 * de API (`Authorization: Bearer ujr_live_...`), escopadas ao escritório dono do token.
 *
 * Documentação legível em /juridico/api-publica.
 */
#[Route('/api/v1/publica')]
final class PublicApiController extends AbstractController
{
    public function __construct(
        private JuridicoProcessoRepository $processoRepo,
        private JuridicoPrazoRepository $prazoRepo,
        private JuridicoProcessoTarefaRepository $tarefaRepo,
        private JuridicoJurisprudenciaRepository $jurisprudenciaRepo,
    ) {
    }

    #[Route('/me', name: 'api_publica_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $token = $this->tokenAtual();

        return $this->json([
            'escritorio' => $token->getEmpresa()->getNome(),
            'escopos' => $token->getScopes(),
            'total_requisicoes' => $token->getTotalRequisicoes(),
            'criado_em' => $token->getCriadoEm()->format(DATE_ATOM),
        ]);
    }

    #[Route('/processos', name: 'api_publica_processos', methods: ['GET'])]
    public function processos(Request $request): JsonResponse
    {
        $empresa = $this->tokenAtual()->getEmpresa();
        $status = (string) $request->query->get('status', '');
        $q = (string) $request->query->get('q', '');

        $processos = $this->processoRepo->findForEmpresa($empresa, $status ?: null, $q ?: null);
        $limit = min(100, max(1, $request->query->getInt('limit', 50)));
        $processos = \array_slice($processos, 0, $limit);

        return $this->json([
            'total' => \count($processos),
            'data' => array_map(static fn ($p) => [
                'id' => $p->getId(),
                'numero' => $p->getNumero(),
                'area' => $p->getArea(),
                'fase' => $p->getFase(),
                'tribunal' => $p->getTribunal(),
                'status' => $p->getStatus(),
                'resultado' => $p->getResultado(),
                'valor' => $p->getValor(),
                'cliente' => $p->getCliente()?->getNome(),
                'responsavel' => $p->getResponsavel()?->getNome(),
                'criado_em' => $p->getCriadoEm()->format(DATE_ATOM),
                'atualizado_em' => $p->getAtualizadoEm()?->format(DATE_ATOM),
            ], $processos),
        ]);
    }

    #[Route('/processos/{numero}', name: 'api_publica_processo_show', requirements: ['numero' => '.+'])]
    public function processo(string $numero): JsonResponse
    {
        $empresa = $this->tokenAtual()->getEmpresa();
        $encontrados = $this->processoRepo->findForEmpresa($empresa, null, $numero);
        $processo = $encontrados[0] ?? null;

        if ($processo === null || $processo->getNumero() !== $numero) {
            return $this->json(['error' => 'Processo não encontrado.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $processo->getId(),
            'numero' => $processo->getNumero(),
            'area' => $processo->getArea(),
            'fase' => $processo->getFase(),
            'tribunal' => $processo->getTribunal(),
            'status' => $processo->getStatus(),
            'resultado' => $processo->getResultado(),
            'valor' => $processo->getValor(),
            'observacoes' => $processo->getObservacoes(),
            'cliente' => $processo->getCliente()?->getNome(),
            'responsavel' => $processo->getResponsavel()?->getNome(),
            'criado_em' => $processo->getCriadoEm()->format(DATE_ATOM),
        ]);
    }

    #[Route('/prazos', name: 'api_publica_prazos', methods: ['GET'])]
    public function prazos(Request $request): JsonResponse
    {
        $empresa = $this->tokenAtual()->getEmpresa();
        $situacao = (string) $request->query->get('situacao', 'pendentes');
        $prazos = $this->prazoRepo->findForEmpresa($empresa, \in_array($situacao, ['pendentes', 'cumpridos'], true) ? $situacao : null);
        $limit = min(100, max(1, $request->query->getInt('limit', 50)));
        $prazos = \array_slice($prazos, 0, $limit);

        return $this->json([
            'total' => \count($prazos),
            'data' => array_map(static fn ($p) => [
                'id' => $p->getId(),
                'tipo' => $p->getTipo(),
                'descricao' => $p->getDescricao(),
                'data_limite' => $p->getDataLimite()->format(DATE_ATOM),
                'cumprido' => $p->isCumprido(),
                'processo' => $p->getProcesso()?->getNumero(),
                'responsavel' => $p->getResponsavel()?->getNome(),
            ], $prazos),
        ]);
    }

    #[Route('/tarefas', name: 'api_publica_tarefas', methods: ['GET'])]
    public function tarefas(): JsonResponse
    {
        $empresa = $this->tokenAtual()->getEmpresa();
        $tarefas = $this->tarefaRepo->findPendentesForEmpresa($empresa);

        return $this->json([
            'total' => \count($tarefas),
            'data' => array_map(static fn ($t) => [
                'id' => $t->getId(),
                'titulo' => $t->getTitulo(),
                'prazo' => $t->getPrazo()?->format(DATE_ATOM),
                'status' => $t->getStatus(),
                'processo' => $t->getProcesso()->getNumero(),
                'responsavel' => $t->getResponsavel()?->getNome(),
            ], \array_slice($tarefas, 0, 100)),
        ]);
    }

    #[Route('/jurisprudencia', name: 'api_publica_jurisprudencia', methods: ['GET'])]
    public function jurisprudencia(Request $request): JsonResponse
    {
        $empresa = $this->tokenAtual()->getEmpresa();
        $q = (string) $request->query->get('q', '');
        $tribunal = (string) $request->query->get('tribunal', '');

        $itens = $this->jurisprudenciaRepo->findForEmpresa($empresa, null, $q ?: null, $tribunal ?: null);
        $limit = min(100, max(1, $request->query->getInt('limit', 30)));
        $itens = \array_slice($itens, 0, $limit);

        return $this->json([
            'total' => \count($itens),
            'data' => array_map(static fn ($j) => [
                'id' => $j->getId(),
                'tribunal' => $j->getTribunal(),
                'tema' => $j->getTema(),
                'resultado' => $j->getResultado(),
                'relevancia' => $j->getRelevancia(),
                'referencia' => $j->getReferencia(),
                'resumo' => $j->getResumo(),
                'favorito' => $j->isFavorito(),
            ], $itens),
        ]);
    }

    private function tokenAtual(): ApiToken
    {
        /** @var ApiTokenUser $user */
        $user = $this->getUser();

        return $user->getToken();
    }
}
