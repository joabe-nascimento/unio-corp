<?php

namespace App\Service\Rh;

use App\Entity\Empresa;
use App\Entity\Funcionario;
use App\Entity\RhComunicado;
use App\Entity\RhComunicadoLeitura;
use App\Entity\RhEmailEvent;
use App\Entity\User;
use App\Exception\RhProcessException;
use App\Repository\RhComunicadoLeituraRepository;
use App\Repository\RhComunicadoRepository;
use Doctrine\ORM\EntityManagerInterface;

class RhComunicacaoService
{
    public function __construct(
        private EntityManagerInterface $em,
        private RhComunicadoRepository $comunicadoRepo,
        private RhComunicadoLeituraRepository $leituraRepo,
        private RhAuditService $audit,
    ) {}

    /** @return list<RhComunicado> */
    public function listForEmpresa(Empresa $empresa): array
    {
        return $this->comunicadoRepo->findAtivosForEmpresa($empresa);
    }

    public function create(Empresa $empresa, string $titulo, string $corpo, ?User $autor): RhComunicado
    {
        $titulo = trim($titulo);
        $corpo = trim($corpo);
        if ($titulo === '' || $corpo === '') {
            throw new RhProcessException('Informe título e conteúdo do comunicado.');
        }

        $com = new RhComunicado();
        $com->setEmpresa($empresa);
        $com->setAutor($autor);
        $com->setTitulo($titulo);
        $com->setCorpo($corpo);
        $com->setAtivo(true);

        $this->em->persist($com);
        $this->em->flush();

        $this->audit->log($empresa, $autor, 'comunicacao', 'criar_comunicado', 'rh_comunicado', $com->getId());

        return $com;
    }

    public function markRead(RhComunicado $comunicado, Funcionario $funcionario): void
    {
        if ($this->leituraRepo->findOneByComunicadoAndFuncionario($comunicado, $funcionario)) {
            return;
        }

        $leitura = new RhComunicadoLeitura();
        $leitura->setComunicado($comunicado);
        $leitura->setFuncionario($funcionario);
        $this->em->persist($leitura);
        $this->em->flush();
    }

    public function queueEmail(
        Empresa $empresa,
        string $destinatario,
        string $assunto,
        string $template,
        ?array $payload = null,
    ): RhEmailEvent {
        $event = new RhEmailEvent();
        $event->setEmpresa($empresa);
        $event->setDestinatario(trim($destinatario));
        $event->setAssunto(trim($assunto));
        $event->setTemplate($template);
        $event->setStatus(RhEmailEvent::STATUS_PENDENTE);
        $event->setPayload($payload);

        $this->em->persist($event);
        $this->em->flush();

        return $event;
    }
}
