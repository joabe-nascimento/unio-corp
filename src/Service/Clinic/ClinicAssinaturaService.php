<?php

namespace App\Service\Clinic;

use App\Entity\ClinicAssinaturaDocumento;
use App\Entity\Empresa;
use App\Entity\PosOperatorioPaciente;
use App\Repository\ClinicAssinaturaDocumentoRepository;
use Doctrine\ORM\EntityManagerInterface;

final class ClinicAssinaturaService
{
    public function __construct(
        private ClinicAssinaturaDocumentoRepository $documentos,
        private EntityManagerInterface $em,
    ) {}

    /** @return array<string, string> */
    public static function statusLabels(): array
    {
        return [
            ClinicAssinaturaDocumento::STATUS_PENDENTE_MEDICO => 'Pendente do médico',
            ClinicAssinaturaDocumento::STATUS_PENDENTE_PACIENTE => 'Pendente do paciente',
            ClinicAssinaturaDocumento::STATUS_NA_FILA => 'Na fila',
            ClinicAssinaturaDocumento::STATUS_CONCLUIDA => 'Concluída',
            ClinicAssinaturaDocumento::STATUS_CANCELADA => 'Cancelada',
        ];
    }

    /** @return array<string, string> */
    public static function tipoLabels(): array
    {
        return [
            ClinicAssinaturaDocumento::TIPO_CONSENTIMENTO => 'Consentimento',
            ClinicAssinaturaDocumento::TIPO_CONTRATO => 'Contrato',
            ClinicAssinaturaDocumento::TIPO_ANAMNESE => 'Anamnese',
            ClinicAssinaturaDocumento::TIPO_ALTA => 'Alta',
        ];
    }

    public function create(
        Empresa $empresa,
        string $titulo,
        string $tipo = ClinicAssinaturaDocumento::TIPO_CONSENTIMENTO,
        ?PosOperatorioPaciente $paciente = null,
        string $status = ClinicAssinaturaDocumento::STATUS_PENDENTE_PACIENTE,
    ): ClinicAssinaturaDocumento {
        $titulo = trim($titulo);
        if ($titulo === '') {
            throw new \InvalidArgumentException('Informe o título do documento.');
        }

        if (!\array_key_exists($tipo, self::tipoLabels())) {
            $tipo = ClinicAssinaturaDocumento::TIPO_CONSENTIMENTO;
        }

        if (!\array_key_exists($status, self::statusLabels())) {
            $status = ClinicAssinaturaDocumento::STATUS_PENDENTE_PACIENTE;
        }

        $doc = (new ClinicAssinaturaDocumento())
            ->setEmpresa($empresa)
            ->setPaciente($paciente)
            ->setTitulo($titulo)
            ->setTipo($tipo)
            ->setStatus($status);

        $this->em->persist($doc);
        $this->em->flush();

        return $doc;
    }

    /** @return array{items: list<array<string, mixed>>, counts: array<string, int>, open: int} */
    public function dashboardSummary(Empresa $empresa, int $limit = 6): array
    {
        $counts = $this->documentos->countByStatusForEmpresa($empresa);
        $open = $this->documentos->countOpenByEmpresa($empresa);
        $items = array_map(
            fn (ClinicAssinaturaDocumento $d): array => $this->mapRow($d),
            $this->documentos->findOpenByEmpresa($empresa, null, $limit),
        );

        return [
            'items' => $items,
            'counts' => $counts,
            'open' => $open,
        ];
    }

    /** @return list<ClinicAssinaturaDocumento> */
    public function listForEmpresa(Empresa $empresa, ?string $status = null): array
    {
        if ($status === 'concluidas') {
            return $this->documentos->findOpenByEmpresa($empresa, ClinicAssinaturaDocumento::STATUS_CONCLUIDA, 100);
        }

        return $this->documentos->findOpenByEmpresa($empresa, $status, 100);
    }

    public function advanceStatus(ClinicAssinaturaDocumento $doc): void
    {
        $next = match ($doc->getStatus()) {
            ClinicAssinaturaDocumento::STATUS_PENDENTE_PACIENTE => ClinicAssinaturaDocumento::STATUS_PENDENTE_MEDICO,
            ClinicAssinaturaDocumento::STATUS_PENDENTE_MEDICO => ClinicAssinaturaDocumento::STATUS_NA_FILA,
            ClinicAssinaturaDocumento::STATUS_NA_FILA => ClinicAssinaturaDocumento::STATUS_CONCLUIDA,
            default => ClinicAssinaturaDocumento::STATUS_CONCLUIDA,
        };

        $doc->setStatus($next);
        if ($next === ClinicAssinaturaDocumento::STATUS_CONCLUIDA) {
            $doc->setAssinadoEm(new \DateTimeImmutable());
        }

        $this->em->flush();
    }

    public function cancel(ClinicAssinaturaDocumento $doc): void
    {
        $doc->setStatus(ClinicAssinaturaDocumento::STATUS_CANCELADA);
        $this->em->flush();
    }

    /** @return array<string, mixed> */
    private function mapRow(ClinicAssinaturaDocumento $d): array
    {
        return [
            'id' => $d->getId(),
            'titulo' => $d->getTitulo(),
            'tipo' => self::tipoLabels()[$d->getTipo()] ?? $d->getTipo(),
            'status' => $d->getStatus(),
            'status_label' => self::statusLabels()[$d->getStatus()] ?? $d->getStatus(),
            'paciente' => $d->getPaciente()?->getNome(),
            'paciente_id' => $d->getPaciente()?->getId(),
            'criado_em' => $d->getCriadoEm()->format('d/m/Y'),
        ];
    }
}
