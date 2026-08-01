<?php

namespace App\Service\Juridico;

use App\Entity\Empresa;
use App\Entity\JuridicoPublicacao;
use App\Entity\User;
use App\Repository\JuridicoPublicacaoConfigRepository;
use App\Repository\UserRepository;
use App\Service\PlatformNotificationService;
use App\Service\PosOperatorio\Whatsapp\ClinicWhatsappService;
use Psr\Log\LoggerInterface;

/**
 * Alertas de novas publicações: notificação na plataforma + WhatsApp Meta (se configurado).
 */
final class JuridicoPublicacaoAlertaService
{
    public function __construct(
        private PlatformNotificationService $notifications,
        private UserRepository $userRepo,
        private ClinicWhatsappService $whatsapp,
        private JuridicoPublicacaoConfigRepository $configRepo,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param list<JuridicoPublicacao> $novas
     */
    public function notificarNovas(Empresa $empresa, array $novas): void
    {
        if ($novas === []) {
            return;
        }

        $config = $this->configRepo->getOrCreate($empresa);
        $qtd = \count($novas);
        $criticas = \count(array_filter(
            $novas,
            static fn (JuridicoPublicacao $p) => \in_array($p->getPrioridade(), [
                JuridicoPublicacao::PRIORIDADE_ALTA,
                JuridicoPublicacao::PRIORIDADE_CRITICA,
            ], true),
        ));

        $titulo = $qtd === 1 ? 'Nova publicação DJEN' : sprintf('%d novas publicações DJEN', $qtd);
        $primeira = $novas[0];
        $mensagem = sprintf(
            '%s%s — %s. Abra a fila de Publicações para triagem.',
            $primeira->getTipoComunicacao() ?? 'Publicação',
            $primeira->getNumeroProcesso() ? ' · ' . $primeira->getNumeroProcesso() : '',
            $criticas > 0 ? sprintf('%d com prioridade alta/crítica', $criticas) : 'aguardando triagem',
        );

        $this->notifications->notifyMany(
            $empresa,
            $this->destinatarios($empresa, $primeira),
            'juridico_publicacoes',
            'nova_publicacao',
            $titulo,
            $mensagem,
            'app_juridico_publicacoes',
            [],
            'fa-newspaper',
            $criticas > 0 ? 'warning' : 'info',
        );

        if (!$config->isAlertaWhatsapp()) {
            return;
        }

        $telefone = $config->getTelefoneAlerta();
        if ($telefone === null || trim($telefone) === '') {
            return;
        }

        if (!$this->whatsapp->isLive()) {
            $this->logger->info('WhatsApp alerta de publicação pulado — Meta Cloud não configurado');

            return;
        }

        $texto = sprintf(
            "Unio Jurídico — %s\n%s\nAcesse Publicações & Intimações para triar.",
            $titulo,
            $mensagem,
        );

        try {
            $this->whatsapp->send($empresa, $telefone, $texto, [
                'event' => 'juridico_publicacao_alerta',
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('Falha ao enviar WhatsApp de publicação: {msg}', ['msg' => $e->getMessage()]);
        }
    }

    /** @return list<User> */
    private function destinatarios(Empresa $empresa, JuridicoPublicacao $publicacao): array
    {
        $responsavel = $publicacao->getProcesso()?->getResponsavel();
        if ($responsavel !== null) {
            return [$responsavel];
        }

        $usuarios = $this->userRepo->findBy(['empresa' => $empresa]);
        $gestores = array_values(array_filter(
            $usuarios,
            static fn (User $u) => $u->isGestor() || $u->isPlatformOwner() || $u->isTenant(),
        ));

        return $gestores !== [] ? $gestores : \array_slice($usuarios, 0, 5);
    }
}
