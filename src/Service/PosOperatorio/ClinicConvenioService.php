<?php

namespace App\Service\PosOperatorio;

use App\Entity\ClinicConvenio;
use App\Entity\Empresa;
use App\Repository\ClinicConvenioRepository;
use Doctrine\ORM\EntityManagerInterface;

final class ClinicConvenioService
{
    public function __construct(
        private ClinicConvenioRepository $convenios,
        private EntityManagerInterface $em,
    ) {}

    /** @return list<ClinicConvenio> */
    public function list(Empresa $empresa, bool $onlyAtivos = false): array
    {
        return $this->convenios->findByEmpresa($empresa, $onlyAtivos);
    }

    public function findForEmpresa(Empresa $empresa, int $id): ?ClinicConvenio
    {
        return $this->convenios->findOneByEmpresa($empresa, $id);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(Empresa $empresa, array $data): ClinicConvenio
    {
        $convenio = new ClinicConvenio();
        $convenio->setEmpresa($empresa);
        $this->apply($convenio, $data, true);

        $this->em->persist($convenio);
        $this->em->flush();

        return $convenio;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(ClinicConvenio $convenio, Empresa $empresa, array $data): ClinicConvenio
    {
        $this->assertScope($convenio, $empresa);
        $this->apply($convenio, $data, false);
        $convenio->touch();
        $this->em->flush();

        return $convenio;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function apply(ClinicConvenio $convenio, array $data, bool $creating): void
    {
        if ($creating || \array_key_exists('nome', $data)) {
            $convenio->setNome(ClinicCadastroRules::requireNome((string) ($data['nome'] ?? ''), 180));
        }
        if ($creating || \array_key_exists('registro_ans', $data)) {
            $convenio->setRegistroAns($this->nullableTruncated($data['registro_ans'] ?? null, 20));
        }
        if ($creating || \array_key_exists('cnpj', $data)) {
            $convenio->setCnpj(ClinicCadastroRules::normalizeCnpj(isset($data['cnpj']) ? (string) $data['cnpj'] : null));
        }
        if ($creating || \array_key_exists('codigo_prestador', $data)) {
            $convenio->setCodigoPrestador($this->nullableTruncated($data['codigo_prestador'] ?? null, 40));
        }
        if ($creating || \array_key_exists('versao_tiss', $data)) {
            $convenio->setVersaoTiss(ClinicCadastroRules::normalizeVersaoTiss(
                isset($data['versao_tiss']) ? (string) $data['versao_tiss'] : null
            ));
        }
        if ($creating || \array_key_exists('contato_faturamento', $data)) {
            $convenio->setContatoFaturamento($this->nullableTruncated($data['contato_faturamento'] ?? null, 120));
        }
        if ($creating || \array_key_exists('email_faturamento', $data)) {
            $convenio->setEmailFaturamento(ClinicCadastroRules::normalizeEmail(
                isset($data['email_faturamento']) ? (string) $data['email_faturamento'] : null
            ));
        }
        if ($creating || \array_key_exists('telefone_faturamento', $data)) {
            $convenio->setTelefoneFaturamento(ClinicCadastroRules::normalizePhone(
                isset($data['telefone_faturamento']) ? (string) $data['telefone_faturamento'] : null
            ));
        }
        if ($creating || \array_key_exists('prazo_glosa_dias', $data)) {
            $dias = (int) ($data['prazo_glosa_dias'] ?? 30);
            $convenio->setPrazoGlosaDias(ClinicCadastroRules::assertPrazoGlosa($dias > 0 ? $dias : 30));
        }
        if ($creating || \array_key_exists('observacoes', $data)) {
            $obs = trim((string) ($data['observacoes'] ?? ''));
            $convenio->setObservacoes($obs === '' ? null : $obs);
        }
        if ($creating || \array_key_exists('ativo', $data)) {
            $convenio->setAtivo(($data['ativo'] ?? true) !== false);
        }
    }

    private function nullableTruncated(mixed $value, int $max): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : mb_substr($text, 0, $max);
    }

    private function assertScope(ClinicConvenio $convenio, Empresa $empresa): void
    {
        if ($convenio->getEmpresa()->getId() !== $empresa->getId()) {
            throw new \InvalidArgumentException('Convênio fora do escopo.');
        }
    }
}
