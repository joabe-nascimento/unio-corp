<?php

namespace App\Service\Clinic;

use App\Entity\ClinicAgendaSolicitacao;
use App\Entity\Empresa;
use App\Repository\ClinicAgendaSolicitacaoRepository;
use App\Repository\EmpresaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ClinicPublicBookingService
{
    public function __construct(
        private EmpresaRepository $empresas,
        private ClinicAgendaSolicitacaoRepository $solicitacoes,
        private EntityManagerInterface $em,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function resolveEmpresa(string $slugOrId): ?Empresa
    {
        $slugOrId = trim($slugOrId);
        if ($slugOrId === '') {
            return null;
        }

        if (ctype_digit($slugOrId)) {
            $empresa = $this->empresas->find((int) $slugOrId);

            return $empresa instanceof Empresa && $empresa->isAtivo() ? $empresa : null;
        }

        return $this->empresas->findBySlug($slugOrId);
    }

    public function publicUrl(Empresa $empresa): string
    {
        $slug = trim((string) $empresa->getSlug());
        if ($slug !== '') {
            return $this->urlGenerator->generate(
                'app_clinica_agendar_public',
                ['slug' => $slug],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );
        }

        return $this->urlGenerator->generate(
            'app_clinica_agendar_public_id',
            ['empresaId' => (int) $empresa->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }

    /** @return array<string, string> */
    public static function motivoLabels(): array
    {
        return [
            ClinicAgendaSolicitacao::MOTIVO_CONSULTA => 'Consulta',
            ClinicAgendaSolicitacao::MOTIVO_RETORNO => 'Retorno',
            ClinicAgendaSolicitacao::MOTIVO_AVALIACAO => 'Avaliação',
        ];
    }

    /** @return array<string, string> */
    public static function periodoLabels(): array
    {
        return [
            ClinicAgendaSolicitacao::PERIODO_MANHA => 'Manhã',
            ClinicAgendaSolicitacao::PERIODO_TARDE => 'Tarde',
            ClinicAgendaSolicitacao::PERIODO_INDIFERENTE => 'Indiferente',
        ];
    }

    /**
     * @param array{
     *     nome?: string,
     *     telefone?: string,
     *     email?: string,
     *     motivo?: string,
     *     data_preferida?: string,
     *     periodo?: string,
     *     observacao?: string
     * } $data
     */
    public function submit(Empresa $empresa, array $data): ClinicAgendaSolicitacao
    {
        $nome = trim((string) ($data['nome'] ?? ''));
        $telefone = preg_replace('/\D+/', '', (string) ($data['telefone'] ?? '')) ?? '';
        if ($nome === '' || \strlen($telefone) < 10) {
            throw new \InvalidArgumentException('Informe nome e telefone válidos.');
        }

        $motivo = (string) ($data['motivo'] ?? ClinicAgendaSolicitacao::MOTIVO_CONSULTA);
        if (!\array_key_exists($motivo, self::motivoLabels())) {
            $motivo = ClinicAgendaSolicitacao::MOTIVO_CONSULTA;
        }

        $periodo = (string) ($data['periodo'] ?? ClinicAgendaSolicitacao::PERIODO_INDIFERENTE);
        if (!\array_key_exists($periodo, self::periodoLabels())) {
            $periodo = ClinicAgendaSolicitacao::PERIODO_INDIFERENTE;
        }

        $dataPreferida = null;
        $dataRaw = trim((string) ($data['data_preferida'] ?? ''));
        if ($dataRaw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataRaw)) {
            $dataPreferida = new \DateTimeImmutable($dataRaw);
            if ($dataPreferida < new \DateTimeImmutable('today')) {
                throw new \InvalidArgumentException('A data preferida não pode ser no passado.');
            }
        }

        $email = trim((string) ($data['email'] ?? ''));

        $solicitacao = (new ClinicAgendaSolicitacao())
            ->setEmpresa($empresa)
            ->setNome($nome)
            ->setTelefone($telefone)
            ->setEmail($email !== '' ? $email : null)
            ->setMotivo($motivo)
            ->setDataPreferida($dataPreferida)
            ->setPeriodo($periodo)
            ->setObservacao(trim((string) ($data['observacao'] ?? '')) ?: null)
            ->setStatus(ClinicAgendaSolicitacao::STATUS_PENDENTE);

        $this->em->persist($solicitacao);
        $this->em->flush();

        return $solicitacao;
    }

    /** @return list<array<string, mixed>> */
    public function listPendingRows(Empresa $empresa, int $limit = 8): array
    {
        return array_map(fn (ClinicAgendaSolicitacao $s): array => $this->mapRow($s), $this->solicitacoes->findPendingByEmpresa($empresa, $limit));
    }

    public function countPending(Empresa $empresa): int
    {
        return $this->solicitacoes->countPendingByEmpresa($empresa);
    }

    public function markScheduled(ClinicAgendaSolicitacao $solicitacao): void
    {
        $solicitacao->setStatus(ClinicAgendaSolicitacao::STATUS_AGENDADO);
        $this->em->flush();
    }

    public function markRejected(ClinicAgendaSolicitacao $solicitacao): void
    {
        $solicitacao->setStatus(ClinicAgendaSolicitacao::STATUS_RECUSADO);
        $this->em->flush();
    }

    /** @return array<string, mixed> */
    private function mapRow(ClinicAgendaSolicitacao $s): array
    {
        return [
            'id' => $s->getId(),
            'nome' => $s->getNome(),
            'telefone' => $s->getTelefone(),
            'motivo' => self::motivoLabels()[$s->getMotivo()] ?? $s->getMotivo(),
            'data_preferida' => $s->getDataPreferida()?->format('d/m/Y'),
            'periodo' => self::periodoLabels()[$s->getPeriodo()] ?? $s->getPeriodo(),
            'criado_em' => $s->getCriadoEm()->format('d/m H:i'),
            'observacao' => $s->getObservacao(),
        ];
    }
}
