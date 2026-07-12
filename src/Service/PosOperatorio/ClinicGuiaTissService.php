<?php

namespace App\Service\PosOperatorio;

use App\Entity\ClinicConta;
use App\Entity\ClinicConvenio;
use App\Entity\ClinicGuiaItem;
use App\Entity\ClinicGuiaTiss;
use App\Entity\Empresa;
use App\Repository\ClinicConvenioRepository;
use App\Repository\ClinicGuiaTissRepository;
use Doctrine\ORM\EntityManagerInterface;

final class ClinicGuiaTissService
{
    public function __construct(
        private ClinicGuiaTissRepository $guias,
        private ClinicConvenioRepository $convenios,
        private ClinicContaService $contas,
        private EntityManagerInterface $em,
    ) {}

    /** @return list<ClinicGuiaTiss> */
    public function list(Empresa $empresa, ?string $status = null): array
    {
        return $this->guias->findByEmpresaAndStatus($empresa, $status);
    }

    public function findForEmpresa(Empresa $empresa, int $id): ?ClinicGuiaTiss
    {
        return $this->guias->findOneByEmpresa($empresa, $id);
    }

    public function findByConta(Empresa $empresa, ClinicConta $conta): ?ClinicGuiaTiss
    {
        return $this->guias->findOneByConta($empresa, $conta);
    }

    public function convertContaToConvenio(ClinicConta $conta, Empresa $empresa, ClinicConvenio $convenio, ?string $numeroGuia = null): ClinicGuiaTiss
    {
        $this->contas->assertScopePublic($conta, $empresa);
        if ($convenio->getEmpresa()->getId() !== $empresa->getId() || !$convenio->isAtivo()) {
            throw new \InvalidArgumentException('Convênio inválido.');
        }
        if (!$conta->isAberto()) {
            throw new \InvalidArgumentException('Só contas abertas podem virar convênio.');
        }

        $existing = $this->guias->findOneByConta($empresa, $conta);
        if ($existing !== null) {
            return $existing;
        }

        $conta->setTipo(ClinicConta::TIPO_CONVENIO);
        $conta->setConvenio($convenio);
        if ($conta->getDescricao()) {
            $conta->setDescricao($conta->getDescricao().' · '.$convenio->getNome());
        } else {
            $conta->setDescricao('Convênio: '.$convenio->getNome());
        }
        $conta->touch();

        $guia = new ClinicGuiaTiss();
        $guia->setEmpresa($empresa);
        $guia->setConta($conta);
        $guia->setAtendimento($conta->getAtendimento());
        $guia->setPaciente($conta->getPaciente());
        $guia->setConvenio($convenio);
        $guia->setStatus(ClinicGuiaTiss::STATUS_RASCUNHO);
        $guia->setNumeroGuia($this->resolveNumeroGuia($numeroGuia, $conta));

        $this->em->persist($guia);
        $this->em->flush();

        return $guia;
    }

    /**
     * @param array{numero_guia?: string, senha_autorizacao?: string|null} $data
     */
    public function updateCabecalho(ClinicGuiaTiss $guia, Empresa $empresa, array $data): ClinicGuiaTiss
    {
        $this->assertScope($guia, $empresa);
        if (!$guia->isEditable()) {
            throw new \InvalidArgumentException('Guia não pode ser editada neste status.');
        }

        if (\array_key_exists('numero_guia', $data)) {
            $numero = trim((string) $data['numero_guia']);
            if ($numero === '') {
                throw new \InvalidArgumentException('Número da guia é obrigatório.');
            }
            $guia->setNumeroGuia(mb_substr($numero, 0, 40));
        }
        if (\array_key_exists('senha_autorizacao', $data)) {
            $senha = trim((string) ($data['senha_autorizacao'] ?? ''));
            $guia->setSenhaAutorizacao($senha === '' ? null : mb_substr($senha, 0, 40));
        }

        $guia->touch();
        $this->em->flush();

        return $guia;
    }

    /**
     * @param array{codigo_tuss?: string|null, descricao?: string, quantidade?: int, valor_centavos?: int|null} $data
     */
    public function addItem(ClinicGuiaTiss $guia, Empresa $empresa, array $data): ClinicGuiaItem
    {
        $this->assertScope($guia, $empresa);
        if (!$guia->isEditable()) {
            throw new \InvalidArgumentException('Guia não pode receber itens neste status.');
        }

        $descricao = trim((string) ($data['descricao'] ?? ''));
        if ($descricao === '') {
            throw new \InvalidArgumentException('Descrição do item é obrigatória.');
        }

        $qtd = (int) ($data['quantidade'] ?? 1);
        if ($qtd < 1) {
            throw new \InvalidArgumentException('Quantidade inválida.');
        }

        $item = new ClinicGuiaItem();
        $item->setDescricao(mb_substr($descricao, 0, 255));
        $item->setQuantidade($qtd);
        $codigo = trim((string) ($data['codigo_tuss'] ?? ''));
        $item->setCodigoTuss($codigo === '' ? null : mb_substr($codigo, 0, 20));
        $item->setValorCentavos($data['valor_centavos'] ?? null);

        $guia->addItem($item);
        $this->syncContaValor($guia);
        $guia->touch();
        $this->em->persist($item);
        $this->em->flush();

        return $item;
    }

    public function removeItem(ClinicGuiaTiss $guia, Empresa $empresa, int $itemId): void
    {
        $this->assertScope($guia, $empresa);
        if (!$guia->isEditable()) {
            throw new \InvalidArgumentException('Guia não pode ser editada neste status.');
        }

        foreach ($guia->getItens() as $item) {
            if ($item->getId() === $itemId) {
                $guia->removeItem($item);
                $this->em->remove($item);
                $this->syncContaValor($guia);
                $guia->touch();
                $this->em->flush();

                return;
            }
        }

        throw new \InvalidArgumentException('Item não encontrado.');
    }

    public function changeStatus(ClinicGuiaTiss $guia, Empresa $empresa, string $status, ?string $motivoGlosa = null): ClinicGuiaTiss
    {
        $this->assertScope($guia, $empresa);
        if (!\in_array($status, ClinicGuiaTiss::STATUSES, true)) {
            throw new \InvalidArgumentException('Status inválido.');
        }

        $current = $guia->getStatus();
        if ($current === ClinicGuiaTiss::STATUS_CANCELADO || $current === ClinicGuiaTiss::STATUS_PAGO) {
            throw new \InvalidArgumentException('Guia já encerrada.');
        }

        if ($status === ClinicGuiaTiss::STATUS_GLOSADO) {
            $motivo = trim((string) $motivoGlosa);
            if ($motivo === '') {
                throw new \InvalidArgumentException('Informe o motivo da glosa.');
            }
            $guia->setMotivoGlosa(mb_substr($motivo, 0, 8000));
        }

        $guia->setStatus($status);
        $guia->touch();

        $conta = $guia->getConta();
        match ($status) {
            ClinicGuiaTiss::STATUS_PAGO => $this->contas->markPagoFromGuia($conta, $empresa, $guia->totalCentavos() ?: null),
            ClinicGuiaTiss::STATUS_GLOSADO => $this->contas->markGlosado($conta, $empresa),
            ClinicGuiaTiss::STATUS_CANCELADO => $this->contas->cancelFromGuia($conta, $empresa),
            default => null,
        };

        $this->em->flush();

        return $guia;
    }

    /** @return array<string, string> */
    public static function statusLabels(): array
    {
        return [
            ClinicGuiaTiss::STATUS_RASCUNHO => 'Rascunho',
            ClinicGuiaTiss::STATUS_ENVIADO => 'Enviado',
            ClinicGuiaTiss::STATUS_AUTORIZADO => 'Autorizado',
            ClinicGuiaTiss::STATUS_GLOSADO => 'Glosado',
            ClinicGuiaTiss::STATUS_PAGO => 'Pago',
            ClinicGuiaTiss::STATUS_CANCELADO => 'Cancelado',
        ];
    }

    public function requireConvenio(Empresa $empresa, int $id): ClinicConvenio
    {
        $convenio = $this->convenios->findOneByEmpresa($empresa, $id);
        if ($convenio === null || !$convenio->isAtivo()) {
            throw new \InvalidArgumentException('Convênio não encontrado.');
        }

        return $convenio;
    }

    private function resolveNumeroGuia(?string $numeroGuia, ClinicConta $conta): string
    {
        $numero = trim((string) $numeroGuia);
        if ($numero !== '') {
            return mb_substr($numero, 0, 40);
        }

        return 'G'.($conta->getId() ?? 0).'-'.(new \DateTimeImmutable())->format('ymdHis');
    }

    private function syncContaValor(ClinicGuiaTiss $guia): void
    {
        $total = $guia->totalCentavos();
        $conta = $guia->getConta();
        $conta->setValorCentavos($total > 0 ? $total : null);
        $conta->touch();
    }

    private function assertScope(ClinicGuiaTiss $guia, Empresa $empresa): void
    {
        if ($guia->getEmpresa()->getId() !== $empresa->getId()) {
            throw new \InvalidArgumentException('Guia fora do escopo.');
        }
    }
}
