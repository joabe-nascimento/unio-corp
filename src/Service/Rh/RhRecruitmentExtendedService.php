<?php

namespace App\Service\Rh;

use App\Entity\Empresa;
use App\Entity\RhCandidato;
use App\Entity\RhCandidatoAprovacao;
use App\Entity\RhVaga;
use App\Entity\User;
use App\Exception\RhProcessException;
use App\Repository\RhCandidatoAprovacaoRepository;
use App\Rh\RhCandidatoEtapa;
use App\Rh\RhEntrevistaTipo;
use App\Rh\RhRecruitmentApprovalPolicy;
use App\Rh\RhScorecardCriteria;
use App\Security\ProductGrantAccess;
use Doctrine\ORM\EntityManagerInterface;

final class RhRecruitmentExtendedService
{
    public function __construct(
        private EntityManagerInterface $em,
        private RhRecrutamentoService $recrutamento,
        private RhRecrutamentoEmailService $emails,
        private RhCandidatoAprovacaoRepository $aprovacaoRepo,
        private RhAuditService $audit,
        private ProductGrantAccess $grants,
        private RhRecruitmentNotificationService $recruitmentNotifications,
    ) {}

    public function scheduleEntrevista(
        RhCandidato $candidato,
        \DateTimeImmutable $when,
        ?string $link,
        string $tipo,
        ?User $entrevistador,
        ?User $actor,
    ): RhCandidato {
        if ($when < new \DateTimeImmutable('-5 minutes')) {
            throw new RhProcessException('Informe data e hora futuras para a entrevista.');
        }

        if (!RhEntrevistaTipo::isValid($tipo)) {
            throw new RhProcessException('Tipo de entrevista inválido.');
        }

        if ($entrevistador !== null && $entrevistador->getEmpresa()?->getId() !== $candidato->getVaga()->getEmpresa()->getId()) {
            throw new RhProcessException('Entrevistador inválido para esta empresa.');
        }

        if ($entrevistador === null && $actor !== null) {
            $entrevistador = $actor;
        }

        $linkTrimmed = $link !== null ? trim($link) : '';
        if ($tipo === RhEntrevistaTipo::ONLINE && $linkTrimmed === '') {
            throw new RhProcessException('Informe o link da reunião para entrevistas online.');
        }
        if ($tipo === RhEntrevistaTipo::PRESENCIAL && $linkTrimmed === '') {
            throw new RhProcessException('Informe o endereço ou local da entrevista presencial.');
        }

        $candidato->setEntrevistaEm($when);
        $candidato->setEntrevistaLink($linkTrimmed !== '' ? $linkTrimmed : null);
        $candidato->setEntrevistaTipo($tipo);
        $candidato->setEntrevistaEntrevistador($entrevistador);
        $this->em->flush();

        $this->emails->queueEntrevistaAgendada($candidato);
        $this->recruitmentNotifications->notifyEntrevistaAgendada($candidato, $actor);
        $this->audit->log(
            $candidato->getVaga()->getEmpresa(),
            $actor,
            'recrutamento',
            'agendar_entrevista',
            'rh_candidato',
            $candidato->getId(),
            [
                'entrevista_em' => $when->format('c'),
                'entrevista_tipo' => $tipo,
                'entrevistador_id' => $entrevistador?->getId(),
            ],
        );

        return $candidato;
    }

    /**
     * @param array<string, int> $scores
     */
    public function saveScorecard(
        RhCandidato $candidato,
        string $etapa,
        array $scores,
        ?string $comentario,
        ?User $actor,
    ): RhCandidato {
        if (!RhCandidatoEtapa::isValid($etapa)) {
            throw new RhProcessException('Etapa inválida para scorecard.');
        }

        $criteria = RhScorecardCriteria::forEtapa($etapa);
        $normalized = [];
        $total = 0;
        $count = 0;
        foreach ($criteria as $item) {
            $id = $item['id'];
            $val = $scores[$id] ?? null;
            if ($val === null || $val === '') {
                continue;
            }
            $score = max(1, min(5, (int) $val));
            $normalized[$id] = $score;
            $total += $score;
            ++$count;
        }

        if ($count === 0) {
            throw new RhProcessException('Informe ao menos um critério avaliado.');
        }

        $media = round($total / $count, 1);
        $all = $candidato->getScorecards() ?? [];
        $all[$etapa] = [
            'scores' => $normalized,
            'media' => $media,
            'comentario' => $comentario !== '' ? trim((string) $comentario) : null,
            'autor' => $actor?->getNome() ?? $actor?->getUserIdentifier(),
            'atualizado_em' => (new \DateTimeImmutable())->format('c'),
        ];
        $candidato->setScorecards($all);
        $candidato->setAvaliacao((int) round($media));
        $this->em->flush();

        $this->audit->log(
            $candidato->getVaga()->getEmpresa(),
            $actor,
            'recrutamento',
            'salvar_scorecard',
            'rh_candidato',
            $candidato->getId(),
            ['etapa' => $etapa, 'media' => $media],
        );

        return $candidato;
    }

    public function moveEtapaWithPolicy(
        RhCandidato $candidato,
        string $etapa,
        ?User $actor,
        ?string $motivo = null,
    ): RhCandidato|RhCandidatoAprovacao {
        if (RhRecruitmentApprovalPolicy::exigeAprovacao($etapa) && !$this->canApprove($actor)) {
            if ($this->aprovacaoRepo->hasPendenteForCandidatoEtapa($candidato, $etapa)) {
                throw new RhProcessException('Já existe uma solicitação de aprovação pendente para esta etapa.');
            }

            return $this->requestApproval($candidato, $etapa, $actor, $motivo);
        }

        $from = $candidato->getEtapa();
        $updated = $this->recrutamento->moveCandidatoEtapa($candidato, $etapa, $actor, $motivo);
        $this->emails->queueEtapaMudanca($updated, $from, $etapa);
        $this->recruitmentNotifications->notifyEtapaMudanca($updated, $from, $etapa, $actor);

        return $updated;
    }

    public function requestApproval(
        RhCandidato $candidato,
        string $etapaDestino,
        ?User $solicitante,
        ?string $comentario = null,
    ): RhCandidatoAprovacao {
        if (!RhCandidatoEtapa::isValid($etapaDestino)) {
            throw new RhProcessException('Etapa inválida.');
        }

        if ($solicitante === null) {
            throw new RhProcessException('Usuário solicitante inválido.');
        }

        $aprovacao = new RhCandidatoAprovacao();
        $aprovacao->setCandidato($candidato);
        $aprovacao->setSolicitante($solicitante);
        $aprovacao->setEtapaDestino($etapaDestino);
        $aprovacao->setComentario($comentario !== '' ? trim((string) $comentario) : null);

        $this->em->persist($aprovacao);
        $this->em->flush();

        $this->recruitmentNotifications->notifyAprovacaoPendente($aprovacao);
        $this->audit->log(
            $candidato->getVaga()->getEmpresa(),
            $solicitante,
            'recrutamento',
            'solicitar_aprovacao',
            'rh_candidato',
            $candidato->getId(),
            ['etapa_destino' => $etapaDestino],
        );

        return $aprovacao;
    }

    public function decideApproval(
        RhCandidatoAprovacao $aprovacao,
        bool $aprovar,
        User $aprovador,
        ?string $comentario = null,
    ): RhCandidato {
        if (!$this->canApprove($aprovador)) {
            throw new RhProcessException('Sem permissão para aprovar movimentações.');
        }

        if ($aprovacao->getStatus() !== RhCandidatoAprovacao::STATUS_PENDENTE) {
            throw new RhProcessException('Esta solicitação já foi decidida.');
        }

        $aprovacao->setAprovador($aprovador);
        $aprovacao->setDecididoEm(new \DateTimeImmutable());
        $aprovacao->setStatus($aprovar ? RhCandidatoAprovacao::STATUS_APROVADO : RhCandidatoAprovacao::STATUS_REJEITADO);
        if ($comentario !== null && trim($comentario) !== '') {
            $aprovacao->setComentario(trim($comentario));
        }

        $candidato = $aprovacao->getCandidato();
        if ($aprovar) {
            $from = $candidato->getEtapa();
            $this->recrutamento->moveCandidatoEtapa($candidato, $aprovacao->getEtapaDestino(), $aprovador);
            $this->emails->queueEtapaMudanca($candidato, $from, $aprovacao->getEtapaDestino());
            $this->recruitmentNotifications->notifyEtapaMudanca($candidato, $from, $aprovacao->getEtapaDestino(), $aprovador);
        }

        $this->em->flush();

        $this->recruitmentNotifications->notifyAprovacaoDecidida($aprovacao, $aprovar, $aprovador);

        return $candidato;
    }

    /** @return list<RhCandidatoAprovacao> */
    public function listPendentes(Empresa $empresa): array
    {
        return $this->aprovacaoRepo->findPendentesForEmpresa($empresa);
    }

    /** @return list<RhCandidatoAprovacao> */
    public function listForCandidato(RhCandidato $candidato): array
    {
        return $this->aprovacaoRepo->findForCandidato($candidato);
    }

    /** @param array<string, mixed> $config */
    public function saveIntegracoes(Empresa $empresa, array $config): Empresa
    {
        $current = $empresa->getRecruitmentIntegracoes() ?? [];
        $merged = array_merge($current, [
            'linkedin_share' => (bool) ($config['linkedin_share'] ?? false),
            'calendar_google' => (bool) ($config['calendar_google'] ?? true),
            'hris_webhook_url' => trim((string) ($config['hris_webhook_url'] ?? '')),
            'hris_webhook_secret' => trim((string) ($config['hris_webhook_secret'] ?? '')),
            'hris_auto_export' => (bool) ($config['hris_auto_export'] ?? false),
        ]);
        $empresa->setRecruitmentIntegracoes($merged);
        $this->em->flush();

        return $empresa;
    }

    public function buildLinkedInShareUrl(RhVaga $vaga, string $baseUrl): ?string
    {
        $integracoes = $vaga->getEmpresa()->getRecruitmentIntegracoes() ?? [];
        if (!($integracoes['linkedin_share'] ?? false)) {
            return null;
        }

        if ($vaga->getPublicadaEm() === null || $vaga->getSlug() === null) {
            return null;
        }

        $empresa = $vaga->getEmpresa();
        $jobUrl = rtrim($baseUrl, '/') . '/carreiras/' . $empresa->getSlug() . '/' . $vaga->getSlug();

        return 'https://www.linkedin.com/sharing/share-offsite/?url=' . rawurlencode($jobUrl);
    }

    public function notifyHrisWebhook(RhCandidato $candidato, string $event): void
    {
        $integracoes = $candidato->getVaga()->getEmpresa()->getRecruitmentIntegracoes() ?? [];
        $url = trim((string) ($integracoes['hris_webhook_url'] ?? ''));
        if ($url === '' || !($integracoes['hris_auto_export'] ?? false)) {
            return;
        }

        $payload = json_encode([
            'event' => $event,
            'candidato_id' => $candidato->getId(),
            'nome' => $candidato->getNome(),
            'email' => $candidato->getEmail(),
            'etapa' => $candidato->getEtapa(),
            'vaga' => $candidato->getVaga()->getTitulo(),
        ], JSON_THROW_ON_ERROR);

        $secret = (string) ($integracoes['hris_webhook_secret'] ?? '');
        $headers = ['Content-Type: application/json'];
        if ($secret !== '') {
            $headers[] = 'X-Huplex-Signature: ' . hash_hmac('sha256', $payload, $secret);
        }

        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $payload,
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ]);

        @file_get_contents($url, false, $ctx);
    }

    private function canApprove(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $this->grants->grantAtLeast($user, 'hub_recrutamento', 'pipeline', 'GESTOR_EQUIPE')
            || $this->grants->grantAtLeast($user, 'product_rh', 'recrutamento', 'GESTOR_EQUIPE');
    }
}
