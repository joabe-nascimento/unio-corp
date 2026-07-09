<?php

namespace App\Service\Organismo;

use App\Entity\Empresa;
use App\Entity\RhFerias;
use App\Entity\RhOnboardingProcess;
use App\Entity\User;
use App\Repository\RhFeriasRepository;
use App\Repository\RhOnboardingProcessRepository;
use App\Repository\TiChamadoRepository;
use App\Service\NavigationService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Monta cenas ativas e aguardando — dados reais quando existem, complemento mock no PoC.
 */
final class PulsoCenaProvider
{
    public function __construct(
        private RhOnboardingProcessRepository $onboardingRepo,
        private RhFeriasRepository $feriasRepo,
        private TiChamadoRepository $chamadoRepo,
        private NavigationService $navigation,
        private UrlGeneratorInterface $router,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getCenas(User $user, ?Empresa $empresa): array
    {
        if ($empresa === null) {
            return $this->mockCenas(null);
        }

        $cenas = [];

        if ($this->navigation->showModuloRh($user)) {
            foreach ($this->onboardingRepo->findOpenRecent($empresa, 3) as $process) {
                $cenas[] = $this->cenaFromOnboarding($process);
            }
        }

        if ($this->navigation->showModuloRh($user) && $this->feriasRepo->countByStatus($empresa, RhFerias::STATUS_SOLICITADA) > 0) {
            $cenas[] = [
                'id' => 'ferias-pendentes',
                'titulo' => 'Férias aguardando aprovação',
                'tipo' => 'remuneracao',
                'praticas' => ['tempo', 'remuneracao'],
                'estado' => 'aguardando',
                'dias_aberta' => 0,
                'condutor' => null,
                'url' => $this->router->generate('app_rh_ferias'),
                'mock' => false,
            ];
        }

        if ($this->hasTiAccess($user)) {
            $openTickets = $this->chamadoRepo->countOpen($empresa);
            if ($openTickets > 0) {
                $cenas[] = [
                    'id' => 'ti-chamados-abertos',
                    'titulo' => sprintf('%d chamado(s) em sustentação', $openTickets),
                    'tipo' => 'sustentacao',
                    'praticas' => ['sustentacao'],
                    'estado' => 'ativa',
                    'dias_aberta' => 0,
                    'condutor' => null,
                    'url' => $this->router->generate('app_ti_chamados'),
                    'mock' => false,
                ];
            }
        }

        if (\count($cenas) < 2) {
            foreach ($this->mockCenas($empresa) as $mock) {
                if (\count($cenas) >= 4) {
                    break;
                }
                $cenas[] = $mock;
            }
        }

        return $cenas;
    }

    /**
     * @return array<string, mixed>
     */
    private function cenaFromOnboarding(RhOnboardingProcess $process): array
    {
        $nome = $process->getNome() ?: 'Novo membro';
        $criado = $process->getCriadoEm();
        $dias = max(0, (int) $criado->diff(new \DateTimeImmutable())->days);

        return [
            'id' => 'admissao-' . $process->getId(),
            'titulo' => 'Admissão — ' . $nome,
            'tipo' => 'admissao',
            'praticas' => ['vida_membro', 'documentos'],
            'estado' => 'ativa',
            'dias_aberta' => $dias,
            'condutor' => null,
            'url' => $this->router->generate('app_rh_admissoes_show', ['id' => $process->getId()]),
            'mock' => false,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mockCenas(?Empresa $empresa): array
    {
        $suffix = $empresa !== null ? (string) $empresa->getId() : '0';

        return [
            [
                'id' => 'mock-folha-' . $suffix,
                'titulo' => 'Fechamento folha — ciclo atual',
                'tipo' => 'remuneracao',
                'praticas' => ['remuneracao'],
                'estado' => 'aguardando',
                'dias_aberta' => 5,
                'condutor' => 'Arquiteto',
                'url' => $this->router->generate('app_rh_folha'),
                'mock' => true,
            ],
        ];
    }

    private function hasTiAccess(User $user): bool
    {
        foreach ($this->navigation->getVisiblePlannedHubs($user) as $hub) {
            if (($hub['id'] ?? '') === 'ti') {
                return true;
            }
        }

        return false;
    }
}
