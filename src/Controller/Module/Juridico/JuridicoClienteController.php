<?php

namespace App\Controller\Module\Juridico;

use App\Exception\JuridicoProcessException;
use App\Service\Juridico\JuridicoClienteService;
use App\Service\Juridico\JuridicoPortalInviteService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/juridico/clientes')]
#[IsGranted('ROLE_USER')]
class JuridicoClienteController extends AbstractController
{
    use JuridicoEmpresaScopeTrait;

    public function __construct(
        private WorkspaceService $workspace,
        private JuridicoClienteService $clientes,
        private JuridicoPortalInviteService $portalInvite,
    ) {}

    protected function getWorkspace(): WorkspaceService
    {
        return $this->workspace;
    }

    #[Route('', name: 'app_juridico_clientes')]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $status = (string) $request->query->get('status', '');
        $q = (string) $request->query->get('q', '');

        $clientes = $this->clientes->findForEmpresa($empresa, $status ?: null, $q ?: null);
        $rows = array_map(fn ($c) => [
            'cliente' => $c,
            'processos_ativos' => $this->clientes->processosAtivos($c),
            'valor_carteira' => $this->clientes->valorCarteira($c),
        ], $clientes);

        return $this->render('modules/juridico/clientes_list.html.twig', [
            'rows' => $rows,
            'filter_status' => $status,
            'filter_q' => $q,
            'open_novo' => $request->query->getBoolean('open_novo'),
        ]);
    }

    #[Route('/novo', name: 'app_juridico_cliente_novo', methods: ['GET', 'POST'])]
    public function novo(Request $request): Response
    {
        $empresa = $this->requireEmpresa();

        if ($request->isMethod('GET')) {
            return $this->redirectToRoute('app_juridico_clientes', ['open_novo' => 1]);
        }

        try {
            $this->requireCsrf($request, 'juridico_cliente_form');
            $cliente = $this->clientes->create($empresa, $request->request->all());
            $this->addFlash('success', 'Cliente cadastrado.');

            return $this->redirectToRoute('app_juridico_cliente_show', ['id' => $cliente->getId()]);
        } catch (JuridicoProcessException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_juridico_clientes', ['open_novo' => 1]);
        }
    }

    #[Route('/{id}', name: 'app_juridico_cliente_show', requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $empresa = $this->requireEmpresa();
        $cliente = $this->clientes->loadForEmpresa($empresa, $id);

        return $this->render('modules/juridico/cliente_show.html.twig', [
            'cliente' => $cliente,
            'processos_ativos' => $this->clientes->processosAtivos($cliente),
            'valor_carteira' => $this->clientes->valorCarteira($cliente),
        ]);
    }

    #[Route('/{id}/editar', name: 'app_juridico_cliente_editar', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function editar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $cliente = $this->clientes->loadForEmpresa($empresa, $id);

        if ($request->isMethod('GET')) {
            return $this->redirectToRoute('app_juridico_cliente_show', ['id' => $id, 'open_editar' => 1]);
        }

        try {
            $this->requireCsrf($request, 'juridico_cliente_form');
            $this->clientes->update($cliente, $request->request->all());
            $this->addFlash('success', 'Cliente atualizado.');
        } catch (JuridicoProcessException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_juridico_cliente_show', ['id' => $id]);
    }

    #[Route('/{id}/excluir', name: 'app_juridico_cliente_excluir', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function excluir(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $cliente = $this->clientes->loadForEmpresa($empresa, $id);

        try {
            $this->requireCsrf($request, 'juridico_cliente_excluir_' . $id);
            $this->clientes->delete($cliente);
            $this->addFlash('success', 'Cliente excluído.');

            return $this->redirectToRoute('app_juridico_clientes');
        } catch (JuridicoProcessException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_juridico_cliente_show', ['id' => $id]);
        }
    }

    #[Route('/{id}/portal-convite', name: 'app_juridico_cliente_portal_convite', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function portalConvite(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $cliente = $this->clientes->loadForEmpresa($empresa, $id);
        $this->requireCsrf($request, 'juridico_cliente_portal_' . $id);

        if ($cliente->hasPortalAtivo()) {
            $this->addFlash('info', 'Este cliente já possui acesso ao portal.');

            return $this->redirectToRoute('app_juridico_cliente_show', ['id' => $id]);
        }

        $url = $this->portalInvite->generateInvite($cliente);
        $this->addFlash('success', 'Link do portal gerado. Copie e envie ao cliente: ' . $url);

        return $this->redirectToRoute('app_juridico_cliente_show', ['id' => $id, 'portal_url' => $url]);
    }
}
