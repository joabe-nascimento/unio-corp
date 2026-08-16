<?php

namespace App\Service\Juridico;

use App\Doctrine\DateNormalizer;
use App\Entity\Empresa;
use App\Entity\JuridicoAudiencia;
use App\Entity\JuridicoProcessoEvento;
use App\Exception\JuridicoProcessException;
use App\Repository\JuridicoAudienciaRepository;
use App\Repository\JuridicoProcessoRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

final class JuridicoAudienciaService
{
    public function __construct(
        private EntityManagerInterface $em,
        private JuridicoAudienciaRepository $repo,
        private JuridicoProcessoRepository $processoRepo,
        private UserRepository $userRepo,
        private JuridicoProcessoTimelineService $timeline,
        private JuridicoWebhookDispatcher $webhooks,
        private JurisFlowAiClient $ai,
    ) {
    }

    public function loadForEmpresa(Empresa $empresa, int $id): JuridicoAudiencia
    {
        $aud = $this->repo->findOneByEmpresa($empresa, $id);
        if (!$aud) {
            throw new JuridicoProcessException('Audiência não encontrada.');
        }

        return $aud;
    }

    /** @param array<string, mixed> $data */
    public function create(Empresa $empresa, array $data): JuridicoAudiencia
    {
        $aud = new JuridicoAudiencia();
        $aud->setEmpresa($empresa);
        $this->apply($empresa, $aud, $data);
        $this->em->persist($aud);
        $this->em->flush();

        if ($aud->getProcesso() !== null) {
            $this->timeline->registrar(
                $aud->getProcesso(),
                JuridicoProcessoEvento::TIPO_AUDIENCIA,
                'Audiência: '.$aud->getTipo(),
                $aud->getDataHora()->format('d/m/Y H:i'),
                'audiencia',
                $aud->getId(),
            );
        }
        $this->webhooks->dispatch($empresa, 'audiencia.agendada', [
            'id' => $aud->getId(),
            'tipo' => $aud->getTipo(),
            'data_hora' => $aud->getDataHora()->format(DATE_ATOM),
        ]);

        return $aud;
    }

    /** @param array<string, mixed> $data */
    public function update(JuridicoAudiencia $aud, array $data): void
    {
        $this->apply($aud->getEmpresa(), $aud, $data);
        $aud->touch();
        $this->em->flush();
    }

    public function gerarRoteiro(JuridicoAudiencia $aud): string
    {
        $desc = sprintf(
            'Audiência %s em %s. Processo %s. Área %s.',
            $aud->getTipo(),
            $aud->getDataHora()->format('d/m/Y H:i'),
            $aud->getProcesso()?->getNumero() ?? 'sem processo',
            $aud->getProcesso()?->getArea() ?? 'geral',
        );
        $roteiro = $this->ai->gerarMinuta('roteiro_audiencia', $desc, (string) $aud->getEmpresa()->getId())
            ?: "Checklist sugerido:\n1. Confirmar testemunhas\n2. Organizar documentos\n3. Revisar teses\n4. Testar link da sala virtual";
        $aud->setRoteiro($roteiro)->touch();
        $this->em->flush();

        return $roteiro;
    }

    /** @param array<string, mixed> $data */
    private function apply(Empresa $empresa, JuridicoAudiencia $aud, array $data): void
    {
        $tipo = trim((string) ($data['tipo'] ?? ''));
        if ($tipo === '') {
            throw new JuridicoProcessException('Informe o tipo da audiência.');
        }
        $dataHora = DateNormalizer::fromFormDateTime($data['data_hora'] ?? $data['data'] ?? null)
            ?? DateNormalizer::fromFormDate($data['data'] ?? null);
        if (!$dataHora) {
            throw new JuridicoProcessException('Informe data e hora da audiência.');
        }

        $aud->setTipo($tipo);
        $aud->setDataHora($dataHora);
        $aud->setLocal($this->nullIfEmpty($data['local'] ?? null));
        $aud->setLinkVirtual($this->nullIfEmpty($data['link_virtual'] ?? null));
        $aud->setStatus((string) ($data['status'] ?? JuridicoAudiencia::STATUS_AGENDADA));
        $aud->setAta($this->nullIfEmpty($data['ata'] ?? null));

        $processoId = (int) ($data['processo_id'] ?? 0);
        $aud->setProcesso($processoId > 0 ? $this->processoRepo->findOneByEmpresa($empresa, $processoId) : null);

        $respId = (int) ($data['responsavel_id'] ?? 0);
        $aud->setResponsavel($respId > 0 ? $this->userRepo->findOneBy(['id' => $respId, 'empresa' => $empresa]) : null);

        if (!empty($data['checklist_texto'])) {
            $itens = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $data['checklist_texto']) ?: [])));
            $aud->setChecklist(array_map(static fn (string $i) => ['item' => $i, 'feito' => false], $itens));
        }
    }

    private function nullIfEmpty(mixed $v): ?string
    {
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }
}
