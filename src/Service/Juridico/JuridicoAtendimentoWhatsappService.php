<?php

namespace App\Service\Juridico;

use App\Entity\Empresa;
use App\Entity\JuridicoAtendimentoMensagem;
use App\Entity\JuridicoAtendimentoTicket;
use App\Service\PosOperatorio\Whatsapp\ClinicWhatsappService;
use Psr\Log\LoggerInterface;

final class JuridicoAtendimentoWhatsappService
{
    public function __construct(
        private ClinicWhatsappService $whatsapp,
        private LoggerInterface $logger,
    ) {
    }

    public function isDisponivel(): bool
    {
        return $this->whatsapp->isLive();
    }

    public function enviarParaCliente(JuridicoAtendimentoTicket $ticket, JuridicoAtendimentoMensagem $mensagem): void
    {
        $cliente = $ticket->getCliente();
        $telefone = $cliente?->getTelefone();

        if ($telefone === null || trim($telefone) === '') {
            $mensagem->setWhatsappEnviado(false)->setWhatsappStatus('sem_telefone');

            return;
        }

        $result = $this->whatsapp->send(
            $ticket->getEmpresa(),
            $telefone,
            $mensagem->getCorpo(),
            ['event' => 'juridico_atendimento', 'ticket_id' => $ticket->getId()],
        );

        $mensagem->setWhatsappEnviado($result->sent);
        $mensagem->setWhatsappStatus($result->status);

        if (!$result->sent) {
            $this->logger->warning('WhatsApp atendimento jurídico falhou', [
                'ticket_id' => $ticket->getId(),
                'erro' => $result->error,
            ]);
        }
    }
}
