<?php

namespace App\Service\Ti;

use App\Entity\Empresa;
use App\Entity\TiChamado;
use App\Entity\User;
use App\Platform\AiAssistant;
use App\Repository\TiAtivoRepository;
use App\Security\ProductGrantAccess;
use App\Security\TiGrantPolicy;
use App\Repository\TiChamadoRepository;
use App\Repository\TiProblemaRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class TiChamadoService
{
    public function __construct(
        private EntityManagerInterface $em,
        private TiChamadoRepository $repository,
        private TiHeliaService $helia,
        private TiChamadoAttachmentService $attachments,
        private UserRepository $userRepository,
        private TiNotificationService $notifications,
        private TiPlaybookService $playbooks,
        private TiAtivoRepository $ativoRepository,
        private TiProblemaRepository $problemaRepository,
        private ProductGrantAccess $grants,
    ) {}

    /** @return array<string, mixed> */
    public function mapTicketForDisplay(TiChamado $chamado): array
    {
        return $this->mapTicket($chamado);
    }

    /** @return list<array<string, mixed>> */
    public function all(Empresa $empresa): array
    {
        return array_map(
            fn (TiChamado $c) => $this->mapTicket($c),
            $this->repository->findByEmpresa($empresa),
        );
    }

    /** @return list<array<string, mixed>> */
    public function allSorted(Empresa $empresa): array
    {
        $priorityOrder = ['P1' => 1, 'P2' => 2, 'P3' => 3, 'P4' => 4];
        $tickets = $this->all($empresa);
        usort($tickets, static function (array $a, array $b) use ($priorityOrder): int {
            $pa = $priorityOrder[$a['priority'] ?? 'P4'] ?? 5;
            $pb = $priorityOrder[$b['priority'] ?? 'P4'] ?? 5;
            if ($pa !== $pb) {
                return $pa <=> $pb;
            }

            return strcmp($b['opened_at'] ?? '', $a['opened_at'] ?? '');
        });

        return $tickets;
    }

    /** @return array<string, list<array<string, mixed>>> */
    public function board(Empresa $empresa): array
    {
        $board = [
            TiChamado::STATUS_NOVO => [],
            TiChamado::STATUS_EM_ANALISE => [],
            TiChamado::STATUS_EM_EXECUCAO => [],
            TiChamado::STATUS_AGUARDANDO => [],
            TiChamado::STATUS_RESOLVIDO => [],
        ];

        foreach ($this->repository->findByEmpresa($empresa) as $chamado) {
            $status = $chamado->getStatus();
            if (!isset($board[$status])) {
                $status = TiChamado::STATUS_NOVO;
            }
            $board[$status][] = $this->mapTicket($chamado);
        }

        return $board;
    }

    public function find(Empresa $empresa, string $id): ?array
    {
        $chamado = $this->repository->findByCodigo($empresa, $id);

        return $chamado ? $this->mapTicket($chamado) : null;
    }

    public function findEntity(Empresa $empresa, string $codigo): ?TiChamado
    {
        return $this->repository->findByCodigo($empresa, $codigo);
    }

    public function delete(Empresa $empresa, string $codigo): void
    {
        $chamado = $this->repository->findByCodigo($empresa, $codigo);
        if ($chamado === null) {
            throw new \InvalidArgumentException('Chamado não encontrado.');
        }

        $this->attachments->removeAllForChamado($chamado);
        $this->em->remove($chamado);
        $this->em->flush();
    }

    public function assignTechnician(Empresa $empresa, string $codigo, int $technicianId, User $actor): array
    {
        $chamado = $this->requireEntity($empresa, $codigo);
        $technician = $this->userRepository->find($technicianId);
        if (
            !$technician instanceof User
            || !$technician->isAtivo()
            || $technician->getEmpresa()?->getId() !== $empresa->getId()
        ) {
            throw new \InvalidArgumentException('Técnico inválido.');
        }

        $actorName = $this->actorName($actor);
        $techName = $technician->getNome() ?: $technician->getEmail() ?: 'Técnico';
        $chamado->setResponsavel($technician);
        if ($chamado->getStatus() === TiChamado::STATUS_NOVO) {
            $chamado->setStatus(TiChamado::STATUS_EM_ANALISE);
            $chamado->addTimelineEvent('Status alterado para Em análise', $actorName);
        }
        $chamado->addTimelineEvent('Atribuído a ' . $techName, $actorName);
        $chamado->touch();
        $this->em->flush();

        $this->notifications->notify(
            $empresa,
            $technician,
            'atribuicao',
            'Chamado ' . $codigo . ' atribuído',
            'Você foi designado para: ' . $chamado->getTitulo(),
            '/ti/chamados/' . $codigo,
        );

        return $this->mapTicket($chamado);
    }

    public function addNote(Empresa $empresa, string $codigo, string $note, User $actor): array
    {
        return $this->operatorRespond($empresa, $codigo, $actor, $note);
    }

    /**
     * Resposta da TI com mensagem, anexos, status e atribuição opcionais num único envio.
     *
     * @param list<UploadedFile> $files
     */
    public function operatorRespond(
        Empresa $empresa,
        string $codigo,
        User $actor,
        string $message = '',
        ?string $status = null,
        ?int $technicianId = null,
        array $files = [],
    ): array {
        $message = trim($message);
        if ($message !== '' && mb_strlen($message) > 2000) {
            throw new \InvalidArgumentException('Mensagem muito longa (máx. 2000 caracteres).');
        }

        $chamado = $this->requireEntity($empresa, $codigo);
        $actorName = $this->actorName($actor);
        $didSomething = false;
        $notifyReply = false;
        $notifyResolved = false;

        if ($message !== '') {
            $chamado->addTimelineEvent('Resposta da TI: ' . $message, $actorName);
            $didSomething = true;
            $notifyReply = true;
        }

        if ($status !== null && $status !== '' && $status !== $chamado->getStatus()) {
            $valid = [
                TiChamado::STATUS_NOVO,
                TiChamado::STATUS_EM_ANALISE,
                TiChamado::STATUS_EM_EXECUCAO,
                TiChamado::STATUS_AGUARDANDO,
                TiChamado::STATUS_RESOLVIDO,
            ];
            if (!\in_array($status, $valid, true)) {
                throw new \InvalidArgumentException('Status inválido.');
            }

            $wasResolved = $chamado->getStatus() === TiChamado::STATUS_RESOLVIDO;
            $labels = TiReferenceData::statusLabels();
            $chamado->setStatus($status);
            if ($status === TiChamado::STATUS_RESOLVIDO) {
                $chamado->setResolvidoEm(new \DateTimeImmutable());
                if (!$chamado->isHeliaRevisado()) {
                    $chamado->setHeliaRevisado(true);
                }
                $notifyResolved = true;
            } else {
                $chamado->setResolvidoEm(null);
            }
            if ($wasResolved && $status !== TiChamado::STATUS_RESOLVIDO) {
                $chamado->addTimelineEvent('Chamado reaberto', $actorName);
            }
            $chamado->addTimelineEvent(
                'Status alterado para ' . ($labels[$status] ?? $status),
                $actorName,
            );
            $didSomething = true;
        }

        if ($technicianId !== null && $technicianId > 0) {
            $currentId = $chamado->getResponsavel()?->getId();
            if ($currentId !== $technicianId) {
                $technician = $this->userRepository->find($technicianId);
                if (
                    !$technician instanceof User
                    || !$technician->isAtivo()
                    || $technician->getEmpresa()?->getId() !== $empresa->getId()
                ) {
                    throw new \InvalidArgumentException('Técnico inválido.');
                }

                $techName = $technician->getNome() ?: $technician->getEmail() ?: 'Técnico';
                $chamado->setResponsavel($technician);
                if ($chamado->getStatus() === TiChamado::STATUS_NOVO) {
                    $chamado->setStatus(TiChamado::STATUS_EM_ANALISE);
                    $chamado->addTimelineEvent('Status alterado para Em análise', $actorName);
                }
                $chamado->addTimelineEvent('Atribuído a ' . $techName, $actorName);
                $didSomething = true;

                $this->notifications->notify(
                    $empresa,
                    $technician,
                    'atribuicao',
                    'Chamado ' . $codigo . ' atribuído',
                    'Você foi designado para: ' . $chamado->getTitulo(),
                    '/ti/chamados/' . $codigo,
                );
            }
        }

        if ($files !== []) {
            $uploaded = $this->attachments->uploadForChamado($chamado, $files, $actor);
            if ($uploaded !== []) {
                $chamado->addTimelineEvent(
                    \count($uploaded) . ' anexo(s) adicionado(s)',
                    $actorName,
                );
                $didSomething = true;
            }
        }

        if (!$didSomething) {
            throw new \InvalidArgumentException('Escreva uma mensagem, altere o status, atribua um técnico ou anexe arquivos.');
        }

        $chamado->touch();
        $this->em->flush();

        if ($notifyReply) {
            $this->notifySolicitante(
                $chamado,
                'Nova resposta no chamado',
                sprintf('A equipe de TI respondeu em %s.', $chamado->getCodigo()),
            );
        }
        if ($notifyResolved) {
            $this->notifySolicitante(
                $chamado,
                'Chamado resolvido',
                sprintf('%s foi marcado como resolvido. Avalie o atendimento quando puder.', $chamado->getCodigo()),
            );
        }

        return $this->mapTicket($chamado);
    }

    /**
     * @param list<UploadedFile> $files
     */
    public function addReply(
        Empresa $empresa,
        string $codigo,
        string $message,
        User $actor,
        array $files = [],
        ?string $status = null,
    ): array {
        $message = trim($message);
        if ($message !== '' && mb_strlen($message) > 2000) {
            throw new \InvalidArgumentException('Mensagem muito longa (máx. 2000 caracteres).');
        }

        $chamado = $this->requireEntity($empresa, $codigo);
        if ($chamado->getSolicitante()->getId() !== $actor->getId()) {
            throw new \InvalidArgumentException('Apenas o solicitante pode enviar esta resposta.');
        }

        $actorName = $this->actorName($actor);
        $wasResolved = $chamado->getStatus() === TiChamado::STATUS_RESOLVIDO;
        $confirmResolved = $status === TiChamado::STATUS_RESOLVIDO;
        $reopen = $status === TiChamado::STATUS_EM_ANALISE && $wasResolved;

        if ($wasResolved && !$reopen) {
            throw new \InvalidArgumentException('Chamado resolvido. Selecione "Reabrir chamado" e descreva o motivo.');
        }

        if ($status !== null && $status !== '' && !$confirmResolved && !$reopen) {
            throw new \InvalidArgumentException('Você só pode confirmar o chamado como resolvido ou reabri-lo.');
        }

        if ($reopen && $message === '') {
            throw new \InvalidArgumentException('Descreva o motivo ao reabrir o chamado.');
        }

        if ($message === '' && $files === [] && !$confirmResolved && !$reopen) {
            throw new \InvalidArgumentException('Escreva uma mensagem, anexe um arquivo ou confirme a resolução.');
        }

        if ($message !== '') {
            $chamado->addTimelineEvent('Resposta do solicitante: ' . $message, $actorName);
        }
        if ($files !== []) {
            $uploaded = $this->attachments->uploadForChamado($chamado, $files, $actor);
            if ($uploaded !== []) {
                $chamado->addTimelineEvent(
                    \count($uploaded) . ' anexo(s) adicionado(s)',
                    $actorName,
                );
            }
        }

        if ($reopen) {
            $this->applyReopen($chamado, $actorName);
        } elseif ($confirmResolved) {
            $labels = TiReferenceData::statusLabels();
            $chamado->setStatus(TiChamado::STATUS_RESOLVIDO);
            $chamado->setResolvidoEm(new \DateTimeImmutable());
            if (!$chamado->isHeliaRevisado()) {
                $chamado->setHeliaRevisado(true);
            }
            $chamado->addTimelineEvent(
                'Status alterado para ' . ($labels[TiChamado::STATUS_RESOLVIDO] ?? 'Resolvido'),
                $actorName,
            );
        } elseif ($chamado->getStatus() === TiChamado::STATUS_AGUARDANDO && $message !== '') {
            $chamado->setStatus(TiChamado::STATUS_EM_ANALISE);
            $chamado->addTimelineEvent('Status alterado para Em análise', 'Sistema');
        }

        $chamado->touch();
        $this->em->flush();

        $this->notifyOperatorsOnReply($chamado, $actor);

        return $this->mapTicket($chamado);
    }

    /** @return list<array<string, mixed>> */
    public function extractChatMessages(array $ticket): array
    {
        $out = [];
        $seq = 0;
        foreach ($ticket['timeline'] ?? [] as $ev) {
            if (!\is_array($ev)) {
                continue;
            }
            $text = (string) ($ev['event'] ?? '');
            if (str_starts_with($text, 'Resposta do solicitante:')) {
                $out[] = $this->formatChatMessage($seq++, 'solicitante', $text, $ev, $ticket);
            } elseif (str_starts_with($text, 'Resposta da TI:')) {
                $out[] = $this->formatChatMessage($seq++, 'ti', $text, $ev, $ticket);
            } elseif (preg_match('/^(\d+) anexo\(s\) adicionado\(s\)$/', $text, $m)) {
                $out[] = $this->formatLegacyAttachmentChatMessage($seq++, (int) $m[1], $ev, $ticket);
            }
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    public function chatMessagesSince(array $ticket, int $after): array
    {
        return array_values(array_filter(
            $this->extractChatMessages($ticket),
            static fn (array $m): bool => ($m['seq'] ?? 0) >= $after,
        ));
    }

    /**
     * Envia mensagem de chat (sem alterar status ou atribuição).
     *
     * @param list<UploadedFile> $files
     */
    public function sendChatMessage(
        Empresa $empresa,
        string $codigo,
        User $actor,
        string $message,
        array $files = [],
        bool $asOperator = false,
    ): array {
        $message = trim($message);
        if ($message !== '' && mb_strlen($message) > 2000) {
            throw new \InvalidArgumentException('Mensagem muito longa (máx. 2000 caracteres).');
        }

        $chamado = $this->requireEntity($empresa, $codigo);
        $actorName = $this->actorName($actor);

        if ($asOperator) {
            if ($message === '' && $files === []) {
                throw new \InvalidArgumentException('Escreva uma mensagem ou anexe arquivos.');
            }
            $this->postChatExchange($chamado, $actor, $actorName, true, $message, $files);
            $chamado->touch();
            $this->em->flush();
            $this->notifySolicitante(
                $chamado,
                'Nova resposta no chamado',
                sprintf('A equipe de TI respondeu em %s.', $chamado->getCodigo()),
            );

            return $this->mapTicket($chamado);
        }

        if ($chamado->getSolicitante()->getId() !== $actor->getId()) {
            throw new \InvalidArgumentException('Apenas o solicitante pode enviar esta mensagem.');
        }
        if ($chamado->getStatus() === TiChamado::STATUS_RESOLVIDO) {
            throw new \InvalidArgumentException('Chamado resolvido. Use a aba Responder para reabrir.');
        }
        if ($message === '' && $files === []) {
            throw new \InvalidArgumentException('Escreva uma mensagem ou anexe arquivos.');
        }

        $this->postChatExchange($chamado, $actor, $actorName, false, $message, $files);
        if ($chamado->getStatus() === TiChamado::STATUS_AGUARDANDO && ($message !== '' || $files !== [])) {
            $chamado->setStatus(TiChamado::STATUS_EM_ANALISE);
            $chamado->addTimelineEvent('Status alterado para Em análise', 'Sistema');
        }

        $chamado->touch();
        $this->em->flush();
        $this->notifyOperatorsOnReply($chamado, $actor);

        return $this->mapTicket($chamado);
    }

    /**
     * Gestão do chamado pelo solicitante (confirmar resolução ou reabrir).
     */
    public function requesterGestao(
        Empresa $empresa,
        string $codigo,
        User $actor,
        ?string $status,
        string $motivo = '',
    ): array {
        $motivo = trim($motivo);
        $chamado = $this->requireEntity($empresa, $codigo);
        if ($chamado->getSolicitante()->getId() !== $actor->getId()) {
            throw new \InvalidArgumentException('Apenas o solicitante pode executar esta ação.');
        }

        $actorName = $this->actorName($actor);
        $wasResolved = $chamado->getStatus() === TiChamado::STATUS_RESOLVIDO;
        $confirmResolved = $status === TiChamado::STATUS_RESOLVIDO;
        $reopen = $status === TiChamado::STATUS_EM_ANALISE && $wasResolved;

        if ($status === null || $status === '') {
            throw new \InvalidArgumentException('Selecione uma situação.');
        }
        if ($wasResolved && !$reopen) {
            throw new \InvalidArgumentException('Chamado resolvido. Selecione "Reabrir chamado" e descreva o motivo.');
        }
        if (!$confirmResolved && !$reopen) {
            throw new \InvalidArgumentException('Você só pode confirmar o chamado como resolvido ou reabri-lo.');
        }
        if ($reopen && $motivo === '') {
            throw new \InvalidArgumentException('Descreva o motivo ao reabrir o chamado.');
        }

        if ($reopen) {
            if ($motivo !== '') {
                $chamado->addTimelineEvent('Resposta do solicitante: ' . $motivo, $actorName);
            }
            $this->applyReopen($chamado, $actorName);
        } elseif ($confirmResolved) {
            $labels = TiReferenceData::statusLabels();
            $chamado->setStatus(TiChamado::STATUS_RESOLVIDO);
            $chamado->setResolvidoEm(new \DateTimeImmutable());
            if (!$chamado->isHeliaRevisado()) {
                $chamado->setHeliaRevisado(true);
            }
            $chamado->addTimelineEvent(
                'Status alterado para ' . ($labels[TiChamado::STATUS_RESOLVIDO] ?? 'Resolvido'),
                $actorName,
            );
        }

        $chamado->touch();
        $this->em->flush();
        $this->notifyOperatorsOnReply($chamado, $actor);

        return $this->mapTicket($chamado);
    }

    /** @return array<string, mixed> */
    private function formatLegacyAttachmentChatMessage(int $seq, int $count, array $ev, array $ticket): array
    {
        $requester = (string) ($ticket['requester'] ?? '');
        $actor = (string) ($ev['actor'] ?? '');
        $role = ($requester !== '' && strcasecmp($actor, $requester) === 0) ? 'solicitante' : 'ti';

        return [
            'seq' => $seq,
            'role' => $role,
            'body' => '📎 ' . $count . ' anexo(s)',
            'at' => (string) ($ev['at'] ?? ''),
            'actor' => $actor,
            'display_name' => $role === 'ti'
                ? ($actor !== '' ? $actor : 'Equipe de TI')
                : ($requester !== '' ? $requester : 'Solicitante'),
        ];
    }

    /**
     * @param list<UploadedFile> $files
     */
    private function postChatExchange(
        TiChamado $chamado,
        User $actor,
        string $actorName,
        bool $asOperator,
        string $message,
        array $files,
    ): void {
        $uploaded = $files !== [] ? $this->attachments->uploadForChamado($chamado, $files, $actor) : [];
        $parts = [];
        if ($message !== '') {
            $parts[] = $message;
        }
        if ($uploaded !== []) {
            $parts[] = $this->summarizeUploadedAttachments($uploaded);
        }
        if ($parts === []) {
            return;
        }

        $prefix = $asOperator ? 'Resposta da TI: ' : 'Resposta do solicitante: ';
        $chamado->addTimelineEvent($prefix . implode("\n", $parts), $actorName);
    }

    /** @param list<\App\Entity\TiChamadoAnexo> $uploaded */
    private function summarizeUploadedAttachments(array $uploaded): string
    {
        $names = array_map(static fn ($a) => (string) $a->getNomeOriginal(), $uploaded);
        if (\count($names) === 1) {
            return '📎 ' . $names[0];
        }

        $preview = implode(', ', \array_slice($names, 0, 3));
        if (\count($names) > 3) {
            $preview .= '…';
        }

        return '📎 ' . \count($names) . ' anexos: ' . $preview;
    }

    /** @return array<string, mixed> */
    private function formatChatMessage(int $seq, string $role, string $text, array $ev, array $ticket): array
    {
        $prefix = $role === 'ti' ? 'Resposta da TI: ' : 'Resposta do solicitante: ';
        $body = str_starts_with($text, $prefix) ? substr($text, \strlen($prefix)) : $text;
        $actor = (string) ($ev['actor'] ?? '');

        return [
            'seq' => $seq,
            'role' => $role,
            'body' => $body,
            'at' => (string) ($ev['at'] ?? ''),
            'actor' => $actor,
            'display_name' => $role === 'ti'
                ? ($actor !== '' ? $actor : 'Equipe de TI')
                : (string) ($ticket['requester'] ?? 'Solicitante'),
        ];
    }

    public function escalatePriority(Empresa $empresa, string $codigo, User $actor): array
    {
        $chamado = $this->requireEntity($empresa, $codigo);
        $order = ['P4', 'P3', 'P2', 'P1'];
        $current = $chamado->getPrioridade();
        $idx = array_search($current, $order, true);
        if ($idx === false || $idx === 0) {
            throw new \InvalidArgumentException('Prioridade já está no nível máximo.');
        }

        $newPriority = $order[$idx - 1];
        $chamado->setPrioridade($newPriority);
        $chamado->addTimelineEvent('Prioridade escalada para ' . $newPriority, $this->actorName($actor));
        $chamado->touch();
        $this->em->flush();

        return $this->mapTicket($chamado);
    }

    public function applyHeliaSuggestion(Empresa $empresa, string $codigo, User $actor): array
    {
        $chamado = $this->requireEntity($empresa, $codigo);
        if ($chamado->getStatus() === TiChamado::STATUS_RESOLVIDO) {
            throw new \InvalidArgumentException('Chamado já resolvido.');
        }

        $analysis = $this->helia->analyzeInput([
            'title' => $chamado->getTitulo(),
            'summary' => $chamado->getResumo(),
            'category' => $chamado->getCategoria(),
        ], $empresa);

        $oldCat = $chamado->getCategoria();
        $oldPri = $chamado->getPrioridade();
        $chamado->setCategoria((string) $analysis['suggested_category']);
        $chamado->setPrioridade((string) $analysis['suggested_priority']);
        $chamado->setImpacto((string) $analysis['suggested_impact']);
        $chamado->setHeliaConfianca((int) $analysis['confidence']);
        $chamado->setHeliaAnalise((string) $analysis['summary']);
        $chamado->setHeliaKb(array_column($analysis['kb_articles'], 'id'));
        $chamado->setHeliaAplicado(true);

        $events = [
            AiAssistant::NAME . ' aplicou triagem · ' . $oldCat . '→' . $chamado->getCategoria()
            . ' · ' . $oldPri . '→' . $chamado->getPrioridade(),
        ];

        if ($chamado->getResponsavel() === null && (int) $analysis['confidence'] >= 85) {
            $technician = $this->suggestTechnician($empresa, (string) $analysis['suggested_category']);
            if ($technician !== null) {
                $chamado->setResponsavel($technician);
                $techName = $technician->getNome() ?: $technician->getEmail() ?: 'Técnico';
                $events[] = AiAssistant::NAME . ' atribuiu a ' . $techName;
            }
        }

        if ($chamado->getStatus() === TiChamado::STATUS_NOVO) {
            $chamado->setStatus(TiChamado::STATUS_EM_ANALISE);
            $events[] = 'Status alterado para Em análise';
        }

        foreach ($events as $event) {
            $chamado->addTimelineEvent($event, AiAssistant::NAME);
        }

        $playbook = $this->playbooks->matchForTicket($this->mapTicket($chamado), $empresa);
        if ($playbook !== null) {
            $chamado->addTimelineEvent('Playbook: ' . ($playbook['title'] ?? ''), 'Cortex');
            foreach ($playbook['steps'] ?? [] as $i => $step) {
                $chamado->addTimelineEvent('Passo ' . ($i + 1) . ': ' . $step, 'Playbook');
            }
        }

        $chamado->touch();
        $this->em->flush();

        return $this->mapTicket($chamado);
    }

    public function markHeliaReviewed(Empresa $empresa, string $codigo, User $actor): array
    {
        $chamado = $this->requireEntity($empresa, $codigo);
        if ($chamado->getHeliaConfianca() === null) {
            throw new \InvalidArgumentException('Chamado sem triagem ' . AiAssistant::NAME . '.');
        }

        $chamado->setHeliaRevisado(true);
        $chamado->addTimelineEvent('Triagem ' . AiAssistant::NAME . ' revisada', $this->actorName($actor));
        $chamado->touch();
        $this->em->flush();

        return $this->mapTicket($chamado);
    }

    /** @return list<array<string, mixed>> */
    public function slaAlerts(Empresa $empresa, int $limit = 8): array
    {
        $alerts = [];
        foreach ($this->repository->findByEmpresa($empresa) as $chamado) {
            if ($chamado->getStatus() === TiChamado::STATUS_RESOLVIDO) {
                continue;
            }

            $ticket = $this->mapTicket($chamado);
            $id = $chamado->getCodigo();
            $sla = (int) ($ticket['sla_pct'] ?? 100);

            if ($sla <= 25) {
                $alerts[] = $this->alertItem(
                    $id,
                    'sla_critical',
                    'critical',
                    'fa-triangle-exclamation',
                    'SLA crítico',
                    $sla . '% restante',
                    $sla,
                );
                if ($sla < 20) {
                    $recipient = $chamado->getResponsavel() ?? $chamado->getSolicitante();
                    $this->notifications->notify(
                        $empresa,
                        $recipient,
                        'sla_critico',
                        'SLA crítico: ' . $chamado->getCodigo(),
                        'O chamado ' . $chamado->getCodigo() . ' tem apenas ' . $sla . '% de SLA restante.',
                        '/ti/chamados/' . $id,
                    );
                }
            } elseif ($sla <= 50) {
                $alerts[] = $this->alertItem(
                    $id,
                    'sla_warn',
                    'warn',
                    'fa-clock',
                    'SLA em risco',
                    $sla . '% restante',
                    $sla,
                );
            }

            if ($chamado->getResponsavel() === null && \in_array($chamado->getPrioridade(), ['P1', 'P2'], true)) {
                $alerts[] = $this->alertItem(
                    $id,
                    'unassigned',
                    'warn',
                    'fa-user-slash',
                    'Sem responsável',
                    null,
                    null,
                    $chamado->getPrioridade(),
                );
            }

            if (
                !$chamado->isHeliaRevisado()
                && $chamado->getHeliaConfianca() !== null
                && $chamado->getHeliaConfianca() < 80
            ) {
                $confidence = (int) $chamado->getHeliaConfianca();
                $alerts[] = $this->alertItem(
                    $id,
                    'helia_review',
                    'info',
                    'fa-brain',
                    'Revisar ' . AiAssistant::NAME,
                    $confidence . '% confiança',
                    null,
                    null,
                    $confidence,
                );
            }
        }

        usort($alerts, static function (array $a, array $b): int {
            $rank = ['critical' => 0, 'warn' => 1, 'info' => 2, 'neutral' => 3];

            return ($rank[$a['tone']] ?? 9) <=> ($rank[$b['tone']] ?? 9);
        });

        return \array_slice($alerts, 0, $limit);
    }

    /** @return list<array<string, mixed>> */
    public function heliaSuggestions(Empresa $empresa, int $limit = 8): array
    {
        $items = [];
        foreach ($this->repository->findByEmpresa($empresa) as $chamado) {
            if ($chamado->getStatus() === TiChamado::STATUS_RESOLVIDO) {
                continue;
            }
            if ($chamado->isHeliaAplicado() || $chamado->getHeliaConfianca() === null) {
                continue;
            }

            $analysis = $this->helia->analyzeInput([
                'title' => $chamado->getTitulo(),
                'summary' => $chamado->getResumo(),
            ]);

            $items[] = [
                'ticket_id' => $chamado->getCodigo(),
                'title' => $chamado->getTitulo(),
                'suggestion' => $chamado->getHeliaAnalise() ?? $analysis['summary'],
                'confidence' => $chamado->getHeliaConfianca() ?? $analysis['confidence'],
                'suggested_category' => $analysis['suggested_category'],
                'suggested_priority' => $analysis['suggested_priority'],
                'auto_applied' => false,
            ];

            if (\count($items) >= $limit) {
                break;
            }
        }

        return $items;
    }

    /** @return list<User> */
    public function technicians(Empresa $empresa): array
    {
        return $this->userRepository->findActiveByEmpresa($empresa);
    }

    public function updateStatus(Empresa $empresa, string $codigo, string $status, User $user): array
    {
        $chamado = $this->repository->findByCodigo($empresa, $codigo);
        if ($chamado === null) {
            throw new \InvalidArgumentException('Chamado não encontrado.');
        }

        $valid = [
            TiChamado::STATUS_NOVO,
            TiChamado::STATUS_EM_ANALISE,
            TiChamado::STATUS_EM_EXECUCAO,
            TiChamado::STATUS_AGUARDANDO,
            TiChamado::STATUS_RESOLVIDO,
        ];
        if (!\in_array($status, $valid, true)) {
            throw new \InvalidArgumentException('Status inválido.');
        }

        if ($chamado->getStatus() === $status) {
            return $this->mapTicket($chamado);
        }

        $labels = TiReferenceData::statusLabels();
        $actor = $user->getNome() ?: $user->getEmail() ?: 'Usuário';
        $chamado->setStatus($status);

        if ($status === TiChamado::STATUS_RESOLVIDO) {
            $chamado->setResolvidoEm(new \DateTimeImmutable());
            if (!$chamado->isHeliaRevisado()) {
                $chamado->setHeliaRevisado(true);
            }
        } else {
            $chamado->setResolvidoEm(null);
        }

        $chamado->addTimelineEvent(
            'Status alterado para ' . ($labels[$status] ?? $status),
            $actor,
        );
        $chamado->touch();
        $this->em->flush();

        if ($status === TiChamado::STATUS_RESOLVIDO) {
            $this->notifySolicitante(
                $chamado,
                'Chamado resolvido',
                sprintf('%s foi marcado como resolvido. Avalie o atendimento quando puder.', $chamado->getCodigo()),
            );
        }

        return $this->mapTicket($chamado);
    }

    /**
     * @param array<string, mixed> $data
     * @param list<UploadedFile>   $files
     */
    public function create(Empresa $empresa, User $user, array $data, array $files = []): array
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('Título é obrigatório.');
        }

        $priority = (string) ($data['priority'] ?? 'P3');
        if (!\in_array($priority, TiReferenceData::priorities(), true)) {
            $priority = 'P3';
        }

        $category = (string) ($data['category'] ?? 'sistema');
        $summary = trim((string) ($data['summary'] ?? ''));
        if ($summary === '') {
            throw new \InvalidArgumentException('Descrição detalhada é obrigatória.');
        }

        $impact = (string) ($data['impact'] ?? 'medio');
        $location = (string) ($data['location'] ?? 'matriz');
        $assetTag = trim((string) ($data['asset_tag'] ?? ''));
        $catalogItem = trim((string) ($data['catalog_item'] ?? ''));
        $affectedUsers = max(1, (int) ($data['affected_users'] ?? 1));
        $contactChannel = (string) ($data['contact_channel'] ?? 'portal');
        $contactPhone = trim((string) ($data['contact_phone'] ?? ''));
        $notifyManager = ($data['notify_manager'] ?? '') === '1';
        $preferredTime = trim((string) ($data['preferred_time'] ?? ''));

        $tags = [$this->categoryLabel($category)];
        if ($catalogItem !== '') {
            $tags[] = 'Catálogo';
        }
        if ($impact === 'critico' || $impact === 'alto') {
            $tags[] = 'Impacto alto';
        }

        $analysis = $this->helia->analyzeInput(['title' => $title, 'summary' => $summary, 'category' => $category], $empresa);
        $actor = $user->getNome() ?: $user->getEmail() ?: 'Usuário';
        $now = new \DateTimeImmutable();

        $chamado = (new TiChamado())
            ->setEmpresa($empresa)
            ->setSolicitante($user)
            ->setCodigo(sprintf('TK-%04d', $this->repository->nextCodigoNumber($empresa)))
            ->setTitulo($title)
            ->setResumo($summary)
            ->setCategoria($category)
            ->setPrioridade($priority)
            ->setStatus(TiChamado::STATUS_NOVO)
            ->setImpacto($impact)
            ->setLocal($location)
            ->setAssetTag($assetTag !== '' ? $assetTag : null)
            ->setCatalogItem($catalogItem !== '' ? $catalogItem : null)
            ->setUsuariosAfetados($affectedUsers)
            ->setCanalContato($contactChannel)
            ->setTelefoneContato($contactPhone !== '' ? $contactPhone : null)
            ->setNotificarGestor($notifyManager)
            ->setHorarioPreferido($preferredTime !== '' ? $preferredTime : null)
            ->setSlaPct(100)
            ->setHeliaConfianca($analysis['confidence'])
            ->setHeliaAnalise($analysis['summary'])
            ->setHeliaKb(array_column($analysis['kb_articles'], 'id'))
            ->setTags($tags)
            ->setAbertoEm($now)
            ->setTimeline([
                ['at' => $now->format('d/m H:i'), 'event' => 'Chamado aberto', 'actor' => $actor],
                ['at' => $now->format('d/m H:i'), 'event' => 'Triagem Cortex agendada', 'actor' => AiAssistant::NAME],
                ['at' => $now->format('d/m H:i'), 'event' => AiAssistant::NAME . ' classificou · ' . $analysis['confidence'] . '% confiança', 'actor' => 'Cortex'],
            ]);

        if ($assetTag !== '') {
            $ativo = $this->ativoRepository->findByCodigoForEmpresa($empresa, $assetTag);
            if ($ativo !== null) {
                $chamado->setAtivo($ativo);
            }
        }

        if ($catalogItem !== '') {
            foreach (TiReferenceData::catalog() as $catItem) {
                if (($catItem['id'] ?? '') === $catalogItem) {
                    $chamado->setCategoria((string) ($catItem['category'] ?? $category));
                    $chamado->setPrioridade((string) ($catItem['priority'] ?? $priority));
                    break;
                }
            }
        }

        $this->em->persist($chamado);
        $this->em->flush();

        if ($files !== []) {
            $uploaded = $this->attachments->uploadForChamado($chamado, $files, $user);
            if ($uploaded !== []) {
                $chamado->addTimelineEvent(
                    \count($uploaded) . ' anexo(s) adicionado(s)',
                    $actor,
                );
                $this->em->flush();
            }
        }

        $this->notifyOperatorsOnCreate($chamado, $user);

        return $this->mapTicket($chamado);
    }

    public function countOpen(Empresa $empresa): int
    {
        return $this->repository->countOpen($empresa);
    }

    /** @return array<string, int> */
    public function stats(Empresa $empresa): array
    {
        return [
            'total' => \count($this->repository->findByEmpresa($empresa)),
            'novo' => $this->repository->countByStatus($empresa, TiChamado::STATUS_NOVO),
            'analise' => $this->repository->countByStatus($empresa, TiChamado::STATUS_EM_ANALISE),
            'execucao' => $this->repository->countByStatus($empresa, TiChamado::STATUS_EM_EXECUCAO),
            'aguardando' => $this->repository->countByStatus($empresa, TiChamado::STATUS_AGUARDANDO),
            'resolvido' => $this->repository->countByStatus($empresa, TiChamado::STATUS_RESOLVIDO),
        ];
    }

    public function slaCompliance(Empresa $empresa, int $days = 7): int
    {
        $since = (new \DateTimeImmutable())->modify('-' . $days . ' days');
        $resolved = $this->repository->findResolvedSince($empresa, $since);
        if ($resolved === []) {
            return 100;
        }

        $within = 0;
        foreach ($resolved as $chamado) {
            if ($chamado->getResolvidoEm() === null) {
                continue;
            }
            $hours = TiReferenceData::resolutionHours($chamado->getPrioridade());
            $elapsed = ($chamado->getResolvidoEm()->getTimestamp() - $chamado->getAbertoEm()->getTimestamp()) / 3600;
            if ($elapsed <= $hours) {
                ++$within;
            }
        }

        return (int) round($within / \count($resolved) * 100);
    }

    public function mttrHours(Empresa $empresa, int $days = 30): float
    {
        $since = (new \DateTimeImmutable())->modify('-' . $days . ' days');
        $resolved = $this->repository->findResolvedSince($empresa, $since);
        if ($resolved === []) {
            return 0.0;
        }

        $total = 0.0;
        $count = 0;
        foreach ($resolved as $chamado) {
            if ($chamado->getResolvidoEm() === null) {
                continue;
            }
            $total += ($chamado->getResolvidoEm()->getTimestamp() - $chamado->getAbertoEm()->getTimestamp()) / 3600;
            ++$count;
        }

        return $count > 0 ? round($total / $count, 1) : 0.0;
    }

    public function cortexAutoRate(Empresa $empresa): int
    {
        $total = \count($this->repository->findByEmpresa($empresa));
        if ($total === 0) {
            return 0;
        }

        return (int) round($this->repository->countWithHeliaTriagem($empresa) / $total * 100);
    }

    /** @return list<array<string, mixed>> */
    public function liveOpsFeed(Empresa $empresa, int $limit = 6): array
    {
        $feed = [];
        foreach (\array_slice($this->repository->findByEmpresa($empresa), 0, $limit) as $chamado) {
            $ticket = $this->mapTicket($chamado);
            $tone = match (true) {
                $chamado->getPrioridade() === 'P1' && $chamado->getStatus() !== TiChamado::STATUS_RESOLVIDO => 'critical',
                $ticket['sla_pct'] < 50 => 'warn',
                $chamado->getStatus() === TiChamado::STATUS_RESOLVIDO => 'ok',
                default => 'neutral',
            };
            $icon = match ($chamado->getStatus()) {
                TiChamado::STATUS_RESOLVIDO => 'fa-check',
                TiChamado::STATUS_NOVO => 'fa-inbox',
                default => 'fa-headset',
            };
            $feed[] = [
                'icon' => $icon,
                'text' => $chamado->getCodigo() . ' · ' . $chamado->getTitulo(),
                'tone' => $tone,
            ];
        }

        return $feed;
    }

    /** @return list<array<string, mixed>> */
    public function cortexQueue(Empresa $empresa, int $limit = 10): array
    {
        $queue = [];
        foreach ($this->repository->findByEmpresa($empresa) as $chamado) {
            if ($chamado->getHeliaConfianca() === null) {
                continue;
            }
            if ($chamado->getStatus() === TiChamado::STATUS_RESOLVIDO) {
                continue;
            }

        if ($chamado->isHeliaRevisado() && $chamado->isHeliaAplicado()) {
                continue;
            }

            $queue[] = [
                'ticket_id' => $chamado->getCodigo(),
                'title' => $chamado->getCodigo() . ' — ' . $chamado->getTitulo(),
                'result' => $chamado->getHeliaAnalise() ?? 'Triagem ' . AiAssistant::NAME . ' concluída',
                'confidence' => $chamado->getHeliaConfianca(),
                'status' => $chamado->isHeliaRevisado() && $chamado->isHeliaAplicado() ? 'done' : 'review',
                'applied' => $chamado->isHeliaAplicado(),
                'reviewed' => $chamado->isHeliaRevisado(),
            ];

            if (\count($queue) >= $limit) {
                break;
            }
        }

        return $queue;
    }

    /** @return list<array<string, mixed>> */
    public function analyticsVolume(Empresa $empresa): array
    {
        return $this->repository->volumeByMonth($empresa);
    }

    /** Workload por técnico - tickets open per assignee */
    public function workloadByTechnician(Empresa $empresa): array
    {
        $workload = [];
        foreach ($this->repository->findByEmpresa($empresa) as $chamado) {
            if ($chamado->getStatus() === TiChamado::STATUS_RESOLVIDO) {
                continue;
            }
            $r = $chamado->getResponsavel();
            $name = $r ? ($r->getNome() ?: $r->getEmail() ?: 'Técnico') : 'Sem responsável';
            $workload[$name] = ($workload[$name] ?? 0) + 1;
        }
        arsort($workload);

        return array_map(fn ($name, $count) => ['name' => $name, 'count' => $count], array_keys($workload), $workload);
    }

    /** MTTR por categoria (últimos 30 dias) */
    public function mttrByCategory(Empresa $empresa): array
    {
        $byCategory = [];
        $since = (new \DateTimeImmutable())->modify('-30 days');
        foreach ($this->repository->findResolvedSince($empresa, $since) as $c) {
            if (!$c->getResolvidoEm()) {
                continue;
            }
            $cat = $c->getCategoria();
            $hours = ($c->getResolvidoEm()->getTimestamp() - $c->getAbertoEm()->getTimestamp()) / 3600;
            $byCategory[$cat][] = $hours;
        }

        return array_map(fn ($cat, $times) => [
            'category' => $cat,
            'avg_hours' => round(array_sum($times) / count($times), 1),
            'count' => count($times),
        ], array_keys($byCategory), $byCategory);
    }

    /** SLA heatmap por hora do dia */
    public function slaHeatmapByHour(Empresa $empresa): array
    {
        $heatmap = array_fill(0, 24, ['hour' => 0, 'count' => 0, 'sla_breach' => 0]);
        foreach (range(0, 23) as $h) {
            $heatmap[$h]['hour'] = $h;
        }
        foreach ($this->repository->findByEmpresa($empresa) as $c) {
            $hour = (int) $c->getAbertoEm()->format('G');
            $heatmap[$hour]['count']++;
            if ($c->getSlaPct() < 50) {
                $heatmap[$hour]['sla_breach']++;
            }
        }

        return array_values($heatmap);
    }

    /** P1 trend - P1 tickets per week for last 8 weeks */
    public function p1Trend(Empresa $empresa): array
    {
        $weeks = [];
        for ($i = 7; $i >= 0; $i--) {
            $start = (new \DateTimeImmutable())->modify("-{$i} weeks")->modify('Monday this week');
            $weeks[] = ['week' => $start->format('d/m'), 'p1' => 0, '_start' => $start];
        }
        foreach ($this->repository->findByEmpresa($empresa) as $c) {
            if ($c->getPrioridade() !== 'P1') {
                continue;
            }
            foreach ($weeks as &$week) {
                $weekEnd = $week['_start']->modify('+7 days');
                if ($c->getAbertoEm() >= $week['_start'] && $c->getAbertoEm() < $weekEnd) {
                    $week['p1']++;
                }
            }
            unset($week);
        }

        return array_map(fn ($w) => ['week' => $w['week'], 'p1' => $w['p1']], $weeks);
    }

    public function csatMetrics(Empresa $empresa): array
    {
        $tickets = $this->repository->findByEmpresa($empresa);
        $scores = array_filter(array_map(fn (\App\Entity\TiChamado $c) => $c->getCsatScore(), $tickets));
        $avg = \count($scores) > 0 ? round(array_sum($scores) / \count($scores), 1) : null;
        $dist = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        foreach ($scores as $s) {
            $dist[$s] = ($dist[$s] ?? 0) + 1;
        }

        return ['avg' => $avg, 'count' => \count($scores), 'dist' => $dist];
    }

    /** @return list<array<string, mixed>> */
    public function slaHeatmap(Empresa $empresa): array
    {
        $days = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex'];
        $rows = [];
        foreach (TiReferenceData::priorities() as $p) {
            $cells = [];
            foreach ($days as $d) {
                $cells[] = ['day' => $d, 'value' => $this->slaCompliance($empresa)];
            }
            $rows[] = ['priority' => $p, 'cells' => $cells];
        }

        return $rows;
    }

    private function mapTicket(TiChamado $chamado): array
    {
        $data = $chamado->toArray();
        $data['channel'] = $this->contactChannelLabel($chamado->getCanalContato());
        $data['sla_pct'] = $this->computeSlaPct($chamado);

        return $data;
    }

    private function computeSlaPct(TiChamado $chamado): int
    {
        if ($chamado->getStatus() === TiChamado::STATUS_RESOLVIDO) {
            return 100;
        }

        $limitHours = TiReferenceData::resolutionHours($chamado->getPrioridade());
        $now = new \DateTimeImmutable();
        $elapsed = $now->getTimestamp() - $chamado->getAbertoEm()->getTimestamp();
        if ($chamado->getSlaPausadoEm() !== null) {
            $elapsed -= ($now->getTimestamp() - $chamado->getSlaPausadoEm()->getTimestamp());
        }
        $elapsed -= $chamado->getSlaPausadoAcumuladoSeg();
        $elapsedHours = max(0, $elapsed) / 3600;

        return max(0, (int) round(100 - ($elapsedHours / $limitHours * 100)));
    }

    private function categoryLabel(string $id): string
    {
        foreach (TiReferenceData::categories() as $cat) {
            if ($cat['id'] === $id) {
                return $cat['label'];
            }
        }

        return 'Geral';
    }

    private function contactChannelLabel(string $id): string
    {
        foreach (TiReferenceData::contactChannels() as $ch) {
            if ($ch['id'] === $id) {
                return $ch['label'];
            }
        }

        return 'Portal';
    }

    private function requireEntity(Empresa $empresa, string $codigo): TiChamado
    {
        $chamado = $this->repository->findByCodigo($empresa, $codigo);
        if ($chamado === null) {
            throw new \InvalidArgumentException('Chamado não encontrado.');
        }

        return $chamado;
    }

    private function applyReopen(TiChamado $chamado, string $actorName): void
    {
        $labels = TiReferenceData::statusLabels();
        $chamado->setStatus(TiChamado::STATUS_EM_ANALISE);
        $chamado->setResolvidoEm(null);
        $chamado->addTimelineEvent('Chamado reaberto', $actorName);
        $chamado->addTimelineEvent(
            'Status alterado para ' . ($labels[TiChamado::STATUS_EM_ANALISE] ?? 'Em análise'),
            $actorName,
        );
    }

    private function actorName(User $user): string
    {
        return $user->getNome() ?: $user->getEmail() ?: 'Usuário';
    }

    private function notifyOperatorsOnCreate(TiChamado $chamado, User $creator): void
    {
        $empresa = $chamado->getEmpresa();
        $link = '/ti/chamados/' . $chamado->getCodigo();
        $titulo = 'Novo chamado ' . $chamado->getCodigo();
        $mensagem = sprintf(
            '%s abriu: %s',
            $this->actorName($creator),
            $chamado->getTitulo(),
        );

        foreach ($this->operatorsForEmpresa($empresa, $creator) as $operator) {
            $this->notifications->notify($empresa, $operator, 'chamado_novo', $titulo, $mensagem, $link);
        }
    }

    private function notifyOperatorsOnReply(TiChamado $chamado, User $author): void
    {
        $empresa = $chamado->getEmpresa();
        $link = '/ti/chamados/' . $chamado->getCodigo();
        $titulo = 'Resposta em ' . $chamado->getCodigo();
        $mensagem = sprintf('%s respondeu no chamado.', $this->actorName($author));

        $recipients = $this->operatorsForEmpresa($empresa, $author);
        $assignee = $chamado->getResponsavel();
        if ($assignee !== null && $assignee->getId() !== $author->getId()) {
            $recipients = [$assignee];
        }

        foreach ($recipients as $operator) {
            $this->notifications->notify($empresa, $operator, 'chamado_resposta', $titulo, $mensagem, $link);
        }
    }

    private function notifySolicitante(TiChamado $chamado, string $titulo, string $mensagem): void
    {
        $this->notifications->notify(
            $chamado->getEmpresa(),
            $chamado->getSolicitante(),
            'chamado_atualizado',
            $titulo,
            $mensagem,
            '/ti/chamados/' . $chamado->getCodigo(),
        );
    }

    /** @return list<User> */
    private function operatorsForEmpresa(Empresa $empresa, ?User $exclude = null): array
    {
        $operators = [];
        foreach ($this->userRepository->findActiveByEmpresa($empresa) as $user) {
            if ($exclude !== null && $user->getId() === $exclude->getId()) {
                continue;
            }
            if ($this->grants->grantAtLeast($user, TiGrantPolicy::SCOPE, 'chamados', TiGrantPolicy::OPERATE_CHAMADOS)) {
                $operators[] = $user;
            }
        }

        return $operators;
    }

    private function suggestTechnician(Empresa $empresa, string $category): ?User
    {
        $candidates = $this->userRepository->findActiveByEmpresa($empresa);
        if ($candidates === []) {
            return null;
        }

        usort($candidates, function (User $a, User $b) use ($empresa): int {
            return $this->repository->countOpenByResponsavel($empresa, $a)
                <=> $this->repository->countOpenByResponsavel($empresa, $b);
        });

        return $candidates[0];
    }

    /** @return list<array<string, mixed>> */
    public function myTickets(Empresa $empresa, User $user): array
    {
        return array_map(
            fn (TiChamado $c) => $this->mapTicket($c),
            $this->repository->findBySolicitante($empresa, $user),
        );
    }

    /** @return list<array<string, mixed>> */
    public function ticketsForAsset(Empresa $empresa, int $ativoId): array
    {
        return array_map(
            fn (TiChamado $c) => $this->mapTicket($c),
            $this->repository->findByAtivo($empresa, $ativoId),
        );
    }

    /** @return list<array{id: int, name: string, score: int, open: int}> */
    public function suggestTechniciansRanked(Empresa $empresa, string $category): array
    {
        $ranked = [];
        foreach ($this->technicians($empresa) as $tech) {
            $open = $this->repository->countOpenByResponsavel($empresa, $tech);
            $score = max(10, 100 - ($open * 12));
            $ranked[] = [
                'id' => $tech->getId(),
                'name' => $tech->getNome() ?: $tech->getEmail() ?: 'Técnico',
                'score' => $score,
                'open' => $open,
            ];
        }
        usort($ranked, static fn ($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));

        return $ranked;
    }

    public function toggleSlaPause(Empresa $empresa, string $codigo, User $actor, ?string $motivo = null): array
    {
        $chamado = $this->requireEntity($empresa, $codigo);
        if ($chamado->getStatus() === TiChamado::STATUS_RESOLVIDO) {
            throw new \InvalidArgumentException('Chamado resolvido.');
        }

        $now = new \DateTimeImmutable();
        if ($chamado->getSlaPausadoEm() !== null) {
            $pausedSec = $now->getTimestamp() - $chamado->getSlaPausadoEm()->getTimestamp();
            $chamado->setSlaPausadoAcumuladoSeg($chamado->getSlaPausadoAcumuladoSeg() + $pausedSec);
            $chamado->setSlaPausadoEm(null);
            $chamado->setSlaPausadoMotivo(null);
            $chamado->addTimelineEvent('SLA retomado', $this->actorName($actor));
        } else {
            $chamado->setSlaPausadoEm($now);
            $chamado->setSlaPausadoMotivo($motivo ?: 'Aguardando solicitante');
            $chamado->addTimelineEvent('SLA pausado · ' . ($motivo ?: 'Aguardando solicitante'), $this->actorName($actor));
        }
        $chamado->touch();
        $this->em->flush();

        return $this->mapTicket($chamado);
    }

    public function heliaFeedback(Empresa $empresa, string $codigo, User $actor, string $feedback): array
    {
        if (!\in_array($feedback, ['correct', 'incorrect'], true)) {
            throw new \InvalidArgumentException('Feedback inválido.');
        }
        $chamado = $this->requireEntity($empresa, $codigo);
        $chamado->setHeliaFeedback($feedback);
        $chamado->setHeliaFeedbackEm(new \DateTimeImmutable());
        $chamado->addTimelineEvent(
            $feedback === 'correct' ? AiAssistant::NAME . ': sugestão confirmada' : AiAssistant::NAME . ': sugestão rejeitada',
            $this->actorName($actor),
        );
        $chamado->touch();
        $this->em->flush();

        return $this->mapTicket($chamado);
    }

    public function linkProblema(Empresa $empresa, string $codigo, User $actor, ?int $problemaId): array
    {
        $chamado = $this->requireEntity($empresa, $codigo);
        $problema = null;
        if ($problemaId !== null && $problemaId > 0) {
            $problema = $this->problemaRepository->find($problemaId);
            if (!$problema || $problema->getEmpresa()->getId() !== $empresa->getId()) {
                throw new \InvalidArgumentException('Problema não encontrado.');
            }
        }
        $chamado->setProblema($problema);
        $label = $problema ? $problema->getCodigo() . ' · ' . $problema->getTitulo() : '—';
        $chamado->addTimelineEvent('Problema vinculado: ' . $label, $this->actorName($actor));
        $chamado->touch();
        $this->em->flush();

        return $this->mapTicket($chamado);
    }

    /** @param array<string, mixed> $data */
    public function createFromWebhook(Empresa $empresa, User $user, array $data): array
    {
        $data['title'] = $data['title'] ?? $data['titulo'] ?? 'Chamado via webhook';
        $data['summary'] = $data['summary'] ?? $data['descricao'] ?? 'Aberto por integração externa.';

        $ticket = $this->create($empresa, $user, $data);

        if (($ticket['priority'] ?? '') === 'P1') {
            $technicians = $this->technicians($empresa);
            foreach ($technicians as $tech) {
                $this->notifications->notify(
                    $empresa,
                    $tech,
                    'p1_criado',
                    'Chamado P1 aberto via webhook',
                    $ticket['title'] ?? 'Chamado crítico aberto automaticamente.',
                    '/ti/chamados/' . ($ticket['id'] ?? ''),
                );
            }
        }

        return $ticket;
    }

    /**
     * Creates an automatic (system-generated) chamado with custom tags and integConectorId.
     *
     * @param array<string, mixed> $data
     * @param list<string>         $tags
     */
    public function createAutomatic(Empresa $empresa, User $user, array $data, array $tags = [], ?string $integConectorId = null): \App\Entity\TiChamado
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('Título é obrigatório.');
        }

        $priority = (string) ($data['priority'] ?? 'P3');
        $category = (string) ($data['category'] ?? 'integracao');
        $summary = trim((string) ($data['summary'] ?? 'Aberto automaticamente.'));
        $impact = (string) ($data['impact'] ?? 'medio');

        $analysis = $this->helia->analyzeInput(['title' => $title, 'summary' => $summary, 'category' => $category], $empresa);
        $now = new \DateTimeImmutable();
        $actor = 'Sistema';

        $chamado = (new \App\Entity\TiChamado())
            ->setEmpresa($empresa)
            ->setSolicitante($user)
            ->setCodigo(sprintf('TK-%04d', $this->repository->nextCodigoNumber($empresa)))
            ->setTitulo($title)
            ->setResumo($summary)
            ->setCategoria($category)
            ->setPrioridade($priority)
            ->setStatus(\App\Entity\TiChamado::STATUS_NOVO)
            ->setImpacto($impact)
            ->setLocal('sistema')
            ->setUsuariosAfetados(1)
            ->setCanalContato('automatico')
            ->setNotificarGestor(false)
            ->setSlaPct(100)
            ->setHeliaConfianca($analysis['confidence'])
            ->setHeliaAnalise($analysis['summary'])
            ->setHeliaKb(array_column($analysis['kb_articles'], 'id'))
            ->setTags($tags)
            ->setAbertoEm($now)
            ->setTimeline([
                ['at' => $now->format('d/m H:i'), 'event' => 'Chamado criado automaticamente por degradação de integração', 'actor' => $actor],
            ]);

        if ($integConectorId !== null) {
            $chamado->setIntegConectorId($integConectorId);
        }

        $this->em->persist($chamado);
        $this->em->flush();

        if ($priority === 'P1') {
            foreach ($this->technicians($empresa) as $tech) {
                $this->notifications->notify(
                    $empresa,
                    $tech,
                    'incidente_auto',
                    'Incidente automático P1: ' . $title,
                    $summary,
                    '/ti/chamados/' . $chamado->getCodigo(),
                );
            }
        }

        return $chamado;
    }

    /** @return array<string, mixed> */
    private function alertItem(
        string $ticketId,
        string $kind,
        string $tone,
        string $icon,
        string $label,
        ?string $detail = null,
        ?int $slaPct = null,
        ?string $priority = null,
        ?int $heliaConfidence = null,
    ): array {
        $text = $ticketId . ' · ' . $label;
        if ($detail !== null && $detail !== '') {
            $text .= ' · ' . $detail;
        }

        return [
            'ticket_id' => $ticketId,
            'kind' => $kind,
            'label' => $label,
            'detail' => $detail,
            'sla_pct' => $slaPct,
            'priority' => $priority,
            'helia_confidence' => $heliaConfidence,
            'text' => $text,
            'tone' => $tone,
            'icon' => $icon,
        ];
    }
}
