<?php

namespace App\Service\Ti;

use App\Entity\Empresa;
use App\Entity\TiAtivo;
use App\Entity\TiIntegracao;
use App\Entity\TiLicenca;
use App\Entity\TiManutencao;
use App\Repository\TiAtivoRepository;
use App\Repository\TiIntegracaoRepository;
use App\Repository\TiLicencaRepository;
use App\Repository\TiManutencaoRepository;
use Doctrine\ORM\EntityManagerInterface;

final class TiInfraManageService
{
    public function __construct(
        private EntityManagerInterface $em,
        private TiAtivoRepository $ativoRepo,
        private TiLicencaRepository $licencaRepo,
        private TiIntegracaoRepository $integracaoRepo,
        private TiManutencaoRepository $manutencaoRepo,
    ) {}

    public function loadAtivo(Empresa $empresa, int $id): TiAtivo
    {
        return $this->load($this->ativoRepo->findOneForEmpresa($empresa, $id), 'Ativo');
    }

    public function loadLicenca(Empresa $empresa, int $id): TiLicenca
    {
        return $this->load($this->licencaRepo->findOneForEmpresa($empresa, $id), 'Licença');
    }

    public function loadIntegracao(Empresa $empresa, int $id): TiIntegracao
    {
        return $this->load($this->integracaoRepo->findOneForEmpresa($empresa, $id), 'Integração');
    }

    public function loadManutencao(Empresa $empresa, int $id): TiManutencao
    {
        return $this->load($this->manutencaoRepo->findOneForEmpresa($empresa, $id), 'Manutenção');
    }

    /** @param array<string, mixed> $data */
    public function createAtivo(Empresa $empresa, array $data): TiAtivo
    {
        $codigo = $this->requireString($data, 'codigo', 'Código');
        $this->assertUniqueCodigo($empresa, $codigo);

        $ativo = (new TiAtivo())
            ->setEmpresa($empresa)
            ->setCodigo($codigo);
        $this->applyAtivoForm($ativo, $data);

        $this->em->persist($ativo);
        $this->em->flush();

        return $ativo;
    }

    /** @param array<string, mixed> $data */
    public function updateAtivo(TiAtivo $ativo, array $data): void
    {
        $codigo = $this->requireString($data, 'codigo', 'Código');
        $existing = $this->ativoRepo->findByCodigoForEmpresa($ativo->getEmpresa(), $codigo);
        if ($existing !== null && $existing->getId() !== $ativo->getId()) {
            throw new \InvalidArgumentException('Já existe um ativo com este código.');
        }

        $ativo->setCodigo($codigo);
        $this->applyAtivoForm($ativo, $data);
        $ativo->touch();
        $this->em->flush();
    }

    public function deleteAtivo(TiAtivo $ativo): void
    {
        $this->em->remove($ativo);
        $this->em->flush();
    }

    /** @param array<string, mixed> $data */
    public function createLicenca(Empresa $empresa, array $data): TiLicenca
    {
        $lic = (new TiLicenca())->setEmpresa($empresa);
        $this->applyLicencaForm($lic, $data);

        $this->em->persist($lic);
        $this->em->flush();

        return $lic;
    }

    /** @param array<string, mixed> $data */
    public function updateLicenca(TiLicenca $lic, array $data): void
    {
        $this->applyLicencaForm($lic, $data);
        $this->em->flush();
    }

    public function deleteLicenca(TiLicenca $lic): void
    {
        $this->em->remove($lic);
        $this->em->flush();
    }

    /** @param array<string, mixed> $data */
    public function createIntegracao(Empresa $empresa, array $data): TiIntegracao
    {
        $int = (new TiIntegracao())->setEmpresa($empresa);
        $this->applyIntegracaoForm($int, $data);

        $this->em->persist($int);
        $this->em->flush();

        return $int;
    }

    /** @param array<string, mixed> $data */
    public function updateIntegracao(TiIntegracao $int, array $data): void
    {
        $this->applyIntegracaoForm($int, $data);
        $this->em->flush();
    }

    public function deleteIntegracao(TiIntegracao $int): void
    {
        $this->em->remove($int);
        $this->em->flush();
    }

    /** @param array<string, mixed> $data */
    public function createManutencao(Empresa $empresa, array $data): TiManutencao
    {
        $man = (new TiManutencao())->setEmpresa($empresa);
        $this->applyManutencaoForm($man, $data);

        $this->em->persist($man);
        $this->em->flush();

        return $man;
    }

    /** @param array<string, mixed> $data */
    public function updateManutencao(TiManutencao $man, array $data): void
    {
        $this->applyManutencaoForm($man, $data);
        $this->em->flush();
    }

    public function deleteManutencao(TiManutencao $man): void
    {
        $this->em->remove($man);
        $this->em->flush();
    }

    public function approveManutencao(TiManutencao $man, string $actorName): void
    {
        $man->setAprovada(true)
            ->setAprovadaEm(new \DateTimeImmutable())
            ->setAprovadaPor($actorName)
            ->setStatus(TiManutencao::STATUS_APPROVED);
        $this->em->flush();
    }

    /** @param array<string, mixed> $data */
    private function applyAtivoForm(TiAtivo $ativo, array $data): void
    {
        $tipo = $this->requireString($data, 'tipo', 'Tipo');
        $modelo = $this->requireString($data, 'modelo', 'Modelo');
        $status = (string) ($data['status'] ?? TiAtivo::STATUS_ATIVO);
        if (!\in_array($status, [TiAtivo::STATUS_ATIVO, TiAtivo::STATUS_MANUTENCAO, TiAtivo::STATUS_ESTOQUE], true)) {
            throw new \InvalidArgumentException('Status inválido.');
        }

        $responsavel = trim((string) ($data['responsavel'] ?? ''));
        $ciclo = max(0, min(100, (int) ($data['ciclo_pct'] ?? 0)));

        $ativo
            ->setTipo($tipo)
            ->setModelo($modelo)
            ->setResponsavel($responsavel !== '' ? $responsavel : null)
            ->setStatus($status)
            ->setCicloPct($ciclo);
    }

    /** @param array<string, mixed> $data */
    private function applyLicencaForm(TiLicenca $lic, array $data): void
    {
        $nome = $this->requireString($data, 'nome', 'Software');
        $seats = max(1, (int) ($data['seats'] ?? 1));
        $used = max(0, (int) ($data['used'] ?? 0));
        if ($used > $seats) {
            throw new \InvalidArgumentException('Seats utilizados não pode exceder o total contratado.');
        }

        $custo = $this->parseDecimal($data['custo_mensal'] ?? '0');
        $renovacao = $this->parseDate($data['renovacao_em'] ?? '', 'Renovação');

        $lic
            ->setNome($nome)
            ->setSeats($seats)
            ->setUsed($used)
            ->setCustoMensal(number_format($custo, 2, '.', ''))
            ->setRenovacaoEm($renovacao);
    }

    /** @param array<string, mixed> $data */
    private function applyIntegracaoForm(TiIntegracao $int, array $data): void
    {
        $nome = $this->requireString($data, 'nome', 'Nome');
        $status = (string) ($data['status'] ?? TiIntegracao::STATUS_HEALTHY);
        if (!\in_array($status, [TiIntegracao::STATUS_HEALTHY, TiIntegracao::STATUS_DEGRADED, TiIntegracao::STATUS_DOWN], true)) {
            throw new \InvalidArgumentException('Status inválido.');
        }

        $latencia = trim((string) ($data['latencia'] ?? '—'));
        $uptime = max(0, min(100, $this->parseDecimal($data['uptime'] ?? '99.9')));
        $eventos = max(0, (int) ($data['eventos_24h'] ?? 0));

        $int
            ->setNome($nome)
            ->setStatus($status)
            ->setLatencia($latencia !== '' ? $latencia : '—')
            ->setUptime(number_format($uptime, 2, '.', ''))
            ->setEventos24h($eventos);
    }

    /** @param array<string, mixed> $data */
    private function applyManutencaoForm(TiManutencao $man, array $data): void
    {
        $titulo = $this->requireString($data, 'titulo', 'Título');
        $janela = $this->requireString($data, 'janela', 'Janela');
        $impacto = $this->requireString($data, 'impacto', 'Impacto');
        $owner = $this->requireString($data, 'owner', 'Responsável');
        $status = (string) ($data['status'] ?? TiManutencao::STATUS_SCHEDULED);
        if (!\in_array($status, [TiManutencao::STATUS_SCHEDULED, TiManutencao::STATUS_APPROVED, TiManutencao::STATUS_DONE], true)) {
            throw new \InvalidArgumentException('Status inválido.');
        }

        $rawServicos = trim((string) ($data['servicos_afetados'] ?? ''));
        $servicosAfetados = $rawServicos !== ''
            ? array_values(array_filter(array_map('trim', explode(',', $rawServicos))))
            : [];

        $man
            ->setTitulo($titulo)
            ->setJanela($janela)
            ->setImpacto($impacto)
            ->setOwner($owner)
            ->setStatus($status)
            ->setServicosAfetados($servicosAfetados);
    }

    private function assertUniqueCodigo(Empresa $empresa, string $codigo): void
    {
        if ($this->ativoRepo->findByCodigoForEmpresa($empresa, $codigo) !== null) {
            throw new \InvalidArgumentException('Já existe um ativo com este código.');
        }
    }

    /** @param array<string, mixed> $data */
    private function requireString(array $data, string $key, string $label): string
    {
        $value = trim((string) ($data[$key] ?? ''));
        if ($value === '') {
            throw new \InvalidArgumentException($label . ' é obrigatório.');
        }

        return $value;
    }

    private function parseDecimal(mixed $value): float
    {
        $raw = trim(str_replace(',', '.', (string) $value));
        if ($raw === '' || !is_numeric($raw)) {
            return 0.0;
        }

        return (float) $raw;
    }

    private function parseDate(string $value, string $label): \DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            throw new \InvalidArgumentException($label . ' é obrigatória.');
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value)
            ?: \DateTimeImmutable::createFromFormat('d/m/Y', $value);
        if (!$date) {
            throw new \InvalidArgumentException($label . ' inválida.');
        }

        return $date;
    }

    /** @template T of object */
    private function load(?object $item, string $label): object
    {
        if ($item === null) {
            throw new \InvalidArgumentException($label . ' não encontrado(a).');
        }

        return $item;
    }
}
