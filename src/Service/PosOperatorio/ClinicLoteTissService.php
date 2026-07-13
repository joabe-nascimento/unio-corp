<?php

namespace App\Service\PosOperatorio;

use App\Entity\ClinicConvenio;
use App\Entity\ClinicGuiaTiss;
use App\Entity\ClinicLoteTiss;
use App\Entity\Empresa;
use App\Repository\ClinicConvenioRepository;
use App\Repository\ClinicGuiaTissRepository;
use App\Repository\ClinicLoteTissRepository;
use Doctrine\ORM\EntityManagerInterface;

final class ClinicLoteTissService
{
    public function __construct(
        private ClinicLoteTissRepository $lotes,
        private ClinicGuiaTissRepository $guias,
        private ClinicConvenioRepository $convenios,
        private EntityManagerInterface $em,
    ) {}

    /** @return list<ClinicLoteTiss> */
    public function list(Empresa $empresa, ?string $status = null): array
    {
        return $this->lotes->findByEmpresaAndStatus($empresa, $status);
    }

    public function countList(Empresa $empresa, ?string $status = null): int
    {
        return $this->lotes->countByEmpresaAndStatus($empresa, $status);
    }

    public function listLimit(): int
    {
        return ClinicLoteTissRepository::LIST_LIMIT;
    }

    public function findForEmpresa(Empresa $empresa, int $id): ?ClinicLoteTiss
    {
        return $this->lotes->findOneByEmpresa($empresa, $id);
    }

    public function create(Empresa $empresa, ClinicConvenio $convenio, ?string $competencia = null, ?string $numero = null): ClinicLoteTiss
    {
        if ($convenio->getEmpresa()->getId() !== $empresa->getId() || !$convenio->isAtivo()) {
            throw new \InvalidArgumentException('Convênio inválido.');
        }

        $comp = $this->normalizeCompetencia($competencia);
        $lote = new ClinicLoteTiss();
        $lote->setEmpresa($empresa);
        $lote->setConvenio($convenio);
        $lote->setCompetencia($comp);
        $lote->setNumero($this->resolveNumero($numero, $empresa, $comp));
        $lote->setStatus(ClinicLoteTiss::STATUS_ABERTO);

        $this->em->persist($lote);
        $this->em->flush();

        return $lote;
    }

    public function addGuia(ClinicLoteTiss $lote, Empresa $empresa, int $guiaId): ClinicGuiaTiss
    {
        $this->assertScope($lote, $empresa);
        if (!$lote->isAberto()) {
            throw new \InvalidArgumentException('Só lotes abertos aceitam novas guias.');
        }

        $guia = $this->guias->findOneByEmpresa($empresa, $guiaId);
        if ($guia === null) {
            throw new \InvalidArgumentException('Guia não encontrada.');
        }
        if ($guia->getConvenio()->getId() !== $lote->getConvenio()->getId()) {
            throw new \InvalidArgumentException('A guia pertence a outro convênio.');
        }
        if ($guia->getLote() !== null) {
            throw new \InvalidArgumentException('Esta guia já está em um lote.');
        }
        if ($guia->getItens()->isEmpty()) {
            throw new \InvalidArgumentException('A guia precisa ter ao menos um item.');
        }
        if (!\in_array($guia->getStatus(), [ClinicGuiaTiss::STATUS_RASCUNHO, ClinicGuiaTiss::STATUS_ENVIADO], true)) {
            throw new \InvalidArgumentException('Só guias em rascunho ou enviadas podem entrar no lote.');
        }

        $guia->setLote($lote);
        if (!$lote->getGuias()->contains($guia)) {
            $lote->getGuias()->add($guia);
        }
        $guia->touch();
        $lote->touch();
        $this->em->flush();

        return $guia;
    }

    public function removeGuia(ClinicLoteTiss $lote, Empresa $empresa, int $guiaId): void
    {
        $this->assertScope($lote, $empresa);
        if (!$lote->isAberto()) {
            throw new \InvalidArgumentException('Só lotes abertos permitem remover guias.');
        }

        foreach ($lote->getGuias() as $guia) {
            if ($guia->getId() === $guiaId) {
                $lote->removeGuia($guia);
                $guia->touch();
                $lote->touch();
                $this->em->flush();

                return;
            }
        }

        throw new \InvalidArgumentException('Guia não pertence a este lote.');
    }

    public function fechar(ClinicLoteTiss $lote, Empresa $empresa): ClinicLoteTiss
    {
        $this->assertScope($lote, $empresa);
        if (!$lote->isAberto()) {
            throw new \InvalidArgumentException('Lote já está fechado.');
        }
        if ($lote->getGuias()->isEmpty()) {
            throw new \InvalidArgumentException('Inclua ao menos uma guia antes de fechar o lote.');
        }

        foreach ($lote->getGuias() as $guia) {
            if ($guia->getItens()->isEmpty()) {
                throw new \InvalidArgumentException(sprintf('Guia %s sem itens.', $guia->getNumeroGuia()));
            }
            if ($guia->getStatus() === ClinicGuiaTiss::STATUS_RASCUNHO) {
                $guia->setStatus(ClinicGuiaTiss::STATUS_ENVIADO);
                $guia->touch();
            }
        }

        $lote->setStatus(ClinicLoteTiss::STATUS_FECHADO);
        $lote->touch();
        $this->em->flush();

        return $lote;
    }

    public function marcarEnviado(ClinicLoteTiss $lote, Empresa $empresa): ClinicLoteTiss
    {
        $this->assertScope($lote, $empresa);
        if ($lote->getStatus() !== ClinicLoteTiss::STATUS_FECHADO) {
            throw new \InvalidArgumentException('Feche o lote antes de marcar como enviado à operadora.');
        }
        if ($lote->getGuias()->isEmpty()) {
            throw new \InvalidArgumentException('Lote sem guias.');
        }

        $lote->setStatus(ClinicLoteTiss::STATUS_ENVIADO);
        $lote->touch();
        $this->em->flush();

        return $lote;
    }

    /** @return list<ClinicGuiaTiss> */
    public function guiasElegiveis(Empresa $empresa, ClinicLoteTiss $lote): array
    {
        $this->assertScope($lote, $empresa);

        return $this->lotes->findGuiasElegiveis($empresa, $lote->getConvenio());
    }

    public function requireConvenio(Empresa $empresa, int $id): ClinicConvenio
    {
        $convenio = $this->convenios->findOneByEmpresa($empresa, $id);
        if ($convenio === null || !$convenio->isAtivo()) {
            throw new \InvalidArgumentException('Convênio não encontrado.');
        }

        return $convenio;
    }

    /** @return array<string, string> */
    public static function statusLabels(): array
    {
        return [
            ClinicLoteTiss::STATUS_ABERTO => 'Aberto',
            ClinicLoteTiss::STATUS_FECHADO => 'Fechado',
            ClinicLoteTiss::STATUS_ENVIADO => 'Enviado',
        ];
    }

    private function normalizeCompetencia(?string $raw): string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return (new \DateTimeImmutable())->format('Y-m');
        }
        if (preg_match('/^\d{4}-\d{2}$/', $raw)) {
            return $raw;
        }
        if (preg_match('/^\d{6}$/', $raw)) {
            return substr($raw, 0, 4).'-'.substr($raw, 4, 2);
        }

        throw new \InvalidArgumentException('Competência inválida. Use AAAA-MM.');
    }

    private function resolveNumero(?string $numero, Empresa $empresa, string $competencia): string
    {
        $numero = trim((string) $numero);
        if ($numero !== '') {
            return mb_substr($numero, 0, 40);
        }

        return sprintf('L%s-%s-%s', $empresa->getId() ?? 0, str_replace('-', '', $competencia), (new \DateTimeImmutable())->format('His'));
    }

    private function assertScope(ClinicLoteTiss $lote, Empresa $empresa): void
    {
        if ($lote->getEmpresa()->getId() !== $empresa->getId()) {
            throw new \InvalidArgumentException('Lote fora do escopo.');
        }
    }
}
