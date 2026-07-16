<?php

namespace App\Controller\Module\PosOperatorio;

use App\Entity\Empresa;
use App\Entity\User;
use App\Service\PosOperatorio\ClinicCadastroRules;
use App\Service\PosOperatorio\ClinicSoapTemplateService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pos-operatorio/soap-templates')]
#[IsGranted('ROLE_USER')]
final class PosOperatorioSoapTemplateController extends AbstractController
{
    public function __construct(
        private WorkspaceService $workspace,
        private ClinicSoapTemplateService $templates,
    ) {}

    #[Route('', name: 'app_pos_operatorio_soap_templates', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('clinic_soap_new', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Token inválido.');

                return $this->redirectToRoute('app_pos_operatorio_soap_templates');
            }

            try {
                $this->templates->create($empresa, $this->payload($request, true));
                $this->addFlash('success', 'Template SOAP cadastrado.');
            } catch (\Throwable $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('app_pos_operatorio_soap_templates');
        }

        $lista = $this->templates->list($empresa);
        $tiposProcedimento = ClinicCadastroRules::TIPOS_PROCEDIMENTO_SOAP;
        foreach ($lista as $template) {
            $tipo = trim((string) $template->getProcedimentoTipo());
            if ($tipo !== '' && !isset($tiposProcedimento[$tipo])) {
                $tiposProcedimento[$tipo] = $tipo;
            }
        }

        return $this->render('modules/pos-operatorio/soap-templates/index.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'soap_templates',
            'templates' => $lista,
            'tipos_procedimento' => $tiposProcedimento,
        ]);
    }

    #[Route('/{id}', name: 'app_pos_operatorio_soap_templates_editar', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function editar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $template = $this->templates->findForEmpresa($empresa, $id);
        if ($template === null) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('clinic_soap_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token inválido.');

            return $this->redirectToRoute('app_pos_operatorio_soap_templates');
        }

        try {
            $this->templates->update($template, $empresa, $this->payload($request, false));
            $this->addFlash('success', 'Template SOAP atualizado.');
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_pos_operatorio_soap_templates');
    }

    /** @return array<string, mixed> */
    private function payload(Request $request, bool $creating): array
    {
        return [
            'nome' => $request->request->getString('nome'),
            'procedimento_tipo' => $request->request->getString('procedimento_tipo'),
            'queixa' => $request->request->getString('queixa'),
            'exame' => $request->request->getString('exame'),
            'hipotese' => $request->request->getString('hipotese'),
            'conduta' => $request->request->getString('conduta'),
            'cid10_sugerido' => $request->request->getString('cid10_sugerido'),
            'ativo' => $creating ? true : $request->request->getBoolean('ativo'),
        ];
    }

    private function requireEmpresa(): Empresa
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();
        if ($empresa === null) {
            throw $this->createAccessDeniedException('Área de trabalho indisponível.');
        }

        return $empresa;
    }
}
