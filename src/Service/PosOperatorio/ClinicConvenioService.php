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
     * @param array{nome?: string, registro_ans?: string|null, ativo?: bool} $data
     */
    public function create(Empresa $empresa, array $data): ClinicConvenio
    {
        $nome = trim((string) ($data['nome'] ?? ''));
        if ($nome === '') {
            throw new \InvalidArgumentException('Nome do convênio é obrigatório.');
        }

        $convenio = new ClinicConvenio();
        $convenio->setEmpresa($empresa);
        $convenio->setNome(mb_substr($nome, 0, 180));
        $convenio->setRegistroAns($this->nullableAns($data['registro_ans'] ?? null));
        $convenio->setAtivo(($data['ativo'] ?? true) !== false);

        $this->em->persist($convenio);
        $this->em->flush();

        return $convenio;
    }

    /**
     * @param array{nome?: string, registro_ans?: string|null, ativo?: bool} $data
     */
    public function update(ClinicConvenio $convenio, Empresa $empresa, array $data): ClinicConvenio
    {
        $this->assertScope($convenio, $empresa);

        if (\array_key_exists('nome', $data)) {
            $nome = trim((string) $data['nome']);
            if ($nome === '') {
                throw new \InvalidArgumentException('Nome do convênio é obrigatório.');
            }
            $convenio->setNome(mb_substr($nome, 0, 180));
        }
        if (\array_key_exists('registro_ans', $data)) {
            $convenio->setRegistroAns($this->nullableAns($data['registro_ans']));
        }
        if (\array_key_exists('ativo', $data)) {
            $convenio->setAtivo((bool) $data['ativo']);
        }

        $convenio->touch();
        $this->em->flush();

        return $convenio;
    }

    private function nullableAns(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : mb_substr($text, 0, 20);
    }

    private function assertScope(ClinicConvenio $convenio, Empresa $empresa): void
    {
        if ($convenio->getEmpresa()->getId() !== $empresa->getId()) {
            throw new \InvalidArgumentException('Convênio fora do escopo.');
        }
    }
}
