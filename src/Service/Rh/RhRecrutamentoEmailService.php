<?php

namespace App\Service\Rh;

use App\Entity\Empresa;
use App\Entity\RhCandidato;
use App\Entity\RhEmailEvent;
use App\Exception\RhProcessException;
use App\Repository\RhEmailEventRepository;
use App\Rh\RhCandidatoEtapa;
use App\Rh\RhEntrevistaTipo;
use App\Rh\RhRecrutamentoDisplay;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

final class RhRecrutamentoEmailService
{
    public const TEMPLATE_CANDIDATURA = 'recrutamento_candidatura_confirmacao';
    public const TEMPLATE_ETAPA = 'recrutamento_etapa_mudanca';
    public const TEMPLATE_ENTREVISTA = 'recrutamento_entrevista_agendada';

    public function __construct(
        private EntityManagerInterface $em,
        private RhComunicacaoService $comunicacao,
        private RhEmailEventRepository $emailRepo,
        private Environment $twig,
        private string $mailerFrom,
        private ?MailerInterface $mailer = null,
    ) {}

    public function queueCandidaturaConfirmacao(RhCandidato $candidato): RhEmailEvent
    {
        $vaga = $candidato->getVaga();
        $empresa = $vaga->getEmpresa();

        return $this->comunicacao->queueEmail(
            $empresa,
            $candidato->getEmail(),
            'Recebemos sua candidatura — ' . $vaga->getTitulo(),
            self::TEMPLATE_CANDIDATURA,
            $this->payloadForCandidato($candidato),
        );
    }

    public function queueEtapaMudanca(RhCandidato $candidato, string $de, string $para): ?RhEmailEvent
    {
        if ($de === $para) {
            return null;
        }

        $vaga = $candidato->getVaga();
        $empresa = $vaga->getEmpresa();

        return $this->comunicacao->queueEmail(
            $empresa,
            $candidato->getEmail(),
            'Atualização do processo seletivo — ' . RhCandidatoEtapa::label($para),
            self::TEMPLATE_ETAPA,
            array_merge($this->payloadForCandidato($candidato), [
                'etapa_de' => RhCandidatoEtapa::label($de),
                'etapa_para' => RhCandidatoEtapa::label($para),
            ]),
        );
    }

    public function queueEntrevistaAgendada(RhCandidato $candidato): RhEmailEvent
    {
        $vaga = $candidato->getVaga();
        $empresa = $vaga->getEmpresa();
        $when = $candidato->getEntrevistaEm();

        return $this->comunicacao->queueEmail(
            $empresa,
            $candidato->getEmail(),
            RhRecrutamentoDisplay::entrevistaTitulo($candidato),
            self::TEMPLATE_ENTREVISTA,
            array_merge($this->payloadForCandidato($candidato), [
                'entrevista_em' => $when?->format('d/m/Y H:i'),
                'entrevista_link' => $candidato->getEntrevistaLink(),
                'entrevista_tipo' => $candidato->getEntrevistaTipoLabel(),
                'entrevista_titulo' => RhRecrutamentoDisplay::entrevistaTitulo($candidato),
                'entrevistador_nome' => $candidato->getEntrevistaEntrevistador()?->getNome(),
                'calendar_url' => $this->buildGoogleCalendarUrl($candidato),
            ]),
        );
    }

    /** @return array{processados: int, enviados: int, erros: int} */
    public function processQueue(?Empresa $empresa = null, int $limit = 20): array
    {
        if ($this->mailer === null) {
            throw new RhProcessException('Serviço de e-mail não configurado (MAILER_DSN).');
        }

        $events = $this->emailRepo->findPending($empresa, $limit);
        $stats = ['processados' => 0, 'enviados' => 0, 'erros' => 0];

        foreach ($events as $event) {
            ++$stats['processados'];
            try {
                $this->dispatch($event);
                $event->setStatus(RhEmailEvent::STATUS_ENVIADO);
                $event->setEnviadoEm(new \DateTimeImmutable());
                ++$stats['enviados'];
            } catch (TransportExceptionInterface|\Throwable $e) {
                $event->setStatus(RhEmailEvent::STATUS_ERRO);
                $payload = $event->getPayload() ?? [];
                $payload['_erro'] = $e->getMessage();
                $event->setPayload($payload);
                ++$stats['erros'];
            }
        }

        $this->em->flush();

        return $stats;
    }

    private function dispatch(RhEmailEvent $event): void
    {
        $payload = $event->getPayload() ?? [];
        $template = 'emails/recrutamento/' . $event->getTemplate() . '.html.twig';
        if (!$this->twig->getLoader()->exists($template)) {
            $template = 'emails/recrutamento/generic.html.twig';
        }

        $html = $this->twig->render($template, [
            'assunto' => $event->getAssunto(),
            'payload' => $payload,
        ]);

        $email = (new Email())
            ->from($this->mailerFrom)
            ->to($event->getDestinatario())
            ->subject($event->getAssunto())
            ->html($html);

        $this->mailer->send($email);
    }

    /** @return array<string, mixed> */
    private function payloadForCandidato(RhCandidato $candidato): array
    {
        $vaga = $candidato->getVaga();
        $empresa = $vaga->getEmpresa();

        return [
            'candidato_nome' => RhRecrutamentoDisplay::formatNome($candidato->getNome()),
            'vaga_titulo' => $vaga->getTitulo(),
            'empresa_nome' => $empresa->getNome(),
        ];
    }

    private function buildGoogleCalendarUrl(RhCandidato $candidato): ?string
    {
        $when = $candidato->getEntrevistaEm();
        if ($when === null) {
            return null;
        }

        $end = $when->modify('+1 hour');
        $tipo = $candidato->getEntrevistaTipoLabel();
        $details = RhRecrutamentoDisplay::entrevistaTitulo($candidato) . "\n"
            . 'Candidato: ' . RhRecrutamentoDisplay::formatNome($candidato->getNome()) . "\n"
            . 'Modalidade: ' . $tipo;
        $entrevistador = $candidato->getEntrevistaEntrevistador();
        if ($entrevistador !== null) {
            $details .= "\nEntrevistador: " . $entrevistador->getNome();
        }
        $params = [
            'action' => 'TEMPLATE',
            'text' => RhRecrutamentoDisplay::entrevistaTitulo($candidato),
            'dates' => $when->format('Ymd\THis\Z') . '/' . $end->format('Ymd\THis\Z'),
            'details' => $details,
        ];
        if ($candidato->getEntrevistaLink()) {
            $params['location'] = $candidato->getEntrevistaLink();
        }

        return 'https://calendar.google.com/calendar/render?' . http_build_query($params);
    }
}
