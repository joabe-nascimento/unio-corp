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

    public function countList(Empresa $empresa, ?string $status = null): int
    {
        return $this->guias->countByEmpresaAndStatus($empresa, $status);
    }

    public function listLimit(): int
    {
        return ClinicGuiaTissRepository::LIST_LIMIT;
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
            throw new \InvalidArgumentException('Esta conta já possui guia TISS.');
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
            throw new \InvalidArgumentException('Guia não pode ser editada neste status. Reabra após glosa ou volte ao rascunho.');
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

        if (!\array_key_exists('valor_centavos', $data) || $data['valor_centavos'] === null) {
            throw new \InvalidArgumentException('Informe o valor do item.');
        }
        $valor = (int) $data['valor_centavos'];
        if ($valor < 0) {
            throw new \InvalidArgumentException('Valor inválido.');
        }

        $item = new ClinicGuiaItem();
        $item->setDescricao(mb_substr($descricao, 0, 255));
        $item->setQuantidade($qtd);
        $codigo = trim((string) ($data['codigo_tuss'] ?? ''));
        $item->setCodigoTuss($codigo === '' ? null : mb_substr($codigo, 0, 20));
        $item->setValorCentavos($valor);

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
        $allowed = ClinicGuiaTiss::allowedTransitionsFrom($current);
        if (!\in_array($status, $allowed, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Não é possível ir de “%s” para “%s”.',
                self::statusLabels()[$current] ?? $current,
                self::statusLabels()[$status] ?? $status,
            ));
        }

        if ($status === ClinicGuiaTiss::STATUS_ENVIADO && $guia->getItens()->isEmpty()) {
            throw new \InvalidArgumentException('Adicione ao menos um item antes de marcar como enviada.');
        }

        if ($status === ClinicGuiaTiss::STATUS_PAGO) {
            if ($guia->getItens()->isEmpty()) {
                throw new \InvalidArgumentException('Adicione ao menos um item antes de marcar a guia como paga.');
            }
            if ($guia->totalCentavos() <= 0) {
                throw new \InvalidArgumentException('Informe valor nos itens antes de marcar como paga.');
            }
        }

        if ($status === ClinicGuiaTiss::STATUS_GLOSADO) {
            $motivo = trim((string) $motivoGlosa);
            if ($motivo === '') {
                throw new \InvalidArgumentException('Informe o motivo da glosa.');
            }
            $guia->appendGlosaHistorico($motivo);
        }

        $guia->setStatus($status);
        $guia->touch();

        $conta = $guia->getConta();
        match ($status) {
            ClinicGuiaTiss::STATUS_PAGO => $this->contas->markPagoFromGuia($conta, $empresa, $guia->totalCentavos()),
            ClinicGuiaTiss::STATUS_GLOSADO => $this->contas->markGlosado($conta, $empresa),
            ClinicGuiaTiss::STATUS_CANCELADO => $this->contas->cancelFromGuia($conta, $empresa),
            default => null,
        };

        $this->em->flush();

        return $guia;
    }

    /**
     * Reabre guia glosada para correção e reapresentação (volta a rascunho + conta aberta).
     */
    public function reabrirAposGlosa(ClinicGuiaTiss $guia, Empresa $empresa): ClinicGuiaTiss
    {
        $this->assertScope($guia, $empresa);
        if (!$guia->canReabrirAposGlosa()) {
            throw new \InvalidArgumentException('Só guias glosadas podem ser reabertas.');
        }

        // Mantém histórico; limpa o motivo “atual” para a próxima tentativa
        $guia->setMotivoGlosa(null);
        $guia->setStatus(ClinicGuiaTiss::STATUS_RASCUNHO);
        $guia->touch();
        $this->contas->reabrirAposGlosa($guia->getConta(), $empresa);
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
