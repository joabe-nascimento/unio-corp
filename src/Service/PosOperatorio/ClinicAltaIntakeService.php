<?php

namespace App\Service\PosOperatorio;

use App\Entity\Empresa;
use App\Entity\PosOperatorioEvento;
use App\Entity\PosOperatorioPaciente;
use App\Entity\User;
use App\Repository\EmpresaRepository;
use App\Repository\PosOperatorioPacienteRepository;
use App\Repository\PosOperatorioProtocoloRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Ingestão de alta cirúrgica via webhook (token por clínica).
 */
final class ClinicAltaIntakeService
{
    public function __construct(
        private EmpresaRepository $empresas,
        private ClinicPolicyConfigService $policy,
        private PosOperatorioPacienteService $pacientes,
        private PosOperatorioPacienteRepository $pacienteRepo,
        private PosOperatorioProtocoloRepository $protocolos,
        private UserRepository $users,
        private PosOperatorioEventRecorder $events,
        private ClinicChannelDispatcher $channels,
        private EntityManagerInterface $em,
    ) {}

    public function findEmpresaByToken(string $token): ?Empresa
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        foreach ($this->empresas->findBy(['ativo' => true]) as $empresa) {
            $stored = $this->policy->get($empresa)['alta_token'];
            if ($stored !== '' && hash_equals($stored, $token)) {
                return $empresa;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{paciente_id: int, codigo: string, created: bool}
     */
    public function ingest(Empresa $empresa, array $payload, ?User $systemUser = null): array
    {
        $nome = trim((string) ($payload['nome'] ?? $payload['paciente_nome'] ?? ''));
        if ($nome === '') {
            throw new \InvalidArgumentException('Campo nome é obrigatório.');
        }

        $codigoExterno = trim((string) ($payload['codigo'] ?? $payload['codigo_externo'] ?? ''));
        $existing = null;
        if ($codigoExterno !== '') {
            $existing = $this->pacienteRepo->findOneBy(['empresa' => $empresa, 'codigo' => $codigoExterno]);
        }

        $data = [
            'nome' => $nome,
            'telefone' => (string) ($payload['telefone'] ?? ''),
            'email' => (string) ($payload['email'] ?? ''),
            'data_cirurgia' => (string) ($payload['data_cirurgia'] ?? $payload['data_alta'] ?? date('Y-m-d')),
            'procedimento' => (string) ($payload['procedimento'] ?? ''),
            'status' => PosOperatorioPaciente::STATUS_ATIVO,
        ];

        $protocoloSlug = trim((string) ($payload['protocolo_slug'] ?? $payload['protocolo'] ?? ''));
        if ($protocoloSlug !== '') {
            $protocolo = $this->protocolos->findOneBy(['empresa' => $empresa, 'tipoProcedimento' => $protocoloSlug]);
            if (!$protocolo) {
                foreach ($this->protocolos->findBy(['empresa' => $empresa]) as $candidate) {
                    if (strcasecmp((string) $candidate->getNome(), $protocoloSlug) === 0
                        || strcasecmp((string) $candidate->getTipoProcedimento(), $protocoloSlug) === 0) {
                        $protocolo = $candidate;
                        break;
                    }
                }
            }
            if ($protocolo) {
                $data['protocolo_id'] = $protocolo->getId();
            }
        }

        $medicoEmail = trim((string) ($payload['medico_email'] ?? ''));
        if ($medicoEmail !== '') {
            $medico = $this->users->findOneBy(['email' => $medicoEmail, 'empresa' => $empresa]);
            if ($medico instanceof User) {
                $data['medico_id'] = $medico->getId();
            }
        }

        $autor = $systemUser ?? $this->users->findActiveByEmpresa($empresa)[0] ?? null;
        if (!$autor instanceof User) {
            throw new \RuntimeException('Clínica sem usuário ativo para registrar alta.');
        }

        if ($existing instanceof PosOperatorioPaciente) {
            $this->pacientes->update($existing, $data, $autor);
            $paciente = $existing;
            $created = false;
        } else {
            $paciente = $this->pacientes->create($empresa, $data, $autor);
            if ($codigoExterno !== '' && method_exists($paciente, 'setCodigo')) {
                // keep generated codigo — external id goes to event
            }
            $created = true;
        }

        $this->events->record(
            $paciente,
            PosOperatorioEvento::TIPO_CADASTRO,
            sprintf(
                'Alta cirúrgica recebida via integração%s',
                $codigoExterno !== '' ? ' · ref ' . $codigoExterno : '',
            ),
            $autor,
        );
        $this->em->flush();

        $this->channels->emitWebhook($empresa, 'alta_cirurgica', [
            'paciente_id' => $paciente->getId(),
            'codigo' => $paciente->getCodigo(),
            'created' => $created,
        ]);

        return [
            'paciente_id' => (int) $paciente->getId(),
            'codigo' => $paciente->getCodigo(),
            'created' => $created,
        ];
    }

    public function ensureToken(Empresa $empresa): string
    {
        $policy = $this->policy->get($empresa);
        if ($policy['alta_token'] !== '') {
            return $policy['alta_token'];
        }

        $token = bin2hex(random_bytes(24));
        $policy['alta_token'] = $token;
        $this->policy->save($empresa, $policy);

        return $token;
    }
}
