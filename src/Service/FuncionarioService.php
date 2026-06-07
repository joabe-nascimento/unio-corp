<?php

namespace App\Service;

use App\Doctrine\DateNormalizer;
use App\Entity\Departamento;
use App\Entity\Empresa;
use App\Entity\Funcionario;
use App\Exception\RhProcessException;
use App\Support\BrPersonFormat;
use App\Repository\DepartamentoRepository;
use App\Repository\FuncionarioRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FuncionarioService
{
    private const UPLOAD_DIR = 'public/uploads/funcionarios';
    private const MAX_PHOTO_BYTES = 2_097_152;
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(
        private EntityManagerInterface $em,
        private FuncionarioRepository $repo,
        private DepartamentoRepository $departamentoRepo,
        private string $projectDir,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function create(Empresa $empresa, array $data, ?UploadedFile $foto = null): Funcionario
    {
        $nome = trim((string) ($data['nome'] ?? ''));
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
        if ($nome === '' || $email === '') {
            throw new RhProcessException('Nome e e-mail são obrigatórios.');
        }
        if (!filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            throw new RhProcessException('Informe um e-mail válido.');
        }
        if ($this->repo->existsByEmail($empresa, $email)) {
            throw new RhProcessException('Já existe um funcionário com este e-mail nesta empresa.');
        }

        $f = new Funcionario();
        $f->setEmpresa($empresa);
        $this->applyData($f, $data);
        $f->setNome($nome);
        $f->setEmail($email);

        if ($foto) {
            $f->setFoto($this->storePhoto($foto));
        }

        $this->em->persist($f);
        $this->em->flush();

        return $f;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Funcionario $f, array $data, ?UploadedFile $foto = null, bool $removeFoto = false): void
    {
        $empresa = $f->getEmpresa();
        if (!$empresa) {
            throw new RhProcessException('Funcionário sem empresa vinculada.');
        }

        $nome = trim((string) ($data['nome'] ?? ''));
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
        if ($nome === '' || $email === '') {
            throw new RhProcessException('Nome e e-mail são obrigatórios.');
        }
        if (!filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            throw new RhProcessException('Informe um e-mail válido.');
        }
        if ($this->repo->existsByEmail($empresa, $email, $f->getId())) {
            throw new RhProcessException('Já existe outro funcionário com este e-mail nesta empresa.');
        }

        $f->setNome($nome);
        $f->setEmail($email);
        $this->applyData($f, $data);

        if ($removeFoto) {
            $this->deletePhotoFile($f->getFoto());
            $f->setFoto(null);
        }
        if ($foto) {
            $this->deletePhotoFile($f->getFoto());
            $f->setFoto($this->storePhoto($foto));
        }

        $this->em->flush();
    }

    public function findForEmpresa(Empresa $empresa, ?string $status = null, ?string $q = null): array
    {
        return $this->repo->findForEmpresa($empresa, $status, $q);
    }

    /** @return list<Funcionario> */
    public function findForPessoas(
        Empresa $empresa,
        ?string $status = null,
        ?string $q = null,
        ?int $departamentoId = null,
        ?string $cargo = null,
    ): array {
        return $this->repo->findForEmpresaFiltered($empresa, $status, $q, $departamentoId, $cargo);
    }

    public function loadForEmpresa(Empresa $empresa, int $id): Funcionario
    {
        $f = $this->repo->findOneBy(['id' => $id, 'empresa' => $empresa]);
        if (!$f) {
            throw new RhProcessException('Funcionário não encontrado.');
        }

        return $f;
    }

    /** @return list<Departamento> */
    public function listDepartamentos(Empresa $empresa): array
    {
        return $this->departamentoRepo->findByEmpresa($empresa);
    }

    /** @return list<Funcionario> */
    public function listGestores(Empresa $empresa, ?int $excludeId = null): array
    {
        return $this->repo->findGestoresForSelect($empresa, $excludeId);
    }

    /** @return list<string> */
    public function listDistinctCargos(Empresa $empresa): array
    {
        return $this->repo->findDistinctCargos($empresa);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyData(Funcionario $f, array $data): void
    {
        $f->setTelefone(BrPersonFormat::digitsOnly($this->nullIfEmpty($data['telefone'] ?? null)));
        $f->setCargo($this->nullIfEmpty($data['cargo'] ?? null));
        $f->setStatus((string) ($data['status'] ?? $f->getStatus() ?: 'ATIVO'));
        $f->setNivelMaturidade($this->nullIfEmpty($data['nivel_maturidade'] ?? null));
        $f->setRg($this->nullIfEmpty($data['rg'] ?? null));
        $f->setTipoContrato($this->nullIfEmpty($data['tipo_contrato'] ?? null));
        $f->setLogradouro($this->nullIfEmpty($data['logradouro'] ?? null));
        $f->setNumero($this->nullIfEmpty($data['numero'] ?? null));
        $f->setComplemento($this->nullIfEmpty($data['complemento'] ?? null));
        $f->setBairro($this->nullIfEmpty($data['bairro'] ?? null));
        $f->setCidade($this->nullIfEmpty($data['cidade'] ?? null));
        $f->setBanco($this->nullIfEmpty($data['banco'] ?? null));
        $f->setAgencia($this->nullIfEmpty($data['agencia'] ?? null));
        $f->setConta($this->nullIfEmpty($data['conta'] ?? null));
        $f->setPix($this->nullIfEmpty($data['pix'] ?? null));
        $f->setObservacoes($this->nullIfEmpty($data['observacoes'] ?? null));

        $competenciasRaw = trim((string) ($data['competencias'] ?? ''));
        if ($competenciasRaw !== '') {
            $items = array_values(array_filter(array_map('trim', preg_split('/[,;\n]+/', $competenciasRaw) ?: [])));
            $f->setCompetencias($items !== [] ? $items : null);
        } elseif (\array_key_exists('competencias', $data)) {
            $f->setCompetencias(null);
        }

        $uf = strtoupper(trim((string) ($data['uf'] ?? '')));
        $f->setUf($uf !== '' ? $uf : null);

        $cep = BrPersonFormat::digitsOnly($this->nullIfEmpty($data['cep'] ?? null));
        $f->setCep($cep);

        $cpf = BrPersonFormat::digitsOnly($this->nullIfEmpty($data['cpf'] ?? null));
        if ($cpf !== null) {
            if (!BrPersonFormat::isValidCpf($cpf)) {
                throw new RhProcessException('CPF inválido.');
            }
            $empresa = $f->getEmpresa();
            if ($empresa && $this->repo->existsByCpf($empresa, $cpf, $f->getId())) {
                throw new RhProcessException('Já existe um funcionário com este CPF nesta empresa.');
            }
        }
        $f->setCpf($cpf);

        $salario = BrPersonFormat::parseMoney($data['salario'] ?? null);
        $f->setSalario($salario);

        $f->setDataAdmissao(DateNormalizer::fromFormDate($data['data_admissao'] ?? null));
        $f->setDataDemissao(DateNormalizer::fromFormDate($data['data_demissao'] ?? null));
        $f->setDataNascimento(DateNormalizer::fromFormDate($data['data_nascimento'] ?? null));

        $deptId = (int) ($data['departamento_id'] ?? 0);
        if ($deptId > 0 && $f->getEmpresa()) {
            $dept = $this->departamentoRepo->findOneBy(['id' => $deptId, 'empresa' => $f->getEmpresa()]);
            $f->setDepartamento($dept);
        } else {
            $f->setDepartamento(null);
        }

        $gestorId = (int) ($data['gestor_id'] ?? 0);
        if ($gestorId > 0 && $f->getEmpresa()) {
            if ($f->getId() !== null && $gestorId === $f->getId()) {
                throw new RhProcessException('O colaborador não pode ser gestor de si mesmo.');
            }
            $gestor = $this->repo->findOneBy(['id' => $gestorId, 'empresa' => $f->getEmpresa()]);
            $f->setGestor($gestor);
        } else {
            $f->setGestor(null);
        }
    }

    private function nullIfEmpty(mixed $v): ?string
    {
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }

    private function storePhoto(UploadedFile $file): string
    {
        if ($file->getSize() > self::MAX_PHOTO_BYTES) {
            throw new RhProcessException('Foto muito grande. Máximo 2 MB.');
        }
        $mime = (string) $file->getMimeType();
        if (!\in_array($mime, self::ALLOWED_MIME, true)) {
            throw new RhProcessException('Formato de imagem não suportado. Use JPG, PNG ou WebP.');
        }

        $ext = match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        $dir = $this->projectDir . '/' . self::UPLOAD_DIR;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RhProcessException('Não foi possível criar pasta de uploads.');
        }

        $name = uniqid('func_', true) . '.' . $ext;
        $file->move($dir, $name);

        return '/uploads/funcionarios/' . $name;
    }

    private function deletePhotoFile(?string $path): void
    {
        if (!$path || !str_starts_with($path, '/uploads/funcionarios/')) {
            return;
        }
        $full = $this->projectDir . '/public' . $path;
        if (is_file($full)) {
            @unlink($full);
        }
    }
}
