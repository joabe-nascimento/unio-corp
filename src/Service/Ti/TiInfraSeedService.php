<?php

namespace App\Service\Ti;

use App\Entity\Empresa;
use App\Entity\TiAtivo;
use App\Entity\TiIntegracao;
use App\Entity\TiIntegracaoLog;
use App\Entity\TiLicenca;
use App\Entity\TiManutencao;
use App\Entity\User;
use App\Repository\TiAtivoRepository;
use Doctrine\ORM\EntityManagerInterface;

/** Popula dados iniciais de infra TI quando a empresa ainda não tem registros. */
final class TiInfraSeedService
{
    public function __construct(
        private EntityManagerInterface $em,
        private TiAtivoRepository $ativoRepo,
    ) {}

    public function seedIfEmpty(Empresa $empresa): bool
    {
        if ($this->ativoRepo->countByEmpresa($empresa) > 0) {
            return false;
        }

        foreach ($this->ativosSeed() as $row) {
            $ativo = (new TiAtivo())
                ->setEmpresa($empresa)
                ->setCodigo($row['codigo'])
                ->setTipo($row['tipo'])
                ->setModelo($row['modelo'])
                ->setResponsavel($row['responsavel'])
                ->setStatus($row['status'])
                ->setCicloPct($row['ciclo']);
            $this->em->persist($ativo);
        }

        foreach ($this->licencasSeed() as $row) {
            $lic = (new TiLicenca())
                ->setEmpresa($empresa)
                ->setNome($row['nome'])
                ->setSeats($row['seats'])
                ->setUsed($row['used'])
                ->setCustoMensal(number_format($row['custo'], 2, '.', ''))
                ->setRenovacaoEm(new \DateTimeImmutable($row['renovacao']));
            $this->em->persist($lic);
        }

        $integracoes = [];
        foreach ($this->integracoesSeed() as $row) {
            $int = (new TiIntegracao())
                ->setEmpresa($empresa)
                ->setNome($row['nome'])
                ->setStatus($row['status'])
                ->setLatencia($row['latencia'])
                ->setUptime(number_format($row['uptime'], 2, '.', ''))
                ->setEventos24h($row['events']);
            $this->em->persist($int);
            $integracoes[$row['nome']] = $int;
        }

        $this->em->flush();

        foreach ($this->logsSeed() as $row) {
            $log = (new TiIntegracaoLog())
                ->setEmpresa($empresa)
                ->setIntegracao($integracoes[$row['integracao']] ?? null)
                ->setConector($row['conector'])
                ->setNivel($row['nivel'])
                ->setMensagem($row['mensagem'])
                ->setRegistradoEm(new \DateTimeImmutable('today ' . $row['hora']));
            $this->em->persist($log);
        }

        foreach ($this->manutencoesSeed() as $row) {
            $man = (new TiManutencao())
                ->setEmpresa($empresa)
                ->setTitulo($row['titulo'])
                ->setJanela($row['janela'])
                ->setImpacto($row['impacto'])
                ->setStatus($row['status'])
                ->setOwner($row['owner']);
            $this->em->persist($man);
        }

        $this->em->flush();

        return true;
    }

    /** @return list<array<string, mixed>> */
    private function ativosSeed(): array
    {
        return [
            ['codigo' => 'NB-1187', 'tipo' => 'Notebook', 'modelo' => 'Dell Latitude 5540', 'responsavel' => 'Marina Costa', 'status' => TiAtivo::STATUS_MANUTENCAO, 'ciclo' => 72],
            ['codigo' => 'NB-1204', 'tipo' => 'Notebook', 'modelo' => 'Lenovo ThinkPad T14', 'responsavel' => 'João Pereira', 'status' => TiAtivo::STATUS_ATIVO, 'ciclo' => 18],
            ['codigo' => 'MON-0892', 'tipo' => 'Monitor', 'modelo' => 'Dell U2723QE', 'responsavel' => 'Design Team', 'status' => TiAtivo::STATUS_ATIVO, 'ciclo' => 45],
            ['codigo' => 'SRV-003', 'tipo' => 'Servidor', 'modelo' => 'HPE ProLiant DL380', 'responsavel' => 'Infra', 'status' => TiAtivo::STATUS_ATIVO, 'ciclo' => 85],
            ['codigo' => 'PH-0441', 'tipo' => 'Celular', 'modelo' => 'iPhone 14', 'responsavel' => 'Comercial SP', 'status' => TiAtivo::STATUS_ATIVO, 'ciclo' => 40],
            ['codigo' => 'NB-0998', 'tipo' => 'Notebook', 'modelo' => 'MacBook Pro 14"', 'responsavel' => null, 'status' => TiAtivo::STATUS_ESTOQUE, 'ciclo' => 5],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function licencasSeed(): array
    {
        return [
            ['nome' => 'Microsoft 365 E3', 'seats' => 220, 'used' => 214, 'custo' => 48.2, 'renovacao' => '2026-08-15'],
            ['nome' => 'Adobe Creative Cloud', 'seats' => 12, 'used' => 11, 'custo' => 4.1, 'renovacao' => '2026-06-04'],
            ['nome' => 'Slack Business+', 'seats' => 180, 'used' => 156, 'custo' => 12.8, 'renovacao' => '2026-11-01'],
            ['nome' => 'GitHub Enterprise', 'seats' => 45, 'used' => 38, 'custo' => 6.5, 'renovacao' => '2026-09-20'],
            ['nome' => 'Zoom Workplace', 'seats' => 100, 'used' => 67, 'custo' => 3.2, 'renovacao' => '2026-07-30'],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function integracoesSeed(): array
    {
        return [
            ['nome' => 'Núcleo RH → AD', 'status' => TiIntegracao::STATUS_HEALTHY, 'latencia' => '120ms', 'uptime' => 99.9, 'events' => 1240],
            ['nome' => 'eSocial webhook', 'status' => TiIntegracao::STATUS_DEGRADED, 'latencia' => '890ms', 'uptime' => 97.2, 'events' => 89],
            ['nome' => 'Cortex API', 'status' => TiIntegracao::STATUS_HEALTHY, 'latencia' => '45ms', 'uptime' => 99.99, 'events' => 8420],
            ['nome' => 'ERP ↔ Suprimentos', 'status' => TiIntegracao::STATUS_HEALTHY, 'latencia' => '210ms', 'uptime' => 99.5, 'events' => 560],
            ['nome' => 'Slack notifications', 'status' => TiIntegracao::STATUS_HEALTHY, 'latencia' => '180ms', 'uptime' => 99.8, 'events' => 3200],
            ['nome' => 'Backup S3', 'status' => TiIntegracao::STATUS_DOWN, 'latencia' => '—', 'uptime' => 94.1, 'events' => 12],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function logsSeed(): array
    {
        return [
            ['integracao' => 'eSocial webhook', 'conector' => 'eSocial', 'nivel' => 'error', 'mensagem' => 'HTTP 502 — retry 3/5', 'hora' => '09:14:00'],
            ['integracao' => 'Cortex API', 'conector' => 'Cortex API', 'nivel' => 'info', 'mensagem' => 'Triagem automática concluída', 'hora' => '09:12:00'],
            ['integracao' => 'Backup S3', 'conector' => 'Backup S3', 'nivel' => 'error', 'mensagem' => 'Disk quota exceeded on node BKP-02', 'hora' => '09:08:00'],
            ['integracao' => 'Núcleo RH → AD', 'conector' => 'Núcleo RH → AD', 'nivel' => 'info', 'mensagem' => 'Sync 14 admissões processadas', 'hora' => '08:55:00'],
            ['integracao' => 'Slack notifications', 'conector' => 'Slack', 'nivel' => 'warn', 'mensagem' => 'Rate limit próximo — fila 890 msgs', 'hora' => '08:40:00'],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function manutencoesSeed(): array
    {
        return [
            ['titulo' => 'Patch firewall datacenter', 'janela' => '31/05 02:00–04:00', 'impacto' => 'VPN instável', 'status' => TiManutencao::STATUS_SCHEDULED, 'owner' => 'Infra'],
            ['titulo' => 'Upgrade cluster Kubernetes', 'janela' => '07/06 01:00–05:00', 'impacto' => 'APIs indisponíveis', 'status' => TiManutencao::STATUS_SCHEDULED, 'owner' => 'DevOps'],
            ['titulo' => 'Renovação certificado SSL', 'janela' => '02/06 23:00–23:30', 'impacto' => 'Portal RH', 'status' => TiManutencao::STATUS_APPROVED, 'owner' => 'Segurança'],
            ['titulo' => 'Manutenção ar condicionado CPD', 'janela' => '28/05 14:00–18:00', 'impacto' => 'Monitoramento alerta', 'status' => TiManutencao::STATUS_DONE, 'owner' => 'Facilities'],
        ];
    }
}
