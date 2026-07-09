<?php

namespace App\Command;

use App\Command\Concern\ProdSeedGuardTrait;
use App\Entity\Empresa;
use App\Entity\PosOperatorioEvento;
use App\Entity\PosOperatorioPaciente;
use App\Entity\PosOperatorioProtocolo;
use App\Entity\User;
use App\Repository\EmpresaRepository;
use App\Repository\UserRepository;
use App\Service\PosOperatorio\PosOperatorioEventRecorder;
use App\Service\PosOperatorio\PosOperatorioQuestionarioService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:pos-operatorio:seed',
    description: 'Popula protocolos e pacientes demo da Unio Clínica',
)]
final class PosOperatorioSeedCommand extends Command
{
    use ProdSeedGuardTrait;

    public function __construct(
        private EntityManagerInterface $em,
        private EmpresaRepository $empresaRepo,
        private UserRepository $userRepo,
        private PosOperatorioQuestionarioService $questionarioService,
        private PosOperatorioEventRecorder $events,
        private string $appEnv = 'dev',
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->configureProdSeedGuard();
        $this->addOption('empresa-id', null, InputOption::VALUE_REQUIRED, 'ID da empresa (workspace)');
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Recriar se já existir seed PO-DEMO');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (($code = $this->refuseInProductionUnlessAllowed($input, $io)) !== null) {
            return $code;
        }

        $empresaId = $input->getOption('empresa-id');
        $empresa = $empresaId
            ? $this->empresaRepo->find((int) $empresaId)
            : $this->empresaRepo->findOneBy([], ['id' => 'ASC']);

        if (!$empresa instanceof Empresa) {
            $io->error('Empresa não encontrada. Use --empresa-id=1');

            return Command::FAILURE;
        }

        $medico = $this->userRepo->findOneBy(['empresa' => $empresa], ['id' => 'ASC']);
        if (!$medico instanceof User) {
            $medico = $this->userRepo->findOneBy([], ['id' => 'ASC']);
        }

        $existing = $this->em->getRepository(PosOperatorioPaciente::class)->findOneBy([
            'empresa' => $empresa,
            'codigo' => 'PO-DEMO-001',
        ]);

        if ($existing && !$input->getOption('force')) {
            $io->warning('Seed já existe (PO-DEMO-001). Use --force para recriar.');

            return Command::SUCCESS;
        }

        if ($existing && $input->getOption('force')) {
            $this->em->remove($existing);
            $this->em->flush();
        }

        $protocolo = (new PosOperatorioProtocolo())
            ->setEmpresa($empresa)
            ->setNome('Apendicectomia laparoscópica')
            ->setTipoProcedimento('apendicectomia')
            ->setDuracaoDias(14)
            ->setChecklist([
                ['dia' => 1, 'item' => 'Repouso relativo'],
                ['dia' => 3, 'item' => 'Retirada de curativo'],
            ])
            ->setPerguntas([
                ['id' => 'dor', 'tipo' => 'escala', 'label' => 'Nível de dor'],
                ['id' => 'febre', 'tipo' => 'numero', 'label' => 'Temperatura'],
            ])
            ->setRegrasAlerta(['dor_p1_min' => 8, 'febre_p2_min' => 38.5])
            ->setAtivo(true);

        $pacienteOk = (new PosOperatorioPaciente())
            ->setEmpresa($empresa)
            ->setProtocolo($protocolo)
            ->setMedicoResponsavel($medico)
            ->setCodigo('PO-DEMO-001')
            ->setNome('Maria Silva Demo')
            ->setProcedimento('Apendicectomia laparoscópica')
            ->setDataCirurgia(new \DateTimeImmutable('-3 days'))
            ->setStatus(PosOperatorioPaciente::STATUS_ATIVO)
            ->setTelefoneContato('+5511999990001');

        $pacienteAlerta = (new PosOperatorioPaciente())
            ->setEmpresa($empresa)
            ->setProtocolo($protocolo)
            ->setMedicoResponsavel($medico)
            ->setCodigo('PO-DEMO-002')
            ->setNome('João Pereira Demo')
            ->setProcedimento('Herniorrafia inguinal')
            ->setDataCirurgia(new \DateTimeImmutable('-1 day'))
            ->setStatus(PosOperatorioPaciente::STATUS_PENDENTE)
            ->setTelefoneContato('+5511999990002');

        $this->em->persist($protocolo);
        $this->em->persist($pacienteOk);
        $this->em->persist($pacienteAlerta);

        $this->events->record($pacienteOk, PosOperatorioEvento::TIPO_CADASTRO, 'Paciente demo cadastrado', $medico);
        $this->events->record($pacienteAlerta, PosOperatorioEvento::TIPO_CADASTRO, 'Paciente demo cadastrado', $medico);
        $this->em->flush();

        $portalUser = $this->userRepo->findOneBy(['email' => 'tenant@unio.dev']) ?? $medico;
        if ($portalUser instanceof User) {
            $pacienteOk->setPortalUser($portalUser);
            $this->em->flush();
            $io->note(sprintf('Portal vinculado ao usuário %s (PO-DEMO-001).', $portalUser->getEmail()));
        }

        $this->questionarioService->submit($pacienteOk, [
            'dor' => 3,
            'febre' => 36.7,
            'nausea' => 'nao',
        ], $medico);

        $this->questionarioService->submit($pacienteAlerta, [
            'dor' => 8,
            'febre' => 38.6,
            'nausea' => 'leve',
        ], $medico);

        $io->success(sprintf(
            'Seed Unio Clínica criado para «%s»: PO-DEMO-001 (estável) e PO-DEMO-002 (alerta esperado).',
            $empresa->getNome() ?? 'workspace',
        ));

        return Command::SUCCESS;
    }
}
