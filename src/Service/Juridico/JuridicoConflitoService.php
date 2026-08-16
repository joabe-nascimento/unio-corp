<?php

namespace App\Service\Juridico;

use App\Entity\Empresa;
use App\Entity\JuridicoConflitoCheck;
use App\Entity\JuridicoProcessoParte;
use App\Exception\JuridicoProcessException;
use App\Repository\JuridicoClienteRepository;
use App\Repository\JuridicoConflitoCheckRepository;
use App\Repository\JuridicoProcessoParteRepository;
use Doctrine\ORM\EntityManagerInterface;

final class JuridicoConflitoService
{
    public function __construct(
        private EntityManagerInterface $em,
        private JuridicoProcessoParteRepository $parteRepo,
        private JuridicoClienteRepository $clienteRepo,
        private JuridicoConflitoCheckRepository $checkRepo,
    ) {
    }

    /**
     * @return array{resultado: string, matches: list<array{tipo: string, nome: string, detalhe: string}>}
     */
    public function verificar(Empresa $empresa, string $nome, ?int $processoId = null, ?int $clienteId = null): array
    {
        $nome = trim($nome);
        if ($nome === '') {
            throw new JuridicoProcessException('Informe o nome da parte ou cliente para verificar conflito.');
        }

        $needle = $this->normalizar($nome);
        $matches = [];

        foreach ($this->clienteRepo->findBy(['empresa' => $empresa]) as $cliente) {
            similar_text($needle, $this->normalizar($cliente->getNome()), $pct);
            if ($pct >= 82 || str_contains($this->normalizar($cliente->getNome()), $needle) || str_contains($needle, $this->normalizar($cliente->getNome()))) {
                $matches[] = [
                    'tipo' => 'cliente',
                    'nome' => $cliente->getNome(),
                    'detalhe' => sprintf('Cliente cadastrado (similaridade %d%%)', (int) $pct),
                ];
            }
        }

        foreach ($this->parteRepo->createQueryBuilder('pt')
            ->join('pt.processo', 'p')
            ->andWhere('p.empresa = :e')
            ->setParameter('e', $empresa)
            ->setMaxResults(400)
            ->getQuery()
            ->getResult() as $parte) {
            /** @var JuridicoProcessoParte $parte */
            similar_text($needle, $this->normalizar($parte->getNome()), $pct);
            if ($pct < 86) {
                continue;
            }
            $matches[] = [
                'tipo' => $parte->getTipo(),
                'nome' => $parte->getNome(),
                'detalhe' => sprintf('Parte %s no processo %s', $parte->getTipo(), $parte->getProcesso()->getNumero()),
            ];
        }

        $resultado = $matches === []
            ? JuridicoConflitoCheck::RESULTADO_LIVRE
            : (count($matches) > 2 ? JuridicoConflitoCheck::RESULTADO_BLOQUEIO : JuridicoConflitoCheck::RESULTADO_ALERTA);

        $check = (new JuridicoConflitoCheck())
            ->setEmpresa($empresa)
            ->setNomeConsultado($nome)
            ->setResultado($resultado)
            ->setDetalhes(['matches' => $matches]);
        $this->em->persist($check);
        $this->em->flush();

        return ['resultado' => $resultado, 'matches' => $matches, 'check_id' => $check->getId()];
    }

    private function normalizar(string $nome): string
    {
        $n = mb_strtolower($nome);
        $n = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $n) ?: $n;

        return preg_replace('/[^a-z0-9]+/', '', $n) ?? $n;
    }
}
