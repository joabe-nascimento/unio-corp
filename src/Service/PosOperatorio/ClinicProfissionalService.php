<?php

namespace App\Service\PosOperatorio;

use App\Entity\ClinicProfissional;
use App\Entity\Empresa;
use App\Entity\User;
use App\Repository\ClinicProfissionalRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

final class ClinicProfissionalService
{
    public function __construct(
        private ClinicProfissionalRepository $profissionais,
        private UserRepository $users,
        private EntityManagerInterface $em,
    ) {}

    /** @return list<ClinicProfissional> */
    public function list(Empresa $empresa, bool $onlyAtivos = false): array
    {
        return $this->profissionais->findByEmpresa($empresa, $onlyAtivos);
    }

    public function findForEmpresa(Empresa $empresa, int $id): ?ClinicProfissional
    {
        return $this->profissionais->findOneByEmpresa($empresa, $id);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(Empresa $empresa, array $data): ClinicProfissional
    {
        $profissional = new ClinicProfissional();
        $profissional->setEmpresa($empresa);
        $this->apply($profissional, $empresa, $data, true);
        $this->em->persist($profissional);
        $this->em->flush();

        return $profissional;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(ClinicProfissional $profissional, Empresa $empresa, array $data): ClinicProfissional
    {
        $this->assertScope($profissional, $empresa);
        $this->apply($profissional, $empresa, $data, false);
        $profissional->touch();
        $this->em->flush();

        return $profissional;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function apply(ClinicProfissional $profissional, Empresa $empresa, array $data, bool $creating): void
    {
        if ($creating || \array_key_exists('nome', $data)) {
            $profissional->setNome(ClinicCadastroRules::requireNome((string) ($data['nome'] ?? ''), 160));
        }
        if ($creating || \array_key_exists('conselho', $data)) {
            $profissional->setConselho(ClinicCadastroRules::normalizeConselho((string) ($data['conselho'] ?? '')));
        }
        if ($creating || \array_key_exists('numero_conselho', $data)) {
            $numero = trim((string) ($data['numero_conselho'] ?? ''));
            if ($numero === '') {
                throw new \InvalidArgumentException('Número do conselho é obrigatório.');
            }
            $profissional->setNumeroConselho(mb_substr($numero, 0, 32));
        }
        if ($creating || \array_key_exists('uf_conselho', $data)) {
            $profissional->setUfConselho(ClinicCadastroRules::normalizeUf(
                isset($data['uf_conselho']) ? (string) $data['uf_conselho'] : null
            ));
        }
        if ($creating || \array_key_exists('especialidade', $data)) {
            $esp = trim((string) ($data['especialidade'] ?? ''));
            $profissional->setEspecialidade($esp === '' ? null : mb_substr($esp, 0, 120));
        }
        if ($creating || \array_key_exists('telefone', $data)) {
            $profissional->setTelefone(ClinicCadastroRules::normalizePhone(
                isset($data['telefone']) ? (string) $data['telefone'] : null
            ));
        }
        if ($creating || \array_key_exists('email', $data)) {
            $email = trim((string) ($data['email'] ?? ''));
            if ($email === '') {
                $profissional->setEmail(null);
            } else {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new \InvalidArgumentException('E-mail inválido.');
                }
                $profissional->setEmail(mb_substr($email, 0, 120));
            }
        }
        if ($creating || \array_key_exists('user_id', $data)) {
            $profissional->setUser($this->resolveUser($empresa, $data['user_id'] ?? null));
        }
        if ($creating || \array_key_exists('ativo', $data)) {
            $profissional->setAtivo(($data['ativo'] ?? true) !== false);
        }
    }

    private function resolveUser(Empresa $empresa, mixed $userId): ?User
    {
        if ($userId === null || $userId === '' || (int) $userId <= 0) {
            return null;
        }
        $user = $this->users->find((int) $userId);
        if (!$user instanceof User || $user->getEmpresa()?->getId() !== $empresa->getId()) {
            throw new \InvalidArgumentException('Usuário fora do escopo da empresa.');
        }

        return $user;
    }

    private function assertScope(ClinicProfissional $profissional, Empresa $empresa): void
    {
        if ($profissional->getEmpresa()->getId() !== $empresa->getId()) {
            throw new \InvalidArgumentException('Profissional fora do escopo.');
        }
    }
}
