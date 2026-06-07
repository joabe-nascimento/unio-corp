<?php

namespace App\Service\Pessoas;

use App\Entity\Empresa;
use App\Entity\Funcionario;
use App\Entity\PessoasAvaliacao;
use App\Entity\User;
use App\Exception\RhProcessException;
use App\Repository\FuncionarioRepository;
use App\Repository\PessoasAvaliacaoRepository;
use Doctrine\ORM\EntityManagerInterface;

class PessoasAvaliacaoService
{
    public function __construct(
        private EntityManagerInterface $em,
        private PessoasAvaliacaoRepository $repo,
        private FuncionarioRepository $funcionarioRepo,
    ) {}

    /** @return list<PessoasAvaliacao> */
    public function list(Empresa $empresa, ?int $funcionarioId = null): array
    {
        return $this->repo->findByEmpresa($empresa, $funcionarioId);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(Empresa $empresa, User $avaliador, array $data): PessoasAvaliacao
    {
        $funcionarioId = (int) ($data['funcionario_id'] ?? 0);
        $periodo = trim((string) ($data['periodo'] ?? ''));
        $notaRaw = trim((string) ($data['nota'] ?? ''));

        if ($funcionarioId <= 0) {
            throw new RhProcessException('Selecione o colaborador avaliado.');
        }
        if ($periodo === '') {
            throw new RhProcessException('Informe o período da avaliação.');
        }
        if ($notaRaw === '' || !is_numeric($notaRaw)) {
            throw new RhProcessException('Informe uma nota válida (0 a 5).');
        }

        $nota = (float) $notaRaw;
        if ($nota < 0 || $nota > 5) {
            throw new RhProcessException('A nota deve estar entre 0 e 5.');
        }

        $funcionario = $this->funcionarioRepo->findOneBy(['id' => $funcionarioId, 'empresa' => $empresa]);
        if (!$funcionario) {
            throw new RhProcessException('Colaborador não encontrado.');
        }

        $avaliacao = new PessoasAvaliacao();
        $avaliacao->setEmpresa($empresa);
        $avaliacao->setFuncionario($funcionario);
        $avaliacao->setAvaliador($avaliador);
        $avaliacao->setPeriodo($periodo);
        $avaliacao->setNota(number_format($nota, 1, '.', ''));
        $avaliacao->setComentario($this->nullIfEmpty($data['comentario'] ?? null));

        $this->em->persist($avaliacao);
        $this->em->flush();

        return $avaliacao;
    }

    /**
     * @return list<array{data: string, evento: string, tipo: string}>
     */
    public function buildHistorico(Funcionario $funcionario): array
    {
        $items = [];

        if ($funcionario->getDataAdmissao()) {
            $items[] = [
                'data' => $funcionario->getDataAdmissao()->format('M/Y'),
                'evento' => 'Admissão na empresa',
                'tipo' => 'admissao',
            ];
        }

        foreach ($this->repo->findByFuncionario($funcionario) as $av) {
            $items[] = [
                'data' => $av->getCriadoEm()->format('M/Y'),
                'evento' => sprintf(
                    'Avaliação de desempenho — %s/5 (%s)',
                    $av->getNota(),
                    $av->getPeriodo()
                ),
                'tipo' => 'avaliacao',
            ];
        }

        usort($items, static fn (array $a, array $b): int => strcmp($a['data'], $b['data']));

        return $items;
    }

    private function nullIfEmpty(mixed $v): ?string
    {
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }
}
