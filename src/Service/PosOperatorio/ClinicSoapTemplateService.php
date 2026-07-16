<?php

namespace App\Service\PosOperatorio;

use App\Entity\ClinicSoapTemplate;
use App\Entity\Empresa;
use App\Repository\ClinicSoapTemplateRepository;
use Doctrine\ORM\EntityManagerInterface;

final class ClinicSoapTemplateService
{
    public function __construct(
        private ClinicSoapTemplateRepository $templates,
        private EntityManagerInterface $em,
    ) {}

    /** @return list<ClinicSoapTemplate> */
    public function list(Empresa $empresa, bool $onlyAtivos = false): array
    {
        return $this->templates->findByEmpresa($empresa, $onlyAtivos);
    }

    public function findForEmpresa(Empresa $empresa, int $id): ?ClinicSoapTemplate
    {
        return $this->templates->findOneByEmpresa($empresa, $id);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(Empresa $empresa, array $data): ClinicSoapTemplate
    {
        $template = new ClinicSoapTemplate();
        $template->setEmpresa($empresa);
        $this->apply($template, $data, true);
        $this->em->persist($template);
        $this->em->flush();

        return $template;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(ClinicSoapTemplate $template, Empresa $empresa, array $data): ClinicSoapTemplate
    {
        $this->assertScope($template, $empresa);
        $this->apply($template, $data, false);
        $this->em->flush();

        return $template;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function apply(ClinicSoapTemplate $template, array $data, bool $creating): void
    {
        if ($creating || \array_key_exists('nome', $data)) {
            $template->setNome(ClinicCadastroRules::requireNome((string) ($data['nome'] ?? ''), 120));
        }
        if ($creating || \array_key_exists('procedimento_tipo', $data)) {
            $tipo = trim((string) ($data['procedimento_tipo'] ?? ''));
            $template->setProcedimentoTipo($tipo === '' ? null : mb_substr($tipo, 0, 120));
        }
        if ($creating || \array_key_exists('queixa', $data)) {
            $v = trim((string) ($data['queixa'] ?? ''));
            $template->setQueixa($v === '' ? null : $v);
        }
        if ($creating || \array_key_exists('exame', $data)) {
            $v = trim((string) ($data['exame'] ?? ''));
            $template->setExame($v === '' ? null : $v);
        }
        if ($creating || \array_key_exists('hipotese', $data)) {
            $v = trim((string) ($data['hipotese'] ?? ''));
            $template->setHipotese($v === '' ? null : $v);
        }
        if ($creating || \array_key_exists('conduta', $data)) {
            $v = trim((string) ($data['conduta'] ?? ''));
            $template->setConduta($v === '' ? null : $v);
        }
        if ($creating || \array_key_exists('cid10_sugerido', $data)) {
            $cid = trim((string) ($data['cid10_sugerido'] ?? ''));
            $template->setCid10Sugerido($cid === '' ? null : mb_substr($cid, 0, 16));
        }
        if ($creating || \array_key_exists('ativo', $data)) {
            $template->setAtivo(($data['ativo'] ?? true) !== false);
        }
    }

    private function assertScope(ClinicSoapTemplate $template, Empresa $empresa): void
    {
        if ($template->getEmpresa()->getId() !== $empresa->getId()) {
            throw new \InvalidArgumentException('Template SOAP fora do escopo.');
        }
    }
}
