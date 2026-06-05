<?php



namespace App\Service\Integracoes;



use App\Entity\Empresa;

use App\Entity\IntegConector;

use App\Entity\IntegLog;

use App\Entity\IntegWebhook;

use App\Repository\IntegLogRepository;

use Doctrine\ORM\EntityManagerInterface;



final class IntegracaoLogService

{

    public function __construct(

        private EntityManagerInterface $em,

        private IntegLogRepository $repository,

    ) {}



    public function info(

        Empresa $empresa,

        string $mensagem,

        string $origem,

        ?IntegConector $conector = null,

        ?string $traceId = null,

    ): void {

        $this->write($empresa, IntegLog::LEVEL_INFO, $mensagem, $origem, $conector, null, $traceId);

    }



    public function warn(

        Empresa $empresa,

        string $mensagem,

        string $origem,

        ?IntegConector $conector = null,

        ?string $traceId = null,

    ): void {

        $this->write($empresa, IntegLog::LEVEL_WARN, $mensagem, $origem, $conector, null, $traceId);

    }



    public function error(

        Empresa $empresa,

        string $mensagem,

        string $origem,

        ?IntegConector $conector = null,

        ?string $traceId = null,

    ): void {

        $this->write($empresa, IntegLog::LEVEL_ERROR, $mensagem, $origem, $conector, null, $traceId);

    }



    /** @return list<IntegLog> */

    public function exportCsv(Empresa $empresa): array

    {

        return $this->repository->exportCsv($empresa);

    }



    /** @return array{total: int, page: int, limit: int, items: list<IntegLog>} */

    public function findFiltered(Empresa $empresa, array $filters = [], int $page = 1, int $limit = 50): array

    {

        return $this->repository->findForEmpresaFiltered($empresa, $filters, $page, $limit);

    }



    private function write(

        Empresa $empresa,

        string $nivel,

        string $mensagem,

        string $origem,

        ?IntegConector $conector = null,

        ?IntegWebhook $webhook = null,

        ?string $traceId = null,

    ): void {

        $log = (new IntegLog())

            ->setEmpresa($empresa)

            ->setNivel($nivel)

            ->setMensagem($mensagem)

            ->setOrigem($origem)

            ->setConector($conector)

            ->setWebhook($webhook)

            ->setTraceId($traceId);



        $this->em->persist($log);

        $this->em->flush();

    }

}



