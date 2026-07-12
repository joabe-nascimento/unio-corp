<?php

namespace App\Service\Beneficiary;

use App\Entity\PosOperatorioPaciente;
use App\Repository\PosOperatorioPacienteRepository;
use App\Service\Marketing\ClinicPatientProductService;
use App\Support\BrPersonFormat;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Acesso público do beneficiário — validação por CPF, código do paciente ou carteirinha.
 */
final class BeneficiaryAccessService
{
    private const SESSION_KEY = 'beneficiary_access';
    private const TTL_SECONDS = 7200;

    public const METHOD_CPF = 'cpf';
    public const METHOD_CODIGO = 'codigo';
    public const METHOD_VERIFICACAO = 'verificacao';

    public function __construct(
        private RequestStack $requestStack,
        private PosOperatorioPacienteRepository $pacientes,
        private ClinicPatientProductService $demoProduct,
    ) {}

    public function isGranted(): bool
    {
        $data = $this->read();

        return $data !== null && $this->isFresh($data);
    }

    public function isCarteirinhaUnlocked(): bool
    {
        $data = $this->read();

        return $data !== null
            && $this->isFresh($data)
            && !empty($data['carteirinha_unlocked']);
    }

    public function unlockCarteirinha(): void
    {
        $data = $this->read();
        if ($data === null) {
            return;
        }

        $data['carteirinha_unlocked'] = true;
        $this->write($data);
    }

    public function revoke(): void
    {
        $this->session()?->remove(self::SESSION_KEY);
    }

    /** @return array{method: string, value: string}|null */
    public function pendingIdentification(): ?array
    {
        $data = $this->read();
        if ($data === null || empty($data['pending_method']) || empty($data['pending_value'])) {
            return null;
        }

        return [
            'method' => (string) $data['pending_method'],
            'value' => (string) $data['pending_value'],
        ];
    }

    public function clearPending(): void
    {
        $data = $this->read();
        if ($data === null) {
            return;
        }

        unset($data['pending_method'], $data['pending_value']);
        $this->write($data);
    }

    /**
     * @return array{ok: true}|array{ok: false, error: string}
     */
    public function storePendingIdentification(string $method, string $value): array
    {
        $method = $this->normalizeMethod($method);
        $value = trim($value);
        if ($value === '') {
            return ['ok' => false, 'error' => 'Informe o dado de identificação.'];
        }

        if (!$this->looksLikeValidPrimary($method, $value)) {
            return ['ok' => false, 'error' => 'Formato inválido. Verifique e tente novamente.'];
        }

        $this->write([
            'pending_method' => $method,
            'pending_value' => $value,
            'granted_at' => time(),
        ]);

        return ['ok' => true];
    }

    /**
     * @return array{ok: true}|array{ok: false, error: string}
     */
    public function confirmIdentification(string $secondaryValue): array
    {
        $pending = $this->pendingIdentification();
        if ($pending === null) {
            return ['ok' => false, 'error' => 'Sessão expirada. Comece novamente.'];
        }

        $resolved = $this->resolvePair($pending['method'], $pending['value'], $secondaryValue);
        if ($resolved === null) {
            return ['ok' => false, 'error' => 'Dados não conferem. Verifique CPF, código ou carteirinha.'];
        }

        if ($resolved['patient'] !== null && !$resolved['patient']->hasCarteirinhaAtiva() && $pending['method'] === self::METHOD_VERIFICACAO) {
            return ['ok' => false, 'error' => 'Carteirinha não encontrada ou expirada.'];
        }

        $this->write([
            'demo' => $resolved['demo'],
            'patient_id' => $resolved['patient']?->getId(),
            'granted_at' => time(),
            'carteirinha_unlocked' => false,
        ]);

        return ['ok' => true];
    }

    public function findGrantedPatient(): ?PosOperatorioPaciente
    {
        $data = $this->read();
        if ($data === null || !$this->isFresh($data) || !empty($data['demo'])) {
            return null;
        }

        $id = $data['patient_id'] ?? null;
        if (!is_int($id) && !is_numeric($id)) {
            return null;
        }

        return $this->pacientes->find((int) $id);
    }

    public function isDemoSession(): bool
    {
        $data = $this->read();

        return $data !== null && $this->isFresh($data) && !empty($data['demo']);
    }

    public function switchToPatient(int $patientId): bool
    {
        $data = $this->read();
        if ($data === null || !$this->isFresh($data) || !empty($data['demo'])) {
            return false;
        }

        $current = $this->findGrantedPatient();
        if ($current === null) {
            return false;
        }

        $titularCpf = $current->getCpfTitularEfetivo();
        if ($titularCpf === null) {
            return false;
        }

        $dependents = $this->pacientes->findDependentesByTitularCpf($current->getEmpresa(), $titularCpf);
        foreach ($dependents as $dep) {
            if ((int) $dep->getId() === $patientId) {
                $data['patient_id'] = $patientId;
                $data['carteirinha_unlocked'] = false;
                $this->write($data);

                return true;
            }
        }

        return false;
    }

    /** @return array<string, string> */
    public function demoAccessHints(): array
    {
        return $this->demoProduct->demoAccess();
    }

    /** @return array<string, mixed> */
    public function demoPreview(): array
    {
        $card = $this->demoProduct->planById('premium') ?? $this->demoProduct->plans()[0] ?? [];

        return [
            'card' => $card,
            'theme' => $card['theme'] ?? 'premium',
            'access' => $this->demoProduct->demoAccess(),
            'guia' => $this->demoProduct->demoGuia(),
        ];
    }

    /** @return array<string, mixed> */
    public function demoComprovantePreview(): array
    {
        $base = $this->demoProduct->demoPatient();

        return [
            'proof' => array_merge($base, [
                'titulo' => 'Comprovante de procedimento',
                'verificacao' => $this->demoProduct->demoAccess()['verificacao'],
                'status_label' => 'Documento válido (demonstração)',
            ]),
            'access' => $this->demoProduct->demoAccess(),
        ];
    }

    /** @return array<string, mixed> */
    private function read(): ?array
    {
        $raw = $this->session()?->get(self::SESSION_KEY);

        return is_array($raw) ? $raw : null;
    }

    /** @param array<string, mixed> $data */
    private function write(array $data): void
    {
        $existing = $this->read() ?? [];
        $this->session()?->set(self::SESSION_KEY, array_merge($existing, $data));
    }

    /** @param array<string, mixed> $data */
    private function isFresh(array $data): bool
    {
        $at = $data['granted_at'] ?? 0;

        return is_int($at) && (time() - $at) < self::TTL_SECONDS;
    }

    private function session(): ?SessionInterface
    {
        $request = $this->requestStack->getCurrentRequest();

        return $request?->hasSession() ? $request->getSession() : null;
    }

    private function normalizeMethod(string $method): string
    {
        return match ($method) {
            self::METHOD_CPF, self::METHOD_CODIGO, self::METHOD_VERIFICACAO => $method,
            default => self::METHOD_CPF,
        };
    }

    private function looksLikeValidPrimary(string $method, string $value): bool
    {
        return match ($method) {
            self::METHOD_CPF => strlen($this->digitsOnly($value)) === 11,
            self::METHOD_CODIGO => (bool) preg_match('/^PO-\d{3,6}$/i', strtoupper(trim($value))),
            self::METHOD_VERIFICACAO => strlen(strtoupper(trim($value))) >= 6,
            default => false,
        };
    }

    /**
     * @return array{demo: bool, patient: ?PosOperatorioPaciente}|null
     */
    private function resolvePair(string $primaryMethod, string $primaryValue, string $secondaryValue): ?array
    {
        if ($this->matchDemo($primaryMethod, $primaryValue, $secondaryValue)) {
            return ['demo' => true, 'patient' => null];
        }

        $patient = $this->matchRealPatient($primaryMethod, $primaryValue, $secondaryValue);
        if ($patient === null) {
            return null;
        }

        return ['demo' => false, 'patient' => $patient];
    }

    private function matchDemo(string $primaryMethod, string $primaryValue, string $secondaryValue): bool
    {
        $demo = $this->demoProduct->demoAccess();
        $pairs = [
            [self::METHOD_CPF, $demo['cpf'], self::METHOD_CODIGO, $demo['codigo']],
            [self::METHOD_CPF, $demo['cpf'], self::METHOD_VERIFICACAO, $demo['verificacao']],
            [self::METHOD_CODIGO, $demo['codigo'], self::METHOD_CPF, $demo['cpf']],
            [self::METHOD_CODIGO, $demo['codigo'], self::METHOD_VERIFICACAO, $demo['verificacao']],
            [self::METHOD_VERIFICACAO, $demo['verificacao'], self::METHOD_CPF, $demo['cpf']],
            [self::METHOD_VERIFICACAO, $demo['verificacao'], self::METHOD_CODIGO, $demo['codigo']],
        ];

        foreach ($pairs as [$m1, $v1, $m2, $v2]) {
            if ($primaryMethod === $m1 && $this->valuesMatch($m1, $primaryValue, $v1)
                && $this->valuesMatch($m2, $secondaryValue, $v2)) {
                return true;
            }
        }

        return false;
    }

    private function matchRealPatient(string $primaryMethod, string $primaryValue, string $secondaryValue): ?PosOperatorioPaciente
    {
        $candidates = $this->candidatesFromPrimary($primaryMethod, $primaryValue);
        if ($candidates === []) {
            return null;
        }

        foreach ($candidates as $patient) {
            if ($this->patientMatchesSecondary($patient, $primaryMethod, $primaryValue, $secondaryValue)) {
                return $patient;
            }
        }

        return null;
    }

    /** @return list<PosOperatorioPaciente> */
    private function candidatesFromPrimary(string $method, string $value): array
    {
        return match ($method) {
            self::METHOD_CODIGO => array_filter([$this->pacientes->findByCodigoGlobal(strtoupper(trim($value)))]),
            self::METHOD_VERIFICACAO => array_filter([$this->pacientes->findByAnyVerificacaoGlobal(strtoupper(trim($value)))]),
            self::METHOD_CPF => array_filter([$this->pacientes->findByCpfGlobal($value)]),
            default => [],
        };
    }

    private function patientMatchesSecondary(
        PosOperatorioPaciente $patient,
        string $primaryMethod,
        string $primaryValue,
        string $secondaryValue,
    ): bool {
        $secondary = $this->detectSecondaryMethod($secondaryValue);
        $codigo = strtoupper(trim($patient->getCodigo()));
        $verificacaoCarteirinha = strtoupper(trim((string) $patient->getCarteirinhaVerificacao()));
        $verificacaoComprovante = strtoupper(trim((string) $patient->getComprovanteVerificacao()));
        $cpf = (string) ($patient->getCpf() ?? '');

        return match ($secondary) {
            self::METHOD_CODIGO => $this->valuesMatch(self::METHOD_CODIGO, $secondaryValue, $codigo)
                && $this->primaryMatchesPatient($patient, $primaryMethod, $primaryValue),
            self::METHOD_VERIFICACAO => ($verificacaoCarteirinha !== '' || $verificacaoComprovante !== '')
                && (
                    ($verificacaoCarteirinha !== '' && $this->valuesMatch(self::METHOD_VERIFICACAO, $secondaryValue, $verificacaoCarteirinha))
                    || ($verificacaoComprovante !== '' && $this->valuesMatch(self::METHOD_VERIFICACAO, $secondaryValue, $verificacaoComprovante))
                )
                && $this->primaryMatchesPatient($patient, $primaryMethod, $primaryValue),
            self::METHOD_CPF => $cpf !== ''
                && $this->valuesMatch(self::METHOD_CPF, $secondaryValue, $cpf)
                && $this->primaryMatchesPatient($patient, $primaryMethod, $primaryValue),
            default => false,
        };
    }

    private function primaryMatchesPatient(PosOperatorioPaciente $patient, string $method, string $value): bool
    {
        return match ($method) {
            self::METHOD_CODIGO => $this->valuesMatch(self::METHOD_CODIGO, $value, $patient->getCodigo()),
            self::METHOD_VERIFICACAO => $this->valuesMatch(
                self::METHOD_VERIFICACAO,
                $value,
                (string) $patient->getCarteirinhaVerificacao(),
            ),
            self::METHOD_CPF => $patient->getCpf() !== null
                && $this->valuesMatch(self::METHOD_CPF, $value, $patient->getCpf()),
            default => false,
        };
    }

    private function detectSecondaryMethod(string $value): string
    {
        $trim = trim($value);
        if (preg_match('/^PO-/i', $trim)) {
            return self::METHOD_CODIGO;
        }

        $digits = $this->digitsOnly($trim);
        if (strlen($digits) === 11 && BrPersonFormat::isValidCpf($digits)) {
            return self::METHOD_CPF;
        }

        return self::METHOD_VERIFICACAO;
    }

    private function valuesMatch(string $method, string $input, string $expected): bool
    {
        return match ($method) {
            self::METHOD_CPF => $this->digitsOnly($input) === $this->digitsOnly($expected),
            self::METHOD_CODIGO => strtoupper(trim($input)) === strtoupper(trim($expected)),
            self::METHOD_VERIFICACAO => strtoupper(preg_replace('/\s+/', '', $input) ?? '') === strtoupper(trim($expected)),
            default => false,
        };
    }

    private function digitsOnly(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }
}
