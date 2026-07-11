<?php

namespace App\Service\PosOperatorio;

use App\Entity\Empresa;
use App\Entity\PosOperatorioPaciente;
use App\Entity\User;
use App\PosOperatorio\PosOperatorioDisplay;
use App\Repository\PosOperatorioPacienteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ClinicCarteirinhaService
{
    public const PLANOS = [
        'essencial' => 'Essencial',
        'profissional' => 'Profissional',
        'premium' => 'Premium',
    ];

    private const UPLOAD_DIR = 'public/uploads/clinic/pacientes';
    private const UPLOAD_PUBLIC_PREFIX = '/uploads/clinic/pacientes/';
    private const MAX_PHOTO_BYTES = 2_097_152;
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(
        private EntityManagerInterface $em,
        private PosOperatorioPacienteRepository $pacientes,
        private string $projectDir,
    ) {}

    /** @return list<PosOperatorioPaciente> */
    public function listComEmissao(Empresa $empresa): array
    {
        return $this->pacientes->findComCarteirinha($empresa);
    }

    public function emitir(
        PosOperatorioPaciente $paciente,
        User $autor,
        string $plano = 'essencial',
        int $validadeDias = 14,
    ): void {
        if (!isset(self::PLANOS[$plano])) {
            throw new \InvalidArgumentException('Plano de carteirinha inválido.');
        }

        $paciente
            ->setCarteirinhaPlano($plano)
            ->setCarteirinhaVerificacao($this->gerarCodigoVerificacao($paciente->getEmpresa()))
            ->setCarteirinhaEmitidaEm(new \DateTimeImmutable())
            ->setCarteirinhaValidaAte(new \DateTimeImmutable(sprintf('+%d days', max(1, $validadeDias))));

        $this->em->flush();
    }

    public function revogar(PosOperatorioPaciente $paciente): void
    {
        $paciente
            ->setCarteirinhaPlano(null)
            ->setCarteirinhaVerificacao(null)
            ->setCarteirinhaEmitidaEm(null)
            ->setCarteirinhaValidaAte(null);

        $this->em->flush();
    }

    public function storeFoto(PosOperatorioPaciente $paciente, UploadedFile $file): void
    {
        if (!$file->isValid()) {
            throw new \InvalidArgumentException('Arquivo de foto inválido.');
        }
        if ($file->getSize() > self::MAX_PHOTO_BYTES) {
            throw new \InvalidArgumentException('Foto muito grande. Máximo 2 MB.');
        }

        $mime = (string) $file->getMimeType();
        if (!\in_array($mime, self::ALLOWED_MIME, true)) {
            throw new \InvalidArgumentException('Formato não suportado. Use JPG, PNG ou WebP.');
        }

        $this->deleteFotoFile($paciente->getFotoPath());

        $ext = match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        $dir = $this->projectDir . '/' . self::UPLOAD_DIR;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Não foi possível salvar a foto.');
        }

        $filename = sprintf('pac-%d-%s.%s', $paciente->getId(), bin2hex(random_bytes(6)), $ext);
        $file->move($dir, $filename);
        $paciente->setFotoPath(self::UPLOAD_PUBLIC_PREFIX . $filename);
        $this->em->flush();
    }

    public function findByVerificacao(Empresa $empresa, string $codigo): ?PosOperatorioPaciente
    {
        $codigo = strtoupper(trim($codigo));
        if ($codigo === '') {
            return null;
        }

        return $this->pacientes->findByCarteirinhaVerificacao($empresa, $codigo);
    }

    /** @return array<string, mixed> */
    public function buildCardData(PosOperatorioPaciente $paciente, Empresa $empresa): array
    {
        $nome = PosOperatorioDisplay::pacienteNome($paciente);
        $partes = preg_split('/\s+/', trim($nome)) ?: [];
        $iniciais = '';
        foreach (array_slice($partes, 0, 2) as $p) {
            $iniciais .= mb_strtoupper(mb_substr($p, 0, 1));
        }

        $plano = $paciente->getCarteirinhaPlano() ?? 'essencial';
        $diaPos = $paciente->getDiaPosOperatorio();
        $medico = $paciente->getMedicoResponsavel()?->getNome() ?? '—';
        $emergencia = trim(implode(' · ', array_filter([
            $paciente->getContatoEmergencia(),
            $paciente->getTelefoneEmergencia(),
        ])));

        return [
            'clinica' => mb_strtoupper($empresa->getNome()),
            'iniciais' => $iniciais !== '' ? $iniciais : 'PC',
            'foto' => $this->fotoPublicUrl($paciente->getFotoPath()),
            'nome' => $nome,
            'role' => $this->roleLabel($plano),
            'plano_label' => $this->planoLabel($plano),
            'codigo' => $paciente->getCodigo(),
            'procedimento' => $paciente->getProcedimento() ?? ($paciente->getProtocolo()?->getNome() ?? 'Procedimento'),
            'dia_pos' => $diaPos,
            'medico' => $medico,
            'cirurgia' => $paciente->getDataCirurgia()?->format('d/m/Y') ?? '—',
            'protocolo' => $paciente->getProtocolo()
                ? sprintf('%s · %d dias', $paciente->getProtocolo()->getNome(), $paciente->getProtocolo()->getDuracaoDias())
                : '—',
            'valido_ate' => $paciente->getCarteirinhaValidaAte()?->format('d/m/Y') ?? '—',
            'emitido_em' => $paciente->getCarteirinhaEmitidaEm()?->format('d/m/Y') ?? '—',
            'telefone' => $paciente->getTelefoneContato() ?? '—',
            'emergencia' => $emergencia !== '' ? $emergencia : '—',
            'verificacao' => $paciente->getCarteirinhaVerificacao() ?? '--------',
            'ribbon' => self::ribbonFor($plano),
            'suporte' => self::suporteFor($plano),
        ];
    }

    /** @return array<string, array{plano_label: string, role: string, ribbon: ?string, suporte: ?string}> */
    public static function planPreviewMeta(): array
    {
        $meta = [];
        foreach (array_keys(self::PLANOS) as $plano) {
            $meta[$plano] = [
                'plano_label' => self::planoLabelFor($plano),
                'role' => self::roleLabelFor($plano),
                'ribbon' => self::ribbonFor($plano),
                'suporte' => self::suporteFor($plano),
            ];
        }

        return $meta;
    }

    public static function roleLabelFor(string $plano): string
    {
        return match ($plano) {
            'premium' => 'Paciente VIP · acompanhamento',
            'profissional' => 'Paciente em acompanhamento',
            default => 'Paciente pós-operatório',
        };
    }

    public static function planoLabelFor(string $plano): string
    {
        return match ($plano) {
            'premium' => 'Plano Premium',
            'profissional' => 'Plano Profissional',
            default => 'Plano Essencial',
        };
    }

    public static function ribbonFor(string $plano): ?string
    {
        return $plano === 'essencial' ? null : ucfirst($plano);
    }

    public static function suporteFor(string $plano): ?string
    {
        return $plano === 'premium' ? 'Suporte clínico 24h' : null;
    }

    private function roleLabel(string $plano): string
    {
        return self::roleLabelFor($plano);
    }

    private function planoLabel(string $plano): string
    {
        return self::planoLabelFor($plano);
    }

    private function fotoPublicUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $path = str_replace('\\', '/', $path);
        if (str_starts_with($path, 'public/')) {
            $path = substr($path, strlen('public'));
        }

        return str_starts_with($path, '/') ? $path : '/' . $path;
    }

    private function gerarCodigoVerificacao(Empresa $empresa): string
    {
        for ($i = 0; $i < 8; ++$i) {
            $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            if ($this->pacientes->findByCarteirinhaVerificacao($empresa, $code) === null) {
                return $code;
            }
        }

        throw new \RuntimeException('Não foi possível gerar código de verificação.');
    }

    private function deleteFotoFile(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        $full = $this->resolveFotoFilesystemPath($path);
        if ($full !== null && is_file($full)) {
            @unlink($full);
        }
    }

    private function resolveFotoFilesystemPath(string $path): ?string
    {
        $path = str_replace('\\', '/', $path);

        if (str_starts_with($path, self::UPLOAD_PUBLIC_PREFIX)) {
            return $this->projectDir . '/public' . $path;
        }

        if (str_starts_with($path, '/uploads/clinic/pacientes/')) {
            return $this->projectDir . '/public' . $path;
        }

        if (str_starts_with($path, 'public/uploads/clinic/pacientes/')) {
            return $this->projectDir . '/' . $path;
        }

        if (str_starts_with($path, self::UPLOAD_DIR . '/')) {
            return $this->projectDir . '/' . $path;
        }

        return null;
    }
}
