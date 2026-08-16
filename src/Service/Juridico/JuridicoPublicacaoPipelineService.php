<?php

namespace App\Service\Juridico;

use App\Entity\JuridicoProcessoEvento;
use App\Entity\JuridicoPublicacao;
use App\Entity\JuridicoPublicacaoEvento;
use App\Exception\JuridicoProcessException;
use App\Repository\JuridicoPublicacaoEventoRepository;
use App\Repository\JuridicoPublicacaoRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Orquestra triagem → match → prazo → alerta com auditoria e idempotência.
 */
final class JuridicoPublicacaoPipelineService
{
    public function __construct(
        private EntityManagerInterface $em,
        private JuridicoPublicacaoService $publicacoes,
        private JuridicoPublicacaoEventoRepository $eventoRepo,
        private JuridicoPublicacaoRepository $publicacaoRepo,
        private JuridicoPublicacaoAlertaService $alertas,
        private JuridicoProcessoTimelineService $timeline,
        private JuridicoWebhookDispatcher $webhooks,
    ) {
    }

    public function registrar(JuridicoPublicacao $publicacao, string $tipo, array $payload = []): void
    {
        $evento = (new JuridicoPublicacaoEvento())
            ->setPublicacao($publicacao)
            ->setTipo($tipo)
            ->setPayload($payload);
        $this->em->persist($evento);
        $this->em->flush();
    }

    public function executar(JuridicoPublicacao $publicacao): void
    {
        try {
            if ($publicacao->getIaResumo() === null) {
                $this->publicacoes->triarComIa($publicacao);
                $this->registrar($publicacao, JuridicoPublicacaoEvento::TIPO_TRIAGEM, [
                    'classificacao' => $publicacao->getIaClassificacao(),
                ]);
            } else {
                $this->publicacoes->tentarCriarPrazoAutomatico($publicacao);
            }

            if ($publicacao->getProcesso() !== null) {
                $this->registrar($publicacao, JuridicoPublicacaoEvento::TIPO_MATCH, [
                    'processo' => $publicacao->getProcesso()->getNumero(),
                ]);
            }

            $criou = $this->publicacoes->tentarCriarPrazoAutomatico($publicacao);
            if ($criou) {
                $publicacao->setPrazoGeradoEm(new \DateTimeImmutable());
                $publicacao->setPipelineStatus('concluido');
                $this->em->flush();
                $this->registrar($publicacao, JuridicoPublicacaoEvento::TIPO_PRAZO, [
                    'dias' => $publicacao->getIaSugestaoPrazoDias(),
                ]);
                if ($publicacao->getProcesso() !== null) {
                    $this->timeline->registrar(
                        $publicacao->getProcesso(),
                        JuridicoProcessoEvento::TIPO_PRAZO,
                        'Prazo aberto pela publicação',
                        $publicacao->getIaSugestaoTipoPrazo() ?: $publicacao->tituloCurto(),
                        'publicacao',
                        $publicacao->getId(),
                    );
                }
                $this->webhooks->dispatch($publicacao->getEmpresa(), 'prazo.criado', [
                    'publicacao_id' => $publicacao->getId(),
                    'processo' => $publicacao->getProcesso()?->getNumero(),
                ]);
            } else {
                $publicacao->setPipelineStatus($publicacao->getProcesso() ? 'aguardando_prazo' : 'aguardando_vinculo');
                $this->em->flush();
            }

            $this->alertas->notificarNovas($publicacao->getEmpresa(), [$publicacao]);
            $this->registrar($publicacao, JuridicoPublicacaoEvento::TIPO_ALERTA);
            $this->webhooks->dispatch($publicacao->getEmpresa(), 'publicacao.nova', [
                'id' => $publicacao->getId(),
                'numero' => $publicacao->getNumeroProcesso(),
            ]);
        } catch (\Throwable $e) {
            $publicacao->setPipelineStatus('erro');
            $this->em->flush();
            $this->registrar($publicacao, JuridicoPublicacaoEvento::TIPO_ERRO, ['msg' => $e->getMessage()]);
            throw new JuridicoProcessException($e->getMessage());
        }
    }

    /** @return list<JuridicoPublicacao> */
    public function falhas(?JuridicoPublicacao $ignore = null): array
    {
        $empresa = $ignore?->getEmpresa();
        if ($empresa === null) {
            return [];
        }

        return $this->publicacaoRepo->findPipelineErros($empresa);
    }

    /** @return list<JuridicoPublicacaoEvento> */
    public function historico(JuridicoPublicacao $publicacao): array
    {
        return $this->eventoRepo->findForPublicacao($publicacao);
    }

    public function reprocessarPendentes(\App\Entity\Empresa $empresa, int $limit = 20): int
    {
        $pubs = $this->publicacaoRepo->findForEmpresa($empresa, JuridicoPublicacao::STATUS_NAO_LIDA);
        $n = 0;
        foreach (\array_slice($pubs, 0, $limit) as $pub) {
            try {
                $this->executar($pub);
                ++$n;
            } catch (\Throwable) {
                continue;
            }
        }

        return $n;
    }
}
