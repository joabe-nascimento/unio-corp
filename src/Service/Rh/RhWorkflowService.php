<?php

namespace App\Service\Rh;

use App\Entity\Empresa;
use App\Entity\RhWorkflowTemplate;
use App\Entity\User;
use App\Exception\RhProcessException;
use App\Repository\RhWorkflowTemplateRepository;
use Doctrine\ORM\EntityManagerInterface;

class RhWorkflowService
{
    public function __construct(
        private EntityManagerInterface $em,
        private RhWorkflowTemplateRepository $repo,
        private RhAuditService $audit,
    ) {}

    /** @return list<RhWorkflowTemplate> */
    public function listForEmpresa(Empresa $empresa): array
    {
        return $this->repo->findForEmpresa($empresa);
    }

    /**
     * @param list<array{id: string, label: string, done?: bool}> $checklist
     */
    public function save(
        Empresa $empresa,
        string $codigo,
        string $nome,
        string $tipoProcesso,
        array $checklist,
        bool $ativo = true,
        ?RhWorkflowTemplate $existing = null,
        ?User $actor = null,
    ): RhWorkflowTemplate {
        $codigo = strtolower(preg_replace('/[^a-z0-9_]+/i', '_', trim($codigo)) ?? '');
        if ($codigo === '' || trim($nome) === '') {
            throw new RhProcessException('Informe código e nome do template.');
        }

        $template = $existing ?? new RhWorkflowTemplate();
        if (!$existing) {
            $dup = $this->repo->findOneBy(['empresa' => $empresa, 'codigo' => $codigo]);
            if ($dup) {
                throw new RhProcessException('Já existe um template com este código.');
            }
            $template->setEmpresa($empresa);
            $template->setCodigo($codigo);
            $this->em->persist($template);
        }

        $template->setNome(trim($nome));
        $template->setTipoProcesso($tipoProcesso);
        $template->setChecklist($checklist);
        $template->setAtivo($ativo);

        $this->em->flush();
        $this->audit->log($empresa, $actor, 'workflows', $existing ? 'atualizar_template' : 'criar_template', 'rh_workflow_template', $template->getId());

        return $template;
    }
}
