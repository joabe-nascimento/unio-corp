<?php

namespace App\Controller\Module\PosOperatorio;

use App\Entity\Empresa;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\PosOperatorio\ClinicCadastroRules;
use App\Service\PosOperatorio\ClinicProfissionalService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pos-operatorio/profissionais')]
#[IsGranted('ROLE_USER')]
final class PosOperatorioProfissionalController extends AbstractController
{
    public function __construct(
        private WorkspaceService $workspace,
        private ClinicProfissionalService $profissionais,
        private UserRepository $users,
    ) {}

    #[Route('', name: 'app_pos_operatorio_profissionais', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('clinic_profissional_new', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Token inválido.');

                return $this->redirectToRoute('app_pos_operatorio_profissionais');
            }

            try {
                $this->profissionais->create($empresa, $this->payload($request, true));
                $this->addFlash('success', 'Profissional cadastrado.');
            } catch (\Throwable $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('app_pos_operatorio_profissionais');
        }

        $lista = $this->profissionais->list($empresa);
        $especialidades = ClinicCadastroRules::ESPECIALIDADES;
        $conselhos = ClinicCadastroRules::CONSELHOS;
        foreach ($lista as $profissional) {
            $esp = trim((string) $profissional->getEspecialidade());
            if ($esp !== '' && !isset($especialidades[$esp])) {
                $especialidades[$esp] = $esp;
            }
            $conselho = trim((string) $profissional->getConselho());
            if ($conselho !== '' && !isset($conselhos[$conselho])) {
                $conselhos[$conselho] = $conselho;
            }
        }

        return $this->render('modules/pos-operatorio/profissionais/index.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'profissionais',
            'profissionais' => $lista,
            'usuarios' => $this->users->findActiveByEmpresa($empresa),
            'conselhos' => $conselhos,
            'especialidades' => $especialidades,
            'ufs' => ClinicCadastroRules::ufSelectOptions(true),
        ]);
    }

    #[Route('/{id}', name: 'app_pos_operatorio_profissionais_editar', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function editar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $profissional = $this->profissionais->findForEmpresa($empresa, $id);
        if ($profissional === null) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('clinic_profissional_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token inválido.');

            return $this->redirectToRoute('app_pos_operatorio_profissionais');
        }

        try {
            $this->profissionais->update($profissional, $empresa, $this->payload($request, false));
            $this->addFlash('success', 'Profissional atualizado.');
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_pos_operatorio_profissionais');
    }

    /** @return array<string, mixed> */
    private function payload(Request $request, bool $creating): array
    {
        $data = [
            'nome' => $request->request->getString('nome'),
            'conselho' => $request->request->getString('conselho'),
            'numero_conselho' => $request->request->getString('numero_conselho'),
            'uf_conselho' => $request->request->getString('uf_conselho'),
            'especialidade' => $request->request->getString('especialidade'),
            'telefone' => $request->request->getString('telefone'),
            'email' => $request->request->getString('email'),
            'user_id' => $request->request->get('user_id'),
        ];
        if (!$creating) {
            $data['ativo'] = $request->request->getBoolean('ativo');
        } else {
            $data['ativo'] = true;
        }

        return $data;
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
