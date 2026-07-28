<?php

declare(strict_types=1);

namespace App\Service\Juridico;

use App\Entity\Empresa;
use App\Entity\JuridicoPrazo;
use App\Entity\User;
use App\Repository\EmpresaRepository;
use App\Repository\JuridicoPrazoRepository;
use App\Repository\UserRepository;
use App\Service\Organismo\OrganismoCopyService;
use App\Service\PlatformNotificationService;

/**
 * O "Agente Autônomo 24/7" de verdade: roda fora do chat (via cron/systemd timer),
 * varre a carteira de todos os escritórios e cria notificações reais quando algo
 * pede atenção — prazo vencendo, tarefa atrasada, processo crítico. Sem depender de
 * ninguém perguntar nada, e sem depender do provedor de IA externo estar disponível
 * (tudo aqui é determinístico, direto no banco).
 *
 * Cada alerta é deduplicado via {@see AgenteAutonomoStatusStore} para não notificar a
 * mesma coisa várias vezes por dia a cada execução do cron.
 */
final class AgenteAutonomoJuridicoService
{
    /** Tarefa/prazo vencendo dentro desses dias já entra em alerta preventivo. */
    private const DIAS_ALERTA_PREVENTIVO = 3;

    public function __construct(
        private OrganismoCopyService $organismoCopy,
        private EmpresaRepository $empresaRepo,
        private JuridicoPrazoRepository $prazoRepo,
        private JuridicoRiscoAlertaService $riscoAlerta,
        private UserRepository $userRepo,
        private PlatformNotificationService $notifications,
        private AgenteAutonomoStatusStore $statusStore,
    ) {
    }

    /**
     * @return array{
     *     executado: bool,
     *     empresas_processadas: int,
     *     alertas_gerados: int,
     *     detalhes: list<array{empresa: string, alertas: int}>
     * }
     */
    public function executar(?int $empresaId = null): array
    {
        if (!$this->organismoCopy->isJuridicoProfile()) {
            return ['executado' => false, 'empresas_processadas' => 0, 'alertas_gerados' => 0, 'detalhes' => []];
        }

        $empresas = $empresaId !== null
            ? array_filter([$this->empresaRepo->find($empresaId)])
            : $this->empresaRepo->findBy(['ativo' => true]);

        $estado = $this->statusStore->load();
        $detalhes = [];
        $totalAlertas = 0;

        foreach ($empresas as $empresa) {
            if (!$empresa instanceof Empresa) {
                continue;
            }

            $novasChaves = $this->processarEmpresa($empresa, $estado);
            $estado = $this->statusStore->registrarExecucao($estado, (int) $empresa->getId(), (string) $empresa->getNome(), $novasChaves);

            $detalhes[] = ['empresa' => (string) $empresa->getNome(), 'alertas' => \count($novasChaves)];
            $totalAlertas += \count($novasChaves);
        }

        $this->statusStore->persist($estado);

        return [
            'executado' => true,
            'empresas_processadas' => \count($detalhes),
            'alertas_gerados' => $totalAlertas,
            'detalhes' => $detalhes,
        ];
    }

    /**
     * @param array<string, mixed> $estado
     *
     * @return list<string> chaves de dedup notificadas nesta execução
     */
    private function processarEmpresa(Empresa $empresa, array $estado): array
    {
        $novasChaves = [];

        foreach ($this->prazoRepo->findForEmpresa($empresa, 'pendentes') as $prazo) {
            $chave = $this->avaliarPrazo($empresa, $prazo, $estado);
            if ($chave !== null) {
                $novasChaves[] = $chave;
            }
        }

        foreach ($this->riscoAlerta->gerarAlertas($empresa) as $alerta) {
            if ($alerta['nivel'] !== 'alto') {
                continue; // ruído baixo/médio fica para quando o usuário pedir (analisar_carteira)
            }

            $processo = $alerta['processo'];
            $chave = sprintf('processo:%d:%s', $processo->getId(), $alerta['tipo']);
            if ($this->statusStore->jaNotificado($estado, $chave) || \in_array($chave, $novasChaves, true)) {
                continue;
            }

            $destinatarios = $this->destinatarios($empresa, $processo->getResponsavel());
            $this->notifications->notifyMany(
                $empresa,
                $destinatarios,
                'juridico_agente',
                $alerta['tipo'],
                sprintf('Atenção: %s', $processo->getNumero()),
                $alerta['mensagem'],
                'app_juridico_processo_show',
                ['id' => $processo->getId()],
                $alerta['icone'],
                'danger',
            );
            $novasChaves[] = $chave;
        }

        return $novasChaves;
    }

    /**
     * @param array<string, mixed> $estado
     */
    private function avaliarPrazo(Empresa $empresa, JuridicoPrazo $prazo, array $estado): ?string
    {
        $dias = $prazo->getDiasRestantes();
        if ($dias > self::DIAS_ALERTA_PREVENTIVO) {
            return null;
        }

        $chave = sprintf('prazo:%d', $prazo->getId());
        if ($this->statusStore->jaNotificado($estado, $chave)) {
            return null;
        }

        $atrasado = $dias < 0;
        $mensagem = $atrasado
            ? sprintf('"%s" venceu há %d dia(s) e ainda não foi marcado como cumprido.', $prazo->getTipo(), abs($dias))
            : ($dias === 0
                ? sprintf('"%s" vence hoje.', $prazo->getTipo())
                : sprintf('"%s" vence em %d dia(s) (%s).', $prazo->getTipo(), $dias, $prazo->getDataLimite()->format('d/m/Y')));

        $destinatarios = $this->destinatarios($empresa, $prazo->getResponsavel() ?? $prazo->getProcesso()?->getResponsavel());

        $this->notifications->notifyMany(
            $empresa,
            $destinatarios,
            'juridico_agente',
            $atrasado ? 'prazo_atrasado' : 'prazo_critico',
            $atrasado ? 'Prazo vencido' : 'Prazo crítico',
            $mensagem,
            'app_juridico_prazos',
            [],
            $atrasado ? 'fa-hourglass-end' : 'fa-clock',
            $atrasado ? 'danger' : 'warning',
        );

        return $chave;
    }

    /** @return list<User> */
    private function destinatarios(Empresa $empresa, ?User $responsavel): array
    {
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
