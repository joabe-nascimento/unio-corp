<?php

namespace App\Service\Juridico;

use App\Entity\JuridicoProcesso;
use App\Entity\JuridicoProcessoEvento;
use App\Repository\JuridicoDocumentoRepository;
use App\Repository\JuridicoPrazoRepository;
use App\Repository\JuridicoProcessoEventoRepository;
use App\Repository\JuridicoPublicacaoRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Linha do tempo unificada do processo: eventos persistidos + agregação das fontes já existentes.
 */
final class JuridicoProcessoTimelineService
{
    public function __construct(
        private EntityManagerInterface $em,
        private JuridicoProcessoEventoRepository $eventoRepo,
        private JuridicoPublicacaoRepository $publicacaoRepo,
        private JuridicoPrazoRepository $prazoRepo,
        private JuridicoDocumentoRepository $documentoRepo,
    ) {
    }

    /**
     * @return list<array{tipo: string, titulo: string, resumo: ?string, ocorreu_em: \DateTimeImmutable, icone: string, tom: string, visivel_portal: bool}>
     */
    public function montar(JuridicoProcesso $processo, int $limit = 60, bool $soPortal = false): array
    {
        $itens = [];

        foreach ($this->eventoRepo->findForProcesso($processo, $limit, $soPortal) as $evento) {
            $itens[] = $this->fromPersistido($evento);
        }

        foreach ($this->publicacaoRepo->findBy(['processo' => $processo], ['criadoEm' => 'DESC'], 30) as $pub) {
            $itens[] = [
                'tipo' => JuridicoProcessoEvento::TIPO_PUBLICACAO,
                'titulo' => $pub->tituloCurto(),
                'resumo' => $pub->getIaResumo() ?: mb_substr((string) $pub->getTexto(), 0, 180),
                'ocorreu_em' => $pub->getDataDisponibilizacao() ?? $pub->getCriadoEm(),
                'icone' => 'fa-newspaper',
                'tom' => $pub->getPrioridade() === 'critica' ? 'danger' : 'info',
                'visivel_portal' => true,
            ];
        }

        foreach ($this->prazoRepo->findBy(['processo' => $processo], ['dataLimite' => 'DESC'], 30) as $prazo) {
            $itens[] = [
                'tipo' => JuridicoProcessoEvento::TIPO_PRAZO,
                'titulo' => $prazo->getTipo(),
                'resumo' => $prazo->getDescricao(),
                'ocorreu_em' => $prazo->getDataLimite(),
                'icone' => 'fa-hourglass-half',
                'tom' => $prazo->getStatusLabel() === 'critico' ? 'danger' : 'warning',
                'visivel_portal' => true,
            ];
        }

        foreach ($this->documentoRepo->findBy(['processo' => $processo], ['criadoEm' => 'DESC'], 20) as $doc) {
            if ($soPortal && !$doc->isVisivelPortal()) {
                continue;
            }
            $itens[] = [
                'tipo' => JuridicoProcessoEvento::TIPO_DOCUMENTO,
                'titulo' => $doc->getNome(),
                'resumo' => $doc->isPrecedente() ? 'Precedente interno do escritório' : ucfirst($doc->getCategoria()),
                'ocorreu_em' => $doc->getCriadoEm(),
                'icone' => 'fa-file-lines',
                'tom' => 'neutral',
                'visivel_portal' => $doc->isVisivelPortal(),
            ];
        }

        usort($itens, static fn (array $a, array $b) => $b['ocorreu_em'] <=> $a['ocorreu_em']);

        return \array_slice($itens, 0, $limit);
    }

    public function registrar(
        JuridicoProcesso $processo,
        string $tipo,
        string $titulo,
        ?string $resumo = null,
        ?string $referenciaTipo = null,
        ?int $referenciaId = null,
        bool $visivelPortal = true,
    ): JuridicoProcessoEvento {
        $evento = (new JuridicoProcessoEvento())
            ->setEmpresa($processo->getEmpresa())
            ->setProcesso($processo)
            ->setTipo($tipo)
            ->setTitulo($titulo)
            ->setResumo($resumo)
            ->setReferenciaTipo($referenciaTipo)
            ->setReferenciaId($referenciaId)
            ->setVisivelPortal($visivelPortal);

        $this->em->persist($evento);
        $this->em->flush();

        return $evento;
    }

    /**
     * @return array{tipo: string, titulo: string, resumo: ?string, ocorreu_em: \DateTimeImmutable, icone: string, tom: string, visivel_portal: bool}
     */
    private function fromPersistido(JuridicoProcessoEvento $evento): array
    {
        $icone = match ($evento->getTipo()) {
            JuridicoProcessoEvento::TIPO_PUBLICACAO => 'fa-newspaper',
            JuridicoProcessoEvento::TIPO_PRAZO => 'fa-hourglass-half',
            JuridicoProcessoEvento::TIPO_TAREFA => 'fa-list-check',
            JuridicoProcessoEvento::TIPO_DOCUMENTO => 'fa-file-lines',
            JuridicoProcessoEvento::TIPO_AUDIENCIA => 'fa-gavel',
            JuridicoProcessoEvento::TIPO_HONORARIO => 'fa-coins',
            JuridicoProcessoEvento::TIPO_MENSAGEM => 'fa-comments',
            default => 'fa-clock',
        };

        return [
            'tipo' => $evento->getTipo(),
            'titulo' => $evento->getTitulo(),
            'resumo' => $evento->getResumo(),
            'ocorreu_em' => $evento->getOcorreuEm(),
            'icone' => $icone,
            'tom' => 'info',
            'visivel_portal' => $evento->isVisivelPortal(),
        ];
    }
}
