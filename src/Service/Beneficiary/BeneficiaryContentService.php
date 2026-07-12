<?php

namespace App\Service\Beneficiary;

use App\Entity\PosOperatorioPaciente;
use App\Service\Marketing\ClinicPatientProductService;
use App\Service\PosOperatorio\ClinicCarteirinhaService;
use App\Service\PosOperatorio\ClinicComprovanteService;
use App\Service\PosOperatorio\PosOperatorioPortalService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Monta conteúdo do beneficiário após validação (demo ou paciente real).
 */
final class BeneficiaryContentService
{
    public function __construct(
        private BeneficiaryAccessService $access,
        private ClinicPatientProductService $demoProduct,
        private ClinicCarteirinhaService $carteirinhaService,
        private ClinicComprovanteService $comprovanteService,
        private PosOperatorioPortalService $portalService,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    /** @return array{card: array<string, mixed>, theme: string}|null */
    public function buildCarteirinhaCard(): ?array
    {
        if ($this->access->isDemoSession()) {
            $plan = $this->demoProduct->planById('premium') ?? $this->demoProduct->plans()[0];

            return [
                'card' => $plan,
                'theme' => (string) ($plan['theme'] ?? 'premium'),
            ];
        }

        $patient = $this->access->findGrantedPatient();
        if ($patient === null || !$patient->hasCarteirinhaAtiva()) {
            return null;
        }

        $plano = $patient->getCarteirinhaPlano() ?? 'essencial';

        return [
            'card' => $this->carteirinhaService->buildCardData($patient, $patient->getEmpresa()),
            'theme' => $plano,
        ];
    }

    /** @return array{proof: array<string, mixed>, card: array<string, mixed>, theme: string, verificacao_url: ?string}|null */
    public function buildComprovanteProof(): ?array
    {
        if ($this->access->isDemoSession()) {
            $card = $this->demoProduct->comprovanteDemoCard();
            $verificacao = (string) ($card['verificacao'] ?? $this->demoProduct->demoAccess()['verificacao']);

            return [
                'card' => $card,
                'theme' => (string) ($card['theme'] ?? 'profissional'),
                'proof' => [
                    'clinica' => $card['clinica'] ?? 'UNIO SAÚDE',
                    'titulo' => 'Comprovante de procedimento',
                    'nome' => $card['nome'] ?? 'Beneficiário',
                    'codigo_paciente' => $card['codigo'] ?? 'PO-0042',
                    'procedimento' => $card['procedimento'] ?? '—',
                    'cirurgia' => $card['cirurgia'] ?? '—',
                    'dia_pos' => $card['dia_pos'] ?? null,
                    'medico' => $card['medico'] ?? '—',
                    'protocolo' => $card['protocolo'] ?? '—',
                    'valido_ate' => $card['valido_ate'] ?? '—',
                    'emitido_em' => $card['emitido_em'] ?? '—',
                    'verificacao' => $verificacao,
                    'status_label' => 'Documento válido (demonstração)',
                ],
                'verificacao_url' => $this->urlGenerator->generate(
                    'app_verificar_documento',
                    ['codigo' => $verificacao],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                ),
            ];
        }

        $patient = $this->access->findGrantedPatient();
        if ($patient === null || !$patient->hasComprovanteAtivo()) {
            return null;
        }

        $codigo = $patient->getComprovanteVerificacao();

        return [
            'card' => $this->comprovanteService->buildCardData($patient, $patient->getEmpresa()),
            'theme' => 'profissional',
            'proof' => $this->comprovanteService->buildProofData($patient, $patient->getEmpresa()),
            'verificacao_url' => $codigo !== null
                ? $this->urlGenerator->generate('app_verificar_documento', ['codigo' => $codigo], UrlGeneratorInterface::ABSOLUTE_URL)
                : null,
        ];
    }

    /** @return array<string, mixed> */
    public function buildGuiaView(): array
    {
        if ($this->access->isDemoSession()) {
            $guia = $this->demoProduct->demoGuia();
            $base = $this->demoProduct->planById('premium') ?? $this->demoProduct->plans()[0];

            return [
                'demo' => true,
                'paciente_nome' => (string) ($base['nome'] ?? 'Beneficiário'),
                'paciente_codigo' => (string) ($base['codigo'] ?? 'PO-0000'),
                'dia_pos' => $base['dia_pos'] ?? null,
                'procedimento_label' => (string) ($base['procedimento'] ?? 'Procedimento'),
                'medico_nome' => (string) ($base['medico'] ?? '—'),
                'clinica_nome' => (string) ($base['clinica'] ?? 'Unio Saúde'),
                'data_cirurgia_label' => (string) ($base['cirurgia'] ?? null),
                'duracao_dias' => 14,
                'progress_pct' => 21,
                'fase_label' => $guia['fase_label'] ?? 'Fase intermediária',
                'guia_medico' => $guia,
                'checklist_hoje' => [
                    ['dia' => 3, 'item' => 'Retirada de curativo'],
                ],
                'telefone_clinica' => (string) ($base['telefone'] ?? '—'),
                'contato_emergencia' => (string) ($base['emergencia'] ?? '—'),
            ];
        }

        $patient = $this->access->findGrantedPatient();
        if ($patient === null) {
            return $this->emptyGuiaView();
        }

        $portal = $this->portalService->buildView($patient);

        return [
            'demo' => false,
            'paciente_nome' => $patient->getNome(),
            'paciente_codigo' => $patient->getCodigo(),
            'dia_pos' => $patient->getDiaPosOperatorio(),
            'procedimento_label' => $portal['procedimento_label'],
            'medico_nome' => $portal['medico_nome'],
            'clinica_nome' => $portal['clinica_nome'],
            'data_cirurgia_label' => $portal['data_cirurgia_label'],
            'duracao_dias' => $portal['duracao_dias'],
            'progress_pct' => $portal['progress_pct'],
            'fase_label' => $portal['fase_label'],
            'guia_medico' => $portal['guia_medico'],
            'checklist_hoje' => $portal['checklist_hoje'],
            'telefone_clinica' => $patient->getTelefoneContato(),
            'contato_emergencia' => trim(implode(' · ', array_filter([
                $patient->getContatoEmergencia(),
                $patient->getTelefoneEmergencia(),
            ]))),
        ];
    }

    /** @return array<string, mixed> */
    private function emptyGuiaView(): array
    {
        return [
            'demo' => false,
            'paciente_nome' => 'Beneficiário',
            'paciente_codigo' => '—',
            'dia_pos' => null,
            'procedimento_label' => '—',
            'medico_nome' => null,
            'clinica_nome' => null,
            'data_cirurgia_label' => null,
            'duracao_dias' => null,
            'progress_pct' => 0,
            'fase_label' => null,
            'guia_medico' => $this->demoProduct->demoGuia(),
            'checklist_hoje' => [],
            'telefone_clinica' => null,
            'contato_emergencia' => null,
        ];
    }
}