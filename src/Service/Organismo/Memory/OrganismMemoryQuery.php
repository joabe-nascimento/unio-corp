<?php

namespace App\Service\Organismo\Memory;

use App\Entity\Empresa;
use App\Entity\Organismo\OrganismoMemoryFact;
use App\Entity\PosOperatorioPaciente;
use App\Repository\Organismo\OrganismoMemoryFactRepository;

final class OrganismMemoryQuery
{
    public function __construct(
        private OrganismoMemoryFactRepository $facts,
    ) {
    }

    /**
     * @return array{
     *   recent: list<array{tipo: string, sujeito: string, peso: int, criado_em: string}>,
     *   patterns: list<array{tipo: string, total: int, label: string}>
     * }
     */
    public function forEmpresa(Empresa $empresa, int $limit = 8): array
    {
        $recent = array_map(
            static fn (OrganismoMemoryFact $f): array => [
                'tipo' => $f->getTipo(),
                'sujeito' => $f->getSujeito(),
                'peso' => $f->getPeso(),
                'criado_em' => $f->getCriadoEm()->format('d/m H:i'),
            ],
            $this->facts->findRecent($empresa, $limit),
        );

        $patterns = array_map(
            fn (array $p): array => [
                'tipo' => $p['tipo'],
                'total' => $p['total'],
                'label' => $this->labelForTipo($p['tipo']),
            ],
            $this->facts->topPatterns($empresa, 5),
        );

        return ['recent' => $recent, 'patterns' => $patterns];
    }

    /** @return list<array{tipo: string, sujeito: string, peso: int, criado_em: string}> */
    public function forPaciente(PosOperatorioPaciente $paciente, int $limit = 6): array
    {
        return array_map(
            static fn (OrganismoMemoryFact $f): array => [
                'tipo' => $f->getTipo(),
                'sujeito' => $f->getSujeito(),
                'peso' => $f->getPeso(),
                'criado_em' => $f->getCriadoEm()->format('d/m H:i'),
            ],
            $this->facts->findForPaciente($paciente, $limit),
        );
    }

    public function labelForTipo(string $tipo): string
    {
        return match ($tipo) {
            'reflexo' => 'Reflexos disparados',
            'alerta' => 'Alertas clínicos',
            'questionario_atrasado' => 'Questionários em atraso',
            'contrato_atestado' => 'Marcos de cuidado atestados',
            'contrato_criado' => 'Contratos de cuidado',
            'risco_cascata' => 'Riscos previstos pelo gêmeo',
            'conta_orfa' => 'Contas órfãs',
            default => $tipo,
        };
    }
}
