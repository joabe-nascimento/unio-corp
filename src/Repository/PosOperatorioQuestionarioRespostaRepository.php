<?php



namespace App\Repository;



use App\Entity\Empresa;

use App\Entity\PosOperatorioPaciente;

use App\Entity\PosOperatorioQuestionarioResposta;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

use Doctrine\Persistence\ManagerRegistry;



/** @extends ServiceEntityRepository<PosOperatorioQuestionarioResposta> */

class PosOperatorioQuestionarioRespostaRepository extends ServiceEntityRepository

{

    public function __construct(ManagerRegistry $registry)

    {

        parent::__construct($registry, PosOperatorioQuestionarioResposta::class);

    }



    public function findOneByPacienteAndDate(PosOperatorioPaciente $paciente, \DateTimeImmutable $day): ?PosOperatorioQuestionarioResposta

    {

        return $this->findOneBy([

            'paciente' => $paciente,

            'dataReferencia' => $day,

        ]);

    }



    /** @return list<PosOperatorioQuestionarioResposta> */

    public function findRecentByEmpresa(Empresa $empresa, int $limit = 50): array

    {

        return $this->createQueryBuilder('q')

            ->innerJoin('q.paciente', 'p')

            ->addSelect('p')

            ->andWhere('p.empresa = :empresa')

            ->setParameter('empresa', $empresa)

            ->orderBy('q.respondidoEm', 'DESC')

            ->setMaxResults($limit)

            ->getQuery()

            ->getResult();

    }



    public function countByEmpresaOnDate(Empresa $empresa, \DateTimeImmutable $day): int

    {

        return (int) $this->createQueryBuilder('q')

            ->select('COUNT(q.id)')

            ->innerJoin('q.paciente', 'p')

            ->andWhere('p.empresa = :empresa')

            ->andWhere('q.dataReferencia = :day')

            ->setParameter('empresa', $empresa)

            ->setParameter('day', $day)

            ->getQuery()

            ->getSingleScalarResult();

    }



    public function countPacientesPendentesHoje(Empresa $empresa, \DateTimeImmutable $day): int

    {

        return (int) $this->getEntityManager()->createQueryBuilder()

            ->select('COUNT(DISTINCT p.id)')

            ->from(PosOperatorioPaciente::class, 'p')

            ->leftJoin(

                PosOperatorioQuestionarioResposta::class,

                'q',

                'WITH',

                'q.paciente = p AND q.dataReferencia = :day',

            )

            ->andWhere('p.empresa = :empresa')

            ->andWhere('p.status IN (:statuses)')

            ->andWhere('q.id IS NULL')

            ->setParameter('empresa', $empresa)

            ->setParameter('day', $day)

            ->setParameter('statuses', [

                PosOperatorioPaciente::STATUS_ATIVO,

                PosOperatorioPaciente::STATUS_ALERTA,

                PosOperatorioPaciente::STATUS_PENDENTE,

            ])

            ->getQuery()

            ->getSingleScalarResult();

    }

}

