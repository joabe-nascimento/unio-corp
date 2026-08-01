<?php

namespace App\Service\Juridico;

use App\Entity\Empresa;
use App\Entity\JuridicoAtendimentoMensagem;
use App\Entity\JuridicoAtendimentoTicket;
use App\Entity\JuridicoCliente;
use App\Entity\JuridicoProcesso;
use App\Entity\User;
use App\Exception\JuridicoProcessException;
use App\Repository\JuridicoAtendimentoTicketRepository;
use App\Repository\JuridicoClienteRepository;
use App\Repository\JuridicoPrazoRepository;
use App\Repository\JuridicoProcessoRepository;
use Doctrine\ORM\EntityManagerInterface;

final class JuridicoAtendimentoService
{
    public function __construct(
        private EntityManagerInterface $em,
        private JuridicoAtendimentoTicketRepository $ticketRepo,
        private JuridicoClienteRepository $clienteRepo,
        private JuridicoProcessoRepository $processoRepo,
        private JuridicoPrazoRepository $prazoRepo,
        private JuridicoAtendimentoWhatsappService $whatsapp,
    ) {
    }

    public function loadForEmpresa(Empresa $empresa, int $id): JuridicoAtendimentoTicket
    {
        $ticket = $this->ticketRepo->findOneByEmpresa($empresa, $id);
        if (!$ticket) {
            throw new JuridicoProcessException('Ticket não encontrado.');
        }

        return $ticket;
    }

    /** @return list<JuridicoAtendimentoTicket> */
    public function findForEmpresa(Empresa $empresa, ?string $status = null, ?string $canal = null, ?string $q = null): array
    {
        return $this->ticketRepo->findForEmpresa($empresa, $status, $canal, $q);
    }

    /** @return array{abertos: int, sla_estourado: int, sla_medio: string|null} */
    public function metricas(Empresa $empresa): array
    {
        $slaMedio = $this->ticketRepo->slaMedioMinutos($empresa);
        $slaLabel = null;
        if ($slaMedio !== null) {
            if ($slaMedio < 60) {
                $slaLabel = sprintf('%.0f min', $slaMedio);
            } else {
                $slaLabel = sprintf('%.1f h', $slaMedio / 60);
            }
        }

        return [
            'abertos' => $this->ticketRepo->countAbertos($empresa),
            'sla_estourado' => $this->ticketRepo->countSlaEstourado($empresa),
            'sla_medio' => $slaLabel,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function criarTicket(Empresa $empresa, array $data, ?User $autor = null): JuridicoAtendimentoTicket
    {
        $assunto = trim((string) ($data['assunto'] ?? ''));
        $corpo = trim((string) ($data['mensagem'] ?? ''));
        if ($assunto === '') {
            throw new JuridicoProcessException('Informe o assunto do ticket.');
        }
        if ($corpo === '') {
            throw new JuridicoProcessException('Informe a mensagem inicial.');
        }

        $canal = (string) ($data['canal'] ?? JuridicoAtendimentoTicket::CANAL_INTERNO);
        if (!\in_array($canal, JuridicoAtendimentoTicket::CANAIS, true)) {
            $canal = JuridicoAtendimentoTicket::CANAL_INTERNO;
        }

        $cliente = $this->resolverCliente($empresa, $data);
        $processo = $this->resolverProcesso($empresa, $data, $cliente);

        $ticket = (new JuridicoAtendimentoTicket())
            ->setEmpresa($empresa)
            ->setAssunto($assunto)
            ->setCanal($canal)
            ->setCliente($cliente)
            ->setProcesso($processo);

        $ticket->aplicarSlaPorCliente($cliente);

        if ($autor !== null) {
            $ticket->setResponsavel($autor);
        }

        $mensagem = (new JuridicoAtendimentoMensagem())
            ->setDirecao(JuridicoAtendimentoMensagem::DIRECAO_ENTRADA)
            ->setCanal($canal)
            ->setCorpo($corpo)
            ->setRemetenteNome($cliente?->getNome());

        $ticket->addMensagem($mensagem);

        $this->em->persist($ticket);
        $this->em->flush();

        return $ticket;
    }

    public function enviarResposta(
        JuridicoAtendimentoTicket $ticket,
        string $corpo,
        ?User $autor,
        bool $enviarWhatsapp = false,
        bool $notaInterna = false,
    ): JuridicoAtendimentoMensagem {
        $corpo = trim($corpo);
        if ($corpo === '') {
            throw new JuridicoProcessException('A mensagem não pode estar vazia.');
        }

        if ($ticket->getStatus() === JuridicoAtendimentoTicket::STATUS_RESOLVIDO && !$notaInterna) {
            throw new JuridicoProcessException('Ticket já resolvido. Reabra para responder ao cliente.');
        }

        $direcao = $notaInterna
            ? JuridicoAtendimentoMensagem::DIRECAO_INTERNO
            : JuridicoAtendimentoMensagem::DIRECAO_SAIDA;

        $mensagem = (new JuridicoAtendimentoMensagem())
            ->setDirecao($direcao)
            ->setCanal($ticket->getCanal())
            ->setCorpo($corpo)
            ->setAutor($autor);

        $ticket->addMensagem($mensagem);

        if (!$notaInterna) {
            if ($ticket->getPrimeiraRespostaEm() === null) {
                $ticket->setPrimeiraRespostaEm(new \DateTimeImmutable());
            }
            if ($ticket->getStatus() === JuridicoAtendimentoTicket::STATUS_ABERTO) {
                $ticket->setStatus(JuridicoAtendimentoTicket::STATUS_EM_ATENDIMENTO);
            }

            if ($enviarWhatsapp && $ticket->getCanal() === JuridicoAtendimentoTicket::CANAL_WHATSAPP) {
                $this->whatsapp->enviarParaCliente($ticket, $mensagem);
            }
        }

        $ticket->touch();
        $this->em->persist($mensagem);
        $this->em->flush();

        return $mensagem;
    }

    public function atualizarStatus(JuridicoAtendimentoTicket $ticket, string $status): void
    {
        if (!\in_array($status, JuridicoAtendimentoTicket::STATUSES, true)) {
            throw new JuridicoProcessException('Status inválido.');
        }

        $ticket->setStatus($status);
        if ($status === JuridicoAtendimentoTicket::STATUS_RESOLVIDO) {
            $ticket->setResolvidoEm(new \DateTimeImmutable());
        } else {
            $ticket->setResolvidoEm(null);
        }
        $ticket->touch();
        $this->em->flush();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function vincular(JuridicoAtendimentoTicket $ticket, array $data): void
    {
        $cliente = $this->resolverCliente($ticket->getEmpresa(), $data);
        $processo = $this->resolverProcesso($ticket->getEmpresa(), $data, $cliente);

        if ($cliente !== null) {
            $ticket->setCliente($cliente);
            $ticket->aplicarSlaPorCliente($cliente);
        }
        if ($processo !== null) {
            $ticket->setProcesso($processo);
            if ($processo->getCliente() !== null) {
                $ticket->setCliente($processo->getCliente());
                $ticket->aplicarSlaPorCliente($processo->getCliente());
            }
        }

        $ticket->touch();
        $this->em->flush();
    }

    /**
     * @return array{processo: ?JuridicoProcesso, prazos: list<\App\Entity\JuridicoPrazo>}
     */
    public function contextoCaso(JuridicoAtendimentoTicket $ticket): array
    {
        $processo = $ticket->getProcesso();
        $prazos = [];

        if ($processo !== null) {
            $prazos = array_values(array_filter(
                $this->prazoRepo->findForEmpresa($ticket->getEmpresa(), 'pendentes'),
                static fn ($p) => $p->getProcesso()?->getId() === $processo->getId(),
            ));
        }

        return ['processo' => $processo, 'prazos' => $prazos];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function resolverCliente(Empresa $empresa, array $data): ?JuridicoCliente
    {
        $clienteId = (int) ($data['cliente_id'] ?? 0);
        if ($clienteId < 1) {
            return null;
        }

        return $this->clienteRepo->findOneBy(['id' => $clienteId, 'empresa' => $empresa]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function resolverProcesso(Empresa $empresa, array $data, ?JuridicoCliente $cliente): ?JuridicoProcesso
    {
        $processoId = (int) ($data['processo_id'] ?? 0);
        if ($processoId < 1) {
            return null;
        }

        $processo = $this->processoRepo->findOneByEmpresa($empresa, $processoId);
        if ($processo !== null && $cliente !== null && $processo->getCliente()?->getId() !== $cliente->getId()) {
            throw new JuridicoProcessException('O processo selecionado não pertence ao cliente informado.');
        }

        return $processo;
    }
}
