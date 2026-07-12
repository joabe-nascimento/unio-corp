<?php

namespace App\Service\Clinic;

use App\Entity\Empresa;
use App\Entity\PosOperatorioPaciente;
use App\PosOperatorio\ClinicProductCatalog;
use App\Repository\PosOperatorioPacienteRepository;
use App\Service\Beneficiary\BeneficiaryAccessService;
use App\Service\Beneficiary\BeneficiaryContentService;
use App\Service\Marketing\ClinicPatientProductService;
use App\Service\PosOperatorio\ClinicProductConfigService;
use App\Service\PosOperatorio\PosOperatorioPortalService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Hub do paciente — painel unificado pós-identificação.
 */
final class PatientHubService
{
    public function __construct(
        private BeneficiaryAccessService $access,
        private BeneficiaryContentService $content,
        private ClinicPatientProductService $demoProduct,
        private ClinicProductConfigService $productConfig,
        private ClinicBrandingService $branding,
        private ClinicPlanLimitsService $planLimits,
        private PosOperatorioPortalService $portalService,
        private PosOperatorioPacienteRepository $pacientes,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    /** @return array<string, mixed> */
    public function buildPublicLanding(): array
    {
        return [
            'branding' => $this->branding->forBeneficiary(),
            'demo_access' => $this->demoProduct->demoAccess(),
            'products' => $this->publicProductCards(null),
            'sandbox_beneficiarios' => $this->demoProduct->plans(),
        ];
    }

    /** @return array<string, mixed> */
    public function buildAuthenticatedHub(): array
    {
        if ($this->access->isDemoSession()) {
            return $this->buildDemoHub();
        }

        $patient = $this->access->findGrantedPatient();
        if ($patient === null) {
            return $this->buildPublicLanding();
        }

        return $this->buildPatientHub($patient);
    }

    /** @return array<string, mixed> */
    private function buildDemoHub(): array
    {
        $card = $this->demoProduct->planById('premium') ?? $this->demoProduct->plans()[0];
        $guia = $this->content->buildGuiaView();

        return [
            'demo' => true,
            'patient' => [
                'nome' => $card['nome'] ?? 'Beneficiário',
                'codigo' => $card['codigo'] ?? 'PO-0042',
                'status_clinico' => sprintf('Dia %d pós-op', $card['dia_pos'] ?? 0),
            ],
            'branding' => $this->branding->forBeneficiary(),
            'dependents' => $this->demoProduct->plans(),
            'timeline' => $this->demoTimeline($card),
            'documents' => $this->demoDocuments(),
            'products' => $this->publicProductCards(null),
            'guia_resumo' => $guia,
            'actions' => $this->quickActions(null, true),
            'notifications' => $this->demoNotifications(),
        ];
    }

    /** @return array<string, mixed> */
    private function buildPatientHub(PosOperatorioPaciente $patient): array
    {
        $empresa = $patient->getEmpresa();
        $portal = $this->portalService->buildView($patient);
        $titularCpf = $patient->getCpfTitularEfetivo();

        return [
            'demo' => false,
            'patient' => [
                'id' => $patient->getId(),
                'nome' => $patient->getNome(),
                'codigo' => $patient->getCodigo(),
                'status_clinico' => $patient->getStatusClinicoLabel(),
                'dia_pos' => $patient->getDiaPosOperatorio(),
                'unidade' => $patient->getUnidade()?->getNome(),
            ],
            'branding' => $this->branding->forBeneficiary($empresa),
            'dependents' => $titularCpf !== null
                ? $this->formatDependents($this->pacientes->findDependentesByTitularCpf($empresa, $titularCpf))
                : [],
            'timeline' => $this->buildTimeline($patient, $portal),
            'documents' => $this->buildDocuments($patient),
            'products' => $this->publicProductCards($empresa),
            'guia_resumo' => $this->content->buildGuiaView(),
            'actions' => $this->quickActions($empresa, false),
            'notifications' => $this->buildNotifications($patient),
            'retornos' => $this->buildRetornos($patient, $portal),
            'questionario_pendente' => $portal['questionario_pendente'] ?? false,
            'whatsapp' => $patient->getTelefoneContato(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function publicProductCards(?Empresa $empresa): array
    {
        $enabled = $empresa !== null
            ? $this->productConfig->enabledMap($empresa)
            : array_fill_keys(array_column(ClinicProductCatalog::all(), 'id'), true);

        $cards = [
            [
                'id' => 'portal',
                'label' => 'Portal pós-operatório',
                'desc' => 'Questionário diário e pedir ajuda.',
                'icon' => 'fa-clipboard-list',
                'route' => 'app_clinica_portal',
                'enabled' => true,
            ],
            [
                'id' => ClinicProductCatalog::CARTEIRINHA,
                'label' => 'Carteirinha digital',
                'desc' => 'Identidade clínica com validação em dois passos.',
                'icon' => 'fa-id-card',
                'route' => 'app_carteirinha_digital',
                'enabled' => $enabled[ClinicProductCatalog::CARTEIRINHA] ?? true,
            ],
            [
                'id' => ClinicProductCatalog::COMPROVANTE,
                'label' => 'Comprovante',
                'desc' => 'Documento da cirurgia com QR.',
                'icon' => 'fa-file-medical',
                'route' => 'app_comprovante_procedimento',
                'enabled' => $enabled[ClinicProductCatalog::COMPROVANTE] ?? true,
            ],
            [
                'id' => ClinicProductCatalog::GUIA_MEDICO,
                'label' => 'Guia médico',
                'desc' => 'Orientações por fase da recuperação.',
                'icon' => 'fa-book-medical',
                'route' => 'app_guia_medico_beneficiario',
                'enabled' => $enabled[ClinicProductCatalog::GUIA_MEDICO] ?? true,
            ],
        ];

        return array_values(array_filter($cards, static fn (array $c): bool => $c['enabled']));
    }

    /** @param array<string, mixed> $portal @return list<array<string, mixed>> */
    private function buildTimeline(PosOperatorioPaciente $patient, array $portal): array
    {
        $dia = $patient->getDiaPosOperatorio() ?? 0;
        $duracao = $patient->getProtocolo()?->getDuracaoDias() ?? 14;
        $milestones = [0, 3, 7, 14];
        $items = [];

        foreach ($milestones as $m) {
            if ($m > $duracao) {
                continue;
            }
            $items[] = [
                'dia' => $m,
                'label' => $m === 0 ? 'Cirurgia' : sprintf('D+%d', $m),
                'status' => $dia >= $m ? ($dia === $m ? 'atual' : 'concluido') : 'pendente',
                'desc' => $this->milestoneDesc($m),
            ];
        }

        if (!empty($portal['checklist_hoje'])) {
            $items[] = [
                'dia' => $dia,
                'label' => 'Hoje',
                'status' => 'atual',
                'desc' => (string) ($portal['checklist_hoje'][0]['item'] ?? 'Acompanhar recuperação'),
            ];
        }

        return $items;
    }

    /** @return list<array<string, mixed>> */
    private function demoTimeline(array $card): array
    {
        return [
            ['dia' => 0, 'label' => 'Cirurgia', 'status' => 'concluido', 'desc' => 'Procedimento realizado'],
            ['dia' => 3, 'label' => 'D+3', 'status' => 'atual', 'desc' => 'Retirada de curativo'],
            ['dia' => 7, 'label' => 'D+7', 'status' => 'pendente', 'desc' => 'Retorno ambulatorial'],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function buildDocuments(PosOperatorioPaciente $patient): array
    {
        $docs = [];
        if ($patient->hasCarteirinhaAtiva()) {
            $docs[] = [
                'tipo' => 'carteirinha',
                'label' => 'Carteirinha digital',
                'valido_ate' => $patient->getCarteirinhaValidaAte()?->format('d/m/Y'),
                'route' => 'app_carteirinha_digital',
                'params' => ['passo' => 3],
            ];
        }
        if ($patient->hasComprovanteAtivo()) {
            $docs[] = [
                'tipo' => 'comprovante',
                'label' => 'Comprovante de procedimento',
                'valido_ate' => $patient->getComprovanteValidoAte()?->format('d/m/Y'),
                'hash' => $patient->getComprovanteHash(),
                'route' => 'app_comprovante_procedimento',
                'params' => ['passo' => 3],
            ];
        }
        $docs[] = [
            'tipo' => 'guia',
            'label' => 'Guia médico',
            'valido_ate' => null,
            'route' => 'app_guia_medico_beneficiario',
            'params' => [],
        ];

        return $docs;
    }

    /** @return list<array<string, mixed>> */
    private function demoDocuments(): array
    {
        return [
            ['tipo' => 'carteirinha', 'label' => 'Carteirinha digital', 'valido_ate' => '22/07/2026', 'route' => 'app_carteirinha_digital', 'params' => []],
            ['tipo' => 'comprovante', 'label' => 'Comprovante de procedimento', 'valido_ate' => '22/08/2026', 'route' => 'app_comprovante_procedimento', 'params' => []],
            ['tipo' => 'guia', 'label' => 'Guia médico', 'valido_ate' => null, 'route' => 'app_guia_medico_beneficiario', 'params' => []],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function quickActions(?Empresa $empresa, bool $demo): array
    {
        return [
            ['label' => 'Responder questionário', 'icon' => 'fa-clipboard-check', 'route' => 'app_clinica_portal', 'params' => []],
            ['label' => 'Ver guia do dia', 'icon' => 'fa-book-medical', 'route' => 'app_guia_medico_beneficiario', 'params' => []],
            ['label' => 'Pedir ajuda', 'icon' => 'fa-hand-holding-medical', 'route' => 'app_clinica_portal', 'params' => []],
            ['label' => 'Falar com a clínica', 'icon' => 'fa-phone', 'route' => 'app_paciente_hub', 'params' => ['secao' => 'contato']],
        ];
    }

    /** @return list<array<string, string>> */
    private function buildNotifications(PosOperatorioPaciente $patient): array
    {
        $items = [];
        $validade = $patient->getCarteirinhaValidaAte();
        if ($validade !== null) {
            $diff = (new \DateTimeImmutable('today'))->diff($validade)->days;
            if ($diff <= 3 && $validade >= new \DateTimeImmutable('today')) {
                $items[] = ['tipo' => 'aviso', 'texto' => sprintf('Carteirinha vence em %d dia(s).', $diff)];
            }
        }
        if ($patient->getStatus() === PosOperatorioPaciente::STATUS_ALERTA) {
            $items[] = ['tipo' => 'alerta', 'texto' => 'Equipe em acompanhamento do seu caso.'];
        }

        return $items;
    }

    /** @return list<array<string, string>> */
    private function demoNotifications(): array
    {
        return [
            ['tipo' => 'info', 'texto' => 'Questionário de hoje disponível no portal.'],
            ['tipo' => 'aviso', 'texto' => 'Retorno D+7 previsto para 15/07/2026.'],
        ];
    }

    /** @param array<string, mixed> $portal @return list<array<string, mixed>> */
    private function buildRetornos(PosOperatorioPaciente $patient, array $portal): array
    {
        $dia = $patient->getDiaPosOperatorio();
        if ($dia === null) {
            return [];
        }

        $retornos = [];
        foreach ([7, 14] as $marco) {
            if ($dia <= $marco) {
                $data = $patient->getDataCirurgia()?->modify(sprintf('+%d days', $marco));
                $retornos[] = [
                    'marco' => sprintf('D+%d', $marco),
                    'data' => $data?->format('d/m/Y') ?? '—',
                    'status' => $dia >= $marco ? 'realizado' : 'agendado',
                    'local' => $patient->getUnidade()?->getNome() ?? $patient->getEmpresa()->getNome(),
                ];
            }
        }

        return $retornos;
    }

    /** @param list<PosOperatorioPaciente> $dependents @return list<array<string, mixed>> */
    private function formatDependents(array $dependents): array
    {
        return array_map(static fn (PosOperatorioPaciente $p): array => [
            'id' => $p->getId(),
            'nome' => $p->getNome(),
            'codigo' => $p->getCodigo(),
            'status_clinico' => $p->getStatusClinicoLabel(),
        ], $dependents);
    }

    private function milestoneDesc(int $dia): string
    {
        return match ($dia) {
            0 => 'Dia da cirurgia',
            3 => 'Cuidados iniciais e curativos',
            7 => 'Retorno ambulatorial',
            14 => 'Alta do acompanhamento',
            default => 'Marco da recuperação',
        };
    }
}
