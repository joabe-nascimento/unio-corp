<?php

namespace App\Controller\Api\Interno;

use App\Entity\Empresa;
use App\Repository\EmpresaRepository;
use App\Repository\JuridicoClienteRepository;
use App\Repository\JuridicoPrazoRepository;
use App\Repository\JuridicoProcessoRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * API interna servidor-a-servidor usada pelo agente do JurisFlow AI para consultar
 * dados reais do escritório (processos, prazos, clientes).
 *
 * Diferente da API pública (`/api/v1/publica`, autenticada por token de terceiros
 * escopado a UM escritório), esta API é chamada apenas pelo próprio JurisFlow e
 * autenticada por um segredo compartilhado fixo (`LEGAL_AI_INTERNAL_SECRET`),
 * recebendo o `escritorio_id` como parâmetro explícito — não há sessão de usuário
 * nem token por tenant nessa chamada.
 *
 * Nunca deve ser exposta em documentação pública nem usada por clientes externos.
 */
#[Route('/api/v1/interno')]
final class AiInternalApiController extends AbstractController
{
    public function __construct(
        private readonly string $internalSecret,
        private readonly EmpresaRepository $empresaRepo,
        private readonly JuridicoProcessoRepository $processoRepo,
        private readonly JuridicoPrazoRepository $prazoRepo,
        private readonly JuridicoClienteRepository $clienteRepo,
    ) {
    }

    #[Route('/processos', name: 'api_interno_processos', methods: ['GET'])]
    public function processos(Request $request): JsonResponse
    {
        $this->assertSecret($request);
        $empresa = $this->requireEmpresa($request);

        $numero = (string) $request->query->get('numero', '');
        $q = (string) $request->query->get('q', '');

        $encontrados = $this->processoRepo->findForEmpresa($empresa, null, $numero ?: ($q ?: null));
        $limit = min(50, max(1, $request->query->getInt('limit', 20)));
        $encontrados = \array_slice($encontrados, 0, $limit);

        return $this->json([
            'data' => array_map(static fn ($p) => [
                'numero' => $p->getNumero(),
                'area' => $p->getArea(),
                'fase' => $p->getFase(),
                'tribunal' => $p->getTribunal(),
                'status' => $p->getStatus(),
                'valorCausa' => $p->getValor(),
                'cliente' => ['nome' => $p->getCliente()?->getNome()],
            ], $encontrados),
        ]);
    }

    #[Route('/prazos', name: 'api_interno_prazos', methods: ['GET'])]
    public function prazos(Request $request): JsonResponse
    {
        $this->assertSecret($request);
        $empresa = $this->requireEmpresa($request);

        $dias = max(1, $request->query->getInt('dias', 7));
        $limite = (new \DateTimeImmutable('today'))->modify('+' . $dias . ' days');

        $prazos = $this->prazoRepo->findForEmpresa($empresa, 'pendentes');
        $prazos = array_values(array_filter($prazos, static fn ($p) => $p->getDataLimite() <= $limite));
        $prazos = \array_slice($prazos, 0, 20);

        return $this->json([
            'data' => array_map(static fn ($p) => [
                'descricao' => $p->getDescricao(),
                'dataVencimento' => $p->getDataLimite()->format('Y-m-d'),
                'vencido' => $p->getDataLimite() < new \DateTimeImmutable('today'),
                'processo' => $p->getProcesso() ? ['numero' => $p->getProcesso()->getNumero()] : null,
            ], $prazos),
        ]);
    }

    #[Route('/prazos/processo/{numero}', name: 'api_interno_prazos_processo', requirements: ['numero' => '.+'])]
    public function prazosDoProcesso(string $numero, Request $request): JsonResponse
    {
        $this->assertSecret($request);
        $empresa = $this->requireEmpresa($request);

        $prazos = $this->prazoRepo->findForEmpresa($empresa, null, $numero);
        $prazos = array_values(array_filter(
            $prazos,
            static fn ($p) => $p->getProcesso()?->getNumero() === $numero,
        ));

        return $this->json([
            'data' => array_map(static fn ($p) => [
                'descricao' => $p->getDescricao(),
                'dataVencimento' => $p->getDataLimite()->format('Y-m-d'),
                'vencido' => !$p->isCumprido() && $p->getDataLimite() < new \DateTimeImmutable('today'),
            ], $prazos),
        ]);
    }

    #[Route('/clientes', name: 'api_interno_clientes', methods: ['GET'])]
    public function clientes(Request $request): JsonResponse
    {
        $this->assertSecret($request);
        $empresa = $this->requireEmpresa($request);

        $nome = (string) $request->query->get('nome', '');
        if (trim($nome) === '') {
            return $this->json(['data' => []]);
        }

        $clientes = $this->clienteRepo->findForEmpresa($empresa, null, $nome);
        $clientes = \array_slice($clientes, 0, 10);

        return $this->json([
            'data' => array_map(static fn ($c) => [
                'nome' => $c->getNome(),
                'cpfCnpj' => $c->getDocumento(),
                'email' => $c->getEmail(),
                'telefone' => $c->getTelefone(),
            ], $clientes),
        ]);
    }

    private function assertSecret(Request $request): void
    {
        // Preferimos o header X-Internal-Secret, mas alguns hosts (ex.: LiteSpeed/CGI
        // na HostGator) descartam headers HTTP customizados antes de chegar ao PHP —
        // por isso aceitamos também o segredo via query string como alternativa.
        $recebido = (string) $request->headers->get('X-Internal-Secret', '');
        if ($recebido === '') {
            $recebido = (string) $request->query->get('internal_secret', '');
        }

        if ($this->internalSecret === '' || !hash_equals($this->internalSecret, $recebido)) {
            throw new AccessDeniedHttpException('Segredo interno inválido.');
        }
    }

    private function requireEmpresa(Request $request): Empresa
    {
        $escritorioId = (int) $request->query->get('escritorio_id', 0);
        $empresa = $escritorioId > 0 ? $this->empresaRepo->find($escritorioId) : null;

        if ($empresa === null) {
            throw new BadRequestHttpException('escritorio_id inválido ou não informado.');
        }

        return $empresa;
    }
}
