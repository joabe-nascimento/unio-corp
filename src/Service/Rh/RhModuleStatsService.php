<?php

namespace App\Service\Rh;

use App\Entity\Empresa;
use App\Repository\FuncionarioRepository;
use App\Repository\RhAssinaturaEnvelopeRepository;
use App\Repository\RhAuditLogRepository;
use App\Repository\RhComunicadoRepository;
use App\Repository\RhEsocialLoteRepository;
use App\Repository\RhFeriasRepository;
use App\Repository\RhFolhaCompetenciaRepository;
use App\Repository\RhFolhaRubricaRepository;
use App\Repository\RhPontoRegistroRepository;
use App\Repository\RhProvisaoRepository;
use App\Repository\RhVagaRepository;
use App\Repository\RhWorkflowTemplateRepository;
use App\Rh\RhModuleCatalog;
use App\Service\RhFeriasService;
use App\Service\RhOffboardingService;
use App\Service\RhOnboardingService;

class RhModuleStatsService
{
    public function __construct(
        private FuncionarioRepository $funcionarioRepo,
        private RhOnboardingService $onboarding,
        private RhOffboardingService $offboarding,
        private RhFeriasRepository $feriasRepo,
        private RhFolhaCompetenciaRepository $folhaRepo,
        private RhFeriasService $feriasService,
        private RhVagaRepository $vagaRepo,
        private RhPontoRegistroRepository $pontoRepo,
        private RhComunicadoRepository $comunicadoRepo,
        private RhAuditLogRepository $auditRepo,
        private RhWorkflowTemplateRepository $workflowRepo,
        private RhFolhaRubricaRepository $rubricaRepo,
        private RhProvisaoRepository $provisaoRepo,
        private RhEsocialLoteRepository $esocialRepo,
        private RhAssinaturaEnvelopeRepository $assinaturaRepo,
    ) {}

    /**
     * Módulos para hub_launcher com contadores.
     *
     * @return list<array<string, mixed>>
     */
    public function hubModules(Empresa $empresa): array
    {
        $this->feriasService->syncStatusByDate($empresa);

        $headcount = $this->funcionarioRepo->countByStatusGrouped($empresa);
        $ativos = $headcount['ATIVO'] ?? 0;

        $counts = [
            'funcionarios' => ['count' => $ativos, 'label' => 'ativos'],
            'admissoes' => ['count' => $this->onboarding->countOpen($empresa), 'label' => 'em aberto'],
            'demissoes' => ['count' => $this->offboarding->countOpen($empresa), 'label' => 'em aberto'],
            'ferias' => ['count' => $this->feriasRepo->countByStatus($empresa, 'SOLICITADA'), 'label' => 'pendentes'],
            'folha' => ['count' => $this->folhaRepo->count(['empresa' => $empresa]), 'label' => 'competências'],
            'portal' => ['count' => $this->funcionarioRepo->countWithPlatformUser($empresa), 'label' => 'com acesso'],
            'recrutamento' => ['count' => $this->vagaRepo->countAbertasByEmpresa($empresa), 'label' => 'vagas abertas'],
            'ponto' => ['count' => $this->pontoRepo->countHojeByEmpresa($empresa), 'label' => 'batidas hoje'],
            'comunicacao' => ['count' => $this->comunicadoRepo->countAtivosByEmpresa($empresa), 'label' => 'comunicados'],
            'organograma' => ['count' => $this->funcionarioRepo->countWithGestor($empresa), 'label' => 'com gestor'],
            'auditoria' => ['count' => $this->auditRepo->countByEmpresa($empresa), 'label' => 'registros'],
            'workflows' => ['count' => $this->workflowRepo->countAtivosByEmpresa($empresa), 'label' => 'templates'],
            'folha_legal' => ['count' => $this->rubricaRepo->countByEmpresa($empresa), 'label' => 'rubricas'],
            'contabilidade' => ['count' => $this->provisaoRepo->countByEmpresa($empresa), 'label' => 'provisões'],
            'esocial' => ['count' => $this->esocialRepo->countPendingByEmpresa($empresa), 'label' => 'na fila'],
            'assinatura' => ['count' => $this->assinaturaRepo->countByEmpresa($empresa), 'label' => 'envelopes'],
            'analytics' => ['count' => $ativos + $this->onboarding->countOpen($empresa), 'label' => 'indicadores'],
        ];

        $modules = [];
        foreach (RhModuleCatalog::all() as $mod) {
            $id = $mod['id'];
            $stat = $counts[$id] ?? ['count' => 0, 'label' => '—'];
            $count = (int) ($stat['count'] ?? 0);
            $pulse = (bool) ($mod['activity_pulse'] ?? false);
            $modules[] = array_merge($mod, [
                'count' => $count,
                'count_label' => $stat['label'],
                'has_activity' => $pulse && $count > 0,
                'grant_scope' => 'product_rh',
                'grant_product' => $mod['grant'],
                'group' => $mod['group'] ?? RhModuleCatalog::GROUP_OPERACAO,
                'short' => $mod['short'] ?? $mod['title'],
            ]);
        }

        usort($modules, static function (array $a, array $b): int {
            $active = ($b['has_activity'] ?? false) <=> ($a['has_activity'] ?? false);
            if ($active !== 0) {
                return $active;
            }

            return strcmp((string) ($a['short'] ?? ''), (string) ($b['short'] ?? ''));
        });

        return $modules;
    }
}
