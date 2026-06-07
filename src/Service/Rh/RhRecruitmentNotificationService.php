<?php

namespace App\Service\Rh;

use App\Entity\Empresa;
use App\Entity\RhCandidato;
use App\Entity\RhCandidatoAprovacao;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Rh\RhCandidatoEtapa;
use App\Rh\RhCandidatoOrigem;
use App\Security\ProductGrantAccess;
use App\Service\PlatformNotificationService;

final class RhRecruitmentNotificationService
{
    public function __construct(
        private PlatformNotificationService $notifications,
        private UserRepository $userRepo,
        private ProductGrantAccess $grants,
    ) {}

    public function notifyNovaCandidatura(RhCandidato $candidato): void
    {
        $empresa = $candidato->getVaga()->getEmpresa();
        $vaga = $candidato->getVaga()->getTitulo();
        $origem = RhCandidatoOrigem::label($candidato->getOrigem());

        $this->notifications->notifyMany(
            $empresa,
            $this->findRecruitmentManagers($empresa),
            'recrutamento',
            'candidatura_nova',
            'Nova candidatura',
            sprintf('%s se candidatou à vaga "%s" (%s).', $candidato->getNome(), $vaga, $origem),
            'app_recrutamento_candidatos_show',
            ['id' => $candidato->getId()],
            'fa-user-plus',
            'info',
        );
    }

    public function notifyEntrevistaAgendada(RhCandidato $candidato, ?User $actor = null): void
    {
        $empresa = $candidato->getVaga()->getEmpresa();
        $when = $candidato->getEntrevistaEm()?->format('d/m/Y H:i') ?? '';
        $mensagem = sprintf(
            'Entrevista com %s agendada para %s na vaga "%s".',
            $candidato->getNome(),
            $when,
            $candidato->getVaga()->getTitulo(),
        );

        $entrevistador = $candidato->getEntrevistaEntrevistador();
        if ($entrevistador !== null) {
            $this->notifications->notify(
                $empresa,
                $entrevistador,
                'recrutamento',
                'entrevista_agendada',
                'Entrevista agendada',
                $mensagem,
                'app_recrutamento_candidatos_show',
                ['id' => $candidato->getId()],
                'fa-calendar-check',
                'info',
            );
        }

        $this->notifications->notifyMany(
            $empresa,
            $this->findRecruitmentManagers($empresa),
            'recrutamento',
            'entrevista_agendada',
            'Entrevista agendada',
            $mensagem,
            'app_recrutamento_candidatos_show',
            ['id' => $candidato->getId()],
            'fa-calendar-check',
            'info',
            $entrevistador,
        );
    }

    public function notifyAprovacaoPendente(RhCandidatoAprovacao $aprovacao): void
    {
        $candidato = $aprovacao->getCandidato();
        $empresa = $candidato->getVaga()->getEmpresa();
        $etapa = RhCandidatoEtapa::label($aprovacao->getEtapaDestino());
        $solicitante = $aprovacao->getSolicitante()->getNome() ?? $aprovacao->getSolicitante()->getUserIdentifier();

        $this->notifications->notifyMany(
            $empresa,
            $this->findRecruitmentManagers($empresa),
            'recrutamento',
            'aprovacao_pendente',
            'Aprovação pendente',
            sprintf(
                '%s solicitou mover %s para a etapa "%s".',
                $solicitante,
                $candidato->getNome(),
                $etapa,
            ),
            'app_recrutamento_candidatos_show',
            ['id' => $candidato->getId()],
            'fa-user-check',
            'warning',
            $aprovacao->getSolicitante(),
        );
    }

    public function notifyAprovacaoDecidida(RhCandidatoAprovacao $aprovacao, bool $aprovar, User $aprovador): void
    {
        $candidato = $aprovacao->getCandidato();
        $empresa = $candidato->getVaga()->getEmpresa();
        $etapa = RhCandidatoEtapa::label($aprovacao->getEtapaDestino());
        $decisor = $aprovador->getNome() ?? $aprovador->getUserIdentifier();

        $this->notifications->notify(
            $empresa,
            $aprovacao->getSolicitante(),
            'recrutamento',
            $aprovar ? 'aprovacao_aprovada' : 'aprovacao_rejeitada',
            $aprovar ? 'Movimentação aprovada' : 'Movimentação rejeitada',
            sprintf(
                '%s %s a solicitação para mover %s para "%s".',
                $decisor,
                $aprovar ? 'aprovou' : 'rejeitou',
                $candidato->getNome(),
                $etapa,
            ),
            'app_recrutamento_candidatos_show',
            ['id' => $candidato->getId()],
            $aprovar ? 'fa-circle-check' : 'fa-circle-xmark',
            $aprovar ? 'success' : 'warning',
        );
    }

    public function notifyEtapaMudanca(RhCandidato $candidato, string $from, string $to, ?User $actor = null): void
    {
        if ($from === $to) {
            return;
        }

        $empresa = $candidato->getVaga()->getEmpresa();
        $actorName = $actor?->getNome() ?? $actor?->getUserIdentifier() ?? 'Equipe';
        $mensagem = sprintf(
            '%s moveu %s de "%s" para "%s".',
            $actorName,
            $candidato->getNome(),
            RhCandidatoEtapa::label($from),
            RhCandidatoEtapa::label($to),
        );

        $this->notifications->notifyMany(
            $empresa,
            $this->findRecruitmentManagers($empresa),
            'recrutamento',
            'etapa_mudanca',
            'Candidato movido no pipeline',
            $mensagem,
            'app_recrutamento_candidatos_show',
            ['id' => $candidato->getId()],
            'fa-arrows-left-right',
            'info',
            $actor,
        );
    }

    /** @return list<User> */
    private function findRecruitmentManagers(Empresa $empresa): array
    {
        $managers = [];
        foreach ($this->userRepo->findActiveByEmpresa($empresa) as $user) {
            if ($this->grants->grantAtLeast($user, 'hub_recrutamento', 'pipeline', 'GESTOR_EQUIPE')
                || $this->grants->grantAtLeast($user, 'product_rh', 'recrutamento', 'GESTOR_EQUIPE')) {
                $managers[] = $user;
            }
        }

        return $managers;
    }
}
