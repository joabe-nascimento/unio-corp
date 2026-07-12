<?php

namespace App\Service\PosOperatorio;

use App\Entity\ClinicDocumentoEmissao;
use App\Entity\Empresa;
use App\Entity\PosOperatorioPaciente;
use App\Entity\User;
use App\PosOperatorio\PosOperatorioDisplay;
use App\Repository\PosOperatorioPacienteRepository;
use App\Service\Clinic\ClinicDocumentoAuditService;
use App\Service\Clinic\ClinicWebhookDispatcherBridge;
use Doctrine\ORM\EntityManagerInterface;

final class ClinicComprovanteService
{
    public function __construct(
        private EntityManagerInterface $em,
        private PosOperatorioPacienteRepository $pacientes,
        private ClinicVerificacaoCodigoGenerator $codigos,
        private ClinicDocumentoAuditService $audit,
        private ClinicWebhookDispatcherBridge $webhooks,
    ) {}

    /** @return list<PosOperatorioPaciente> */
    public function listComEmissao(Empresa $empresa): array
    {
        return $this->pacientes->findComComprovante($empresa);
    }

    public function emitir(PosOperatorioPaciente $paciente, User $autor, int $validadeDias = 30): void
    {
        if ($paciente->getDataCirurgia() === null) {
            throw new \InvalidArgumentException('Informe a data da cirurgia antes de emitir o comprovante.');
        }

        $codigo = $this->codigos->gerar();
        $paciente
            ->setComprovanteVerificacao($codigo)
            ->setComprovanteEmitidaEm(new \DateTimeImmutable())
            ->setComprovanteValidoAte(new \DateTimeImmutable(sprintf('+%d days', max(7, $validadeDias))));

        $hash = ClinicDocumentoAuditService::hashComprovante($paciente);
        $paciente->setComprovanteHash($hash);

        $this->em->flush();

        $this->audit->registrar(
            $paciente,
            ClinicDocumentoEmissao::TIPO_COMPROVANTE,
            ClinicDocumentoEmissao::ACAO_EMITIR,
            $codigo,
            $autor,
            null,
            $hash,
        );

        $this->webhooks->documentoEmitido($paciente->getEmpresa(), 'comprovante', $paciente, [
            'hash' => $hash,
            'codigo_verificacao' => $codigo,
        ]);
    }

    public function revogar(PosOperatorioPaciente $paciente, ?User $autor = null): void
    {
        $codigo = $paciente->getComprovanteVerificacao();

        $paciente
            ->setComprovanteVerificacao(null)
            ->setComprovanteEmitidaEm(null)
            ->setComprovanteValidoAte(null)
            ->setComprovanteHash(null);

        $this->em->flush();

        if ($codigo !== null) {
            $this->audit->registrar(
                $paciente,
                ClinicDocumentoEmissao::TIPO_COMPROVANTE,
                ClinicDocumentoEmissao::ACAO_REVOGAR,
                $codigo,
                $autor,
            );
        }
    }

    /** @return array<string, mixed> */
    public function buildProofData(PosOperatorioPaciente $paciente, Empresa $empresa): array
    {
        $card = $this->buildCardData($paciente, $empresa);

        return [
            'clinica' => $card['clinica'],
            'titulo' => 'Comprovante de procedimento',
            'nome' => $card['nome'],
            'codigo_paciente' => $card['codigo'],
            'procedimento' => $card['procedimento'],
            'cirurgia' => $card['cirurgia'],
            'dia_pos' => $card['dia_pos'],
            'medico' => $card['medico'],
            'protocolo' => $card['protocolo'],
            'valido_ate' => $card['valido_ate'],
            'emitido_em' => $card['emitido_em'],
            'verificacao' => $card['verificacao'],
            'hash' => $paciente->getComprovanteHash(),
            'hash_curto' => $paciente->getComprovanteHash() !== null
                ? strtoupper(substr($paciente->getComprovanteHash(), 0, 12))
                : null,
            'status_label' => $paciente->hasComprovanteAtivo() ? 'Documento válido' : 'Documento expirado ou revogado',
        ];
    }

    /**
     * Payload no formato do card flip (mesmo visual da carteirinha).
     *
     * @return array<string, mixed>
     */
    public function buildCardData(PosOperatorioPaciente $paciente, Empresa $empresa): array
    {
        $nome = PosOperatorioDisplay::pacienteNome($paciente);
        $partes = preg_split('/\s+/', trim($nome)) ?: [];
        $iniciais = '';
        foreach (array_slice($partes, 0, 2) as $p) {
            $iniciais .= mb_strtoupper(mb_substr($p, 0, 1));
        }

        $emergencia = trim(implode(' · ', array_filter([
            $paciente->getContatoEmergencia(),
            $paciente->getTelefoneEmergencia(),
        ])));

        $protocolo = $paciente->getProtocolo();

        return [
            'clinica' => mb_strtoupper($empresa->getNome()),
            'doc_type_label' => 'Comprovante',
            'iniciais' => $iniciais !== '' ? $iniciais : 'PC',
            'foto' => null,
            'nome' => $nome,
            'role' => 'Documento do procedimento',
            'plano_label' => 'Comprovante',
            'ribbon' => 'Comprovante',
            'codigo' => $paciente->getCodigo(),
            'procedimento' => $paciente->getProcedimento() ?? ($protocolo?->getNome() ?? 'Procedimento'),
            'dia_pos' => $paciente->getDiaPosOperatorio(),
            'medico' => $paciente->getMedicoResponsavel()?->getNome() ?? '—',
            'cirurgia' => $paciente->getDataCirurgia()?->format('d/m/Y') ?? '—',
            'protocolo' => $protocolo
                ? sprintf('%s · %d dias', $protocolo->getNome(), $protocolo->getDuracaoDias())
                : '—',
            'valido_ate' => $paciente->getComprovanteValidoAte()?->format('d/m/Y') ?? '—',
            'emitido_em' => $paciente->getComprovanteEmitidaEm()?->format('d/m/Y') ?? '—',
            'telefone' => $paciente->getTelefoneContato() ?? '—',
            'emergencia' => $emergencia !== '' ? $emergencia : '—',
            'verificacao' => $paciente->getComprovanteVerificacao() ?? '--------',
            'suporte' => 'Apresente na recepção ou valide pelo QR',
            'hash_curto' => $paciente->getComprovanteHash() !== null
                ? strtoupper(substr($paciente->getComprovanteHash(), 0, 12))
                : null,
        ];
    }
}
