<?php

namespace App\Service\PosOperatorio\Whatsapp;

use App\Entity\ClinicAgendamento;
use App\Entity\ClinicOutboundMessage;
use App\Entity\Empresa;
use App\Repository\ClinicAgendamentoRepository;
use App\Service\PosOperatorio\ClinicAgendaService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Processa respostas inbound Meta (CONFIRMO/SIM) e confirma agenda.
 */
final class ClinicWhatsappInboundConfirmService
{
    private const CONFIRM_WORDS = ['confirmo', 'confirmado', 'sim', 'ok', '1', 'confirmar'];

    public function __construct(
        private ClinicWhatsappService $whatsapp,
        private ClinicAgendamentoRepository $agendamentos,
        private ClinicAgendaService $agenda,
        private WhatsappMetaTenantResolver $tenantResolver,
        private EntityManagerInterface $em,
    ) {}

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{confirmed: int, ignored: int}
     */
    public function handleMetaPayload(array $payload): array
    {
        $confirmed = 0;
        $ignored = 0;

        $entries = $payload['entry'] ?? [];
        if (!\is_array($entries)) {
            return ['confirmed' => 0, 'ignored' => 0];
        }

        foreach ($entries as $entry) {
            if (!\is_array($entry)) {
                continue;
            }
            $changes = $entry['changes'] ?? [];
            if (!\is_array($changes)) {
                continue;
            }
            foreach ($changes as $change) {
                if (!\is_array($change)) {
                    continue;
                }
                $value = $change['value'] ?? [];
                if (!\is_array($value)) {
                    continue;
                }
                $phoneNumberId = (string) ($value['metadata']['phone_number_id'] ?? '');
                $empresa = $this->tenantResolver->resolve($phoneNumberId);
                $messages = $value['messages'] ?? [];
                if (!\is_array($messages)) {
                    continue;
                }
                foreach ($messages as $message) {
                    if (!\is_array($message)) {
                        continue;
                    }
                    if (($message['type'] ?? '') !== 'text') {
                        ++$ignored;
                        continue;
                    }
                    $from = (string) ($message['from'] ?? '');
                    $body = mb_strtolower(trim((string) ($message['text']['body'] ?? '')));
                    $body = preg_replace('/[!?.]+$/u', '', $body) ?? $body;
                    if (!$this->isConfirmText($body)) {
                        ++$ignored;
                        $this->logInbound($empresa, $from, $body, 'ignored');
                        continue;
                    }
                    if ($empresa === null) {
                        ++$ignored;
                        continue;
                    }
                    $agendamento = $this->findPendingByPhone($empresa, $from);
                    if ($agendamento === null) {
                        ++$ignored;
                        $this->logInbound($empresa, $from, $body, 'no_match');
                        continue;
                    }
                    try {
                        $this->agenda->changeStatus(
                            $agendamento,
                            $empresa,
                            ClinicAgendamento::STATUS_CONFIRMADO,
                        );
                        ++$confirmed;
                        $this->logInbound($empresa, $from, $body, 'confirmed', $agendamento->getId());
                    } catch (\Throwable) {
                        ++$ignored;
                        $this->logInbound($empresa, $from, $body, 'error', $agendamento->getId());
                    }
                }
            }
        }

        return ['confirmed' => $confirmed, 'ignored' => $ignored];
    }

    private function isConfirmText(string $body): bool
    {
        return \in_array($body, self::CONFIRM_WORDS, true);
    }

    private function findPendingByPhone(Empresa $empresa, string $fromE164): ?ClinicAgendamento
    {
        $normalized = $this->whatsapp->normalizeE164($fromE164);
        if ($normalized === null) {
            return null;
        }

        $today = new \DateTimeImmutable('today');
        $fim = $today->modify('+2 days');
        $candidates = $this->agendamentos->findMarcadosForPhoneConfirm($empresa, $today, $fim);

        foreach ($candidates as $agendamento) {
            $phone = $this->whatsapp->normalizeE164($agendamento->getPaciente()->getTelefoneContato());
            if ($phone !== null && $phone === $normalized) {
                return $agendamento;
            }
        }

        return null;
    }

    private function logInbound(
        ?Empresa $empresa,
        string $from,
        string $body,
        string $status,
        ?int $agendamentoId = null,
    ): void {
        if ($empresa === null) {
            return;
        }

        $message = (new ClinicOutboundMessage())
            ->setEmpresa($empresa)
            ->setCanal(ClinicOutboundMessage::CANAL_WHATSAPP)
            ->setEvento('agenda_confirmacao_inbound')
            ->setDestino($from)
            ->setStatus($status)
            ->setProvider('meta_inbound')
            ->setProviderMessageId($agendamentoId !== null ? 'agendamento:'.$agendamentoId : null)
            ->setCorpoPreview(mb_substr($body, 0, 240));

        $this->em->persist($message);
        $this->em->flush();
    }
}
