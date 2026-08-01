<?php

namespace App\Controller\Module\Juridico;

use App\Exception\JuridicoProcessException;
use App\Repository\JuridicoClienteRepository;
use App\Repository\JuridicoProcessoRepository;
use App\Service\Juridico\JuridicoAtendimentoService;
use App\Service\Juridico\JuridicoAtendimentoTemplateService;
use App\Service\Juridico\JuridicoAtendimentoWhatsappService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/juridico/atendimento')]
#[IsGranted('ROLE_USER')]
class JuridicoAtendimentoController extends AbstractController
{
    use JuridicoEmpresaScopeTrait;

    public function __construct(
        private WorkspaceService $workspace,
        private JuridicoAtendimentoService $atendimento,
        private JuridicoAtendimentoTemplateService $templates,
        private JuridicoAtendimentoWhatsappService $whatsapp,
        private JuridicoClienteRepository $clienteRepo,
        private JuridicoProcessoRepository $processoRepo,
    ) {
    }

    protected function getWorkspace(): WorkspaceService
    {
        return $this->workspace;
    }

    #[Route('', name: 'app_juridico_atendimento')]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $status = (string) $request->query->get('status', '');
        $canal = (string) $request->query->get('canal', '');
        $q = (string) $request->query->get('q', '');

        $this->templates->ensureDefaults($empresa);

        return $this->render('modules/juridico/atendimento_list.html.twig', [
            'tickets' => $this->atendimento->findForEmpresa($empresa, $status ?: null, $canal ?: null, $q ?: null),
            'templates' => $this->templates->listarAtivos($empresa),
            'metricas' => $this->atendimento->metricas($empresa),
            'whatsapp_live' => $this->whatsapp->isDisponivel(),
            'clientes' => $this->clienteRepo->findAllForSelect($empresa),
            'processos' => $this->processoRepo->findAllForSelect($empresa),
            'filter_status' => $status,
            'filter_canal' => $canal,
            'filter_q' => $q,
            'open_novo' => $request->query->getBoolean('novo'),
            'open_templates' => $request->query->getBoolean('templates'),
        ]);
    }

    #[Route('/novo', name: 'app_juridico_atendimento_novo', methods: ['POST'])]
    public function novo(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'juridico_atendimento_novo');

        try {
            /** @var \App\Entity\User $user */
            $user = $this->getUser();
            $ticket = $this->atendimento->criarTicket($empresa, $request->request->all(), $user);
            $this->addFlash('success', 'Ticket criado.');

            return $this->redirectToRoute('app_juridico_atendimento_show', ['id' => $ticket->getId()]);
        } catch (JuridicoProcessException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_juridico_atendimento', ['novo' => 1]);
        }
    }

    #[Route('/{id}', name: 'app_juridico_atendimento_show', requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $empresa = $this->requireEmpresa();
        $ticket = $this->atendimento->loadForEmpresa($empresa, $id);
        $contexto = $this->atendimento->contextoCaso($ticket);

        return $this->render('modules/juridico/atendimento_show.html.twig', [
            'ticket' => $ticket,
            'contexto' => $contexto,
            'templates' => $this->templates->listarAtivos($empresa),
            'whatsapp_live' => $this->whatsapp->isDisponivel(),
            'clientes' => $this->clienteRepo->findAllForSelect($empresa),
            'processos' => $this->processoRepo->findAllForSelect($empresa),
        ]);
    }

    #[Route('/{id}/mensagem', name: 'app_juridico_atendimento_mensagem', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function mensagem(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $ticket = $this->atendimento->loadForEmpresa($empresa, $id);
        $this->requireCsrf($request, 'juridico_atendimento_msg_' . $id);

        try {
            /** @var \App\Entity\User $user */
            $user = $this->getUser();
            $this->atendimento->enviarResposta(
                $ticket,
                (string) $request->request->get('corpo', ''),
                $user,
                $request->request->getBoolean('enviar_whatsapp'),
                $request->request->getBoolean('nota_interna'),
            );
            $this->addFlash('success', 'Mensagem registrada.');
        } catch (JuridicoProcessException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_juridico_atendimento_show', ['id' => $id]);
    }

    #[Route('/{id}/status', name: 'app_juridico_atendimento_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function status(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $ticket = $this->atendimento->loadForEmpresa($empresa, $id);
        $this->requireCsrf($request, 'juridico_atendimento_status_' . $id);

        try {
            $this->atendimento->atualizarStatus($ticket, (string) $request->request->get('status', ''));
            $this->addFlash('success', 'Status atualizado.');
        } catch (JuridicoProcessException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_juridico_atendimento_show', ['id' => $id]);
    }

    #[Route('/{id}/vincular', name: 'app_juridico_atendimento_vincular', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function vincular(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $ticket = $this->atendimento->loadForEmpresa($empresa, $id);
        $this->requireCsrf($request, 'juridico_atendimento_vincular_' . $id);

        try {
            $this->atendimento->vincular($ticket, $request->request->all());
            $this->addFlash('success', 'Vínculos atualizados.');
        } catch (JuridicoProcessException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_juridico_atendimento_show', ['id' => $id]);
    }

    #[Route('/{id}/sugerir-sasha', name: 'app_juridico_atendimento_sugerir', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function sugerirSasha(int $id, Request $request): JsonResponse
    {
        $empresa = $this->requireEmpresa();
        $ticket = $this->atendimento->loadForEmpresa($empresa, $id);
        $this->requireCsrf($request, 'juridico_atendimento_sugerir_' . $id);

        try {
            $sugestao = $this->templates->sugerirComSasha(
                $ticket,
                (string) $request->request->get('instrucao', ''),
            );

            return $this->json(['ok' => true, 'sugestao' => $sugestao]);
        } catch (JuridicoProcessException $e) {
            return $this->json(['ok' => false, 'erro' => $e->getMessage()], 422);
        }
    }

    #[Route('/templates/novo', name: 'app_juridico_atendimento_template_novo', methods: ['POST'])]
    public function templateNovo(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'juridico_atendimento_template');

        try {
            $this->templates->criar($empresa, $request->request->all());
            $this->addFlash('success', 'Template criado.');
        } catch (JuridicoProcessException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_juridico_atendimento', ['templates' => 1]);
    }

    #[Route('/templates/{id}/editar', name: 'app_juridico_atendimento_template_editar', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function templateEditar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'juridico_atendimento_template_edit_' . $id);

        try {
            $tpl = $this->templates->loadForEmpresa($empresa, $id);
            $this->templates->atualizar($tpl, $request->request->all());
            $this->addFlash('success', 'Template atualizado.');
        } catch (JuridicoProcessException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_juridico_atendimento', ['templates' => 1]);
    }

    #[Route('/templates/{id}/excluir', name: 'app_juridico_atendimento_template_excluir', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function templateExcluir(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'juridico_atendimento_template_del_' . $id);

        try {
            $tpl = $this->templates->loadForEmpresa($empresa, $id);
            $this->templates->excluir($tpl);
            $this->addFlash('success', 'Template removido.');
        } catch (JuridicoProcessException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_juridico_atendimento', ['templates' => 1]);
    }

    #[Route('/templates/{id}/preview/{ticketId}', name: 'app_juridico_atendimento_template_preview', requirements: ['id' => '\d+', 'ticketId' => '\d+'], methods: ['GET'])]
    public function templatePreview(int $id, int $ticketId): JsonResponse
    {
        $empresa = $this->requireEmpresa();
        $ticket = $this->atendimento->loadForEmpresa($empresa, $ticketId);
        $tpl = $this->templates->loadForEmpresa($empresa, $id);

        return $this->json([
            'ok' => true,
            'texto' => $this->templates->renderizar($tpl, $ticket),
        ]);
    }
}
