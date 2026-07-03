<?php



namespace App\Controller\Module\PosOperatorio;



use App\Entity\User;

use App\Repository\UserRepository;

use App\Service\PosOperatorio\PosOperatorioAuditService;

use App\Service\PosOperatorio\PosOperatorioPacienteService;

use App\Service\PosOperatorio\PosOperatorioProtocoloService;

use App\Service\WorkspaceService;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\Request;

use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\Routing\Attribute\Route;

use Symfony\Component\Security\Http\Attribute\IsGranted;



#[Route('/pos-operatorio/pacientes')]

#[IsGranted('ROLE_USER')]

final class PosOperatorioPacienteController extends AbstractController

{

    private const T = 'modules/pos-operatorio/pacientes/';



    public function __construct(

        private WorkspaceService $workspace,

        private PosOperatorioPacienteService $service,

        private PosOperatorioProtocoloService $protocoloService,

        private UserRepository $userRepo,

        private PosOperatorioAuditService $audit,

    ) {}



    #[Route('', name: 'app_pos_operatorio_pacientes')]

    public function index(): Response

    {

        $empresa = $this->requireEmpresa();



        return $this->render(self::T . 'index.html.twig', array_merge(

            [

                'empresa' => $empresa,

                'pos_section' => 'pacientes',

                'pacientes' => $this->service->listByEmpresa($empresa),

            ],

            $this->formContext($empresa, null),

        ));

    }



    #[Route('/novo', name: 'app_pos_operatorio_paciente_novo', methods: ['GET', 'POST'])]

    public function novo(Request $request): Response

    {

        $empresa = $this->requireEmpresa();



        if ($request->isMethod('POST')) {

            return $this->handleSave($request, $empresa, null);

        }



        return $this->redirectToRoute('app_pos_operatorio_pacientes', ['open_novo' => 1]);

    }



    #[Route('/{id}', name: 'app_pos_operatorio_paciente_show', requirements: ['id' => '\d+'])]

    public function show(int $id, Request $request): Response

    {

        $empresa = $this->requireEmpresa();

        $paciente = $this->service->findForEmpresa($empresa, $id);

        if (!$paciente) {

            throw $this->createNotFoundException();

        }



        /** @var User $user */

        $user = $this->getUser();

        $this->audit->logAccess($paciente, $user, 'ficha_paciente', $request);



        return $this->redirectToRoute('app_pos_operatorio_pacientes', ['open_ficha' => $id]);

    }



    #[Route('/{id}/ficha-partial', name: 'app_pos_operatorio_paciente_ficha_partial', requirements: ['id' => '\d+'])]

    public function fichaPartial(int $id, Request $request): Response

    {

        $empresa = $this->requireEmpresa();

        $paciente = $this->service->findForEmpresa($empresa, $id);

        if (!$paciente) {

            throw $this->createNotFoundException();

        }



        /** @var User $user */

        $user = $this->getUser();

        $this->audit->logAccess($paciente, $user, 'ficha_paciente', $request);



        return $this->render(self::T . '_ficha_partial.html.twig', array_merge(

            ['empresa' => $empresa, 'pos_section' => 'pacientes'],

            $this->service->buildFicha($paciente),

        ));

    }



    #[Route('/{id}/form-partial', name: 'app_pos_operatorio_paciente_form_partial', requirements: ['id' => '\d+'])]

    public function formPartial(int $id): Response

    {

        $empresa = $this->requireEmpresa();

        $paciente = $this->service->findForEmpresa($empresa, $id);

        if (!$paciente) {

            throw $this->createNotFoundException();

        }



        return $this->render(self::T . '_form_edit_partial.html.twig', $this->formContext($empresa, $paciente));

    }



    #[Route('/{id}/editar', name: 'app_pos_operatorio_paciente_editar', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]

    public function editar(int $id, Request $request): Response

    {

        $empresa = $this->requireEmpresa();

        $paciente = $this->service->findForEmpresa($empresa, $id);

        if (!$paciente) {

            throw $this->createNotFoundException();

        }



        if ($request->isMethod('POST')) {

            return $this->handleSave($request, $empresa, $paciente);

        }



        return $this->redirectToRoute('app_pos_operatorio_pacientes', ['open_edit' => $id]);

    }



    /** @return array<string, mixed> */

    private function formContext(\App\Entity\Empresa $empresa, ?\App\Entity\PosOperatorioPaciente $paciente): array

    {

        $usuarios = $this->userRepo->findActiveByEmpresa($empresa);



        return [

            'empresa' => $empresa,

            'pos_section' => 'pacientes',

            'paciente' => $paciente,

            'protocolos' => $this->protocoloService->listByEmpresa($empresa),

            'medicos' => $usuarios,

            'portal_users' => $usuarios,

        ];

    }



    private function handleSave(Request $request, \App\Entity\Empresa $empresa, ?\App\Entity\PosOperatorioPaciente $paciente): Response

    {

        if (!$this->isCsrfTokenValid('pos_op_paciente', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token inválido.');

            return $this->redirectToList($paciente, $paciente !== null);
        }



        $nome = trim((string) $request->request->get('nome', ''));

        if ($nome === '') {

            $this->addFlash('error', 'Nome é obrigatório.');



            return $this->redirectToList($paciente, true);

        }



        /** @var User $user */

        $user = $this->getUser();

        $data = $request->request->all();



        if ($paciente) {

            $this->service->update($paciente, $data, $user);

            $this->addFlash('success', 'Paciente atualizado.');



            return $this->redirectToRoute('app_pos_operatorio_pacientes', ['open_ficha' => $paciente->getId()]);

        }



        $novo = $this->service->create($empresa, $data, $user);

        $this->addFlash('success', 'Paciente cadastrado.');



        return $this->redirectToRoute('app_pos_operatorio_pacientes', ['open_ficha' => $novo->getId()]);

    }



    private function redirectToList(?\App\Entity\PosOperatorioPaciente $paciente, bool $editMode = false): Response

    {

        if ($paciente && $editMode) {

            return $this->redirectToRoute('app_pos_operatorio_pacientes', ['open_edit' => $paciente->getId()]);

        }



        if ($paciente) {

            return $this->redirectToRoute('app_pos_operatorio_pacientes', ['open_ficha' => $paciente->getId()]);

        }



        return $this->redirectToRoute('app_pos_operatorio_pacientes', ['open_novo' => 1]);

    }



    private function requireEmpresa(): \App\Entity\Empresa

    {

        /** @var User $user */

        $user = $this->getUser();

        $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();

        if (!$empresa) {

            throw $this->createAccessDeniedException('Selecione um workspace.');

        }



        return $empresa;

    }

}


