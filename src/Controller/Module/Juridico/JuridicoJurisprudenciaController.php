<?php

namespace App\Controller\Module\Juridico;

use App\Entity\User;
use App\Exception\JuridicoProcessException;
use App\Service\Juridico\JuridicoJurisprudenciaService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/juridico/jurisprudencia')]
#[IsGranted('ROLE_USER')]
class JuridicoJurisprudenciaController extends AbstractController
{
    use JuridicoEmpresaScopeTrait;

    public function __construct(
        private WorkspaceService $workspace,
        private JuridicoJurisprudenciaService $jurisprudencia,
    ) {}

    protected function getWorkspace(): WorkspaceService
    {
        return $this->workspace;
    }

    #[Route('', name: 'app_juridico_jurisprudencia')]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $relevancia = (string) $request->query->get('relevancia', '');
        $q = (string) $request->query->get('q', '');

        return $this->render('modules/juridico/jurisprudencia_list.html.twig', [
            'itens' => $this->jurisprudencia->findForEmpresa($empresa, $relevancia ?: null, $q ?: null),
            'filter_relevancia' => $relevancia,
            'filter_q' => $q,
            'open_novo' => $request->query->getBoolean('open_novo'),
        ]);
    }

    #[Route('/novo', name: 'app_juridico_jurisprudencia_novo', methods: ['GET', 'POST'])]
    public function novo(Request $request): Response
    {
        $empresa = $this->requireEmpresa();

        if ($request->isMethod('GET')) {
            return $this->redirectToRoute('app_juridico_jurisprudencia', ['open_novo' => 1]);
        }

        /** @var User|null $user */
        $user = $this->getUser();

        try {
            $this->requireCsrf($request, 'juridico_jurisprudencia_form');
            $this->jurisprudencia->create($empresa, $request->request->all(), $user);
            $this->addFlash('success', 'Registro adicionado.');
        } catch (JuridicoProcessException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_juridico_jurisprudencia');
    }

    #[Route('/{id}/editar', name: 'app_juridico_jurisprudencia_editar', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function editar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $item = $this->jurisprudencia->loadForEmpresa($empresa, $id);

        if ($request->isMethod('POST')) {
            try {
                $this->requireCsrf($request, 'juridico_jurisprudencia_form');
                $this->jurisprudencia->update($item, $request->request->all());
                $this->addFlash('success', 'Registro atualizado.');

                return $this->redirectToRoute('app_juridico_jurisprudencia');
            } catch (JuridicoProcessException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('modules/juridico/jurisprudencia_editar.html.twig', [
            'item' => $item,
        ]);
    }

    #[Route('/{id}/excluir', name: 'app_juridico_jurisprudencia_excluir', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function excluir(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $item = $this->jurisprudencia->loadForEmpresa($empresa, $id);
        $this->requireCsrf($request, 'juridico_jurisprudencia_excluir_' . $id);
        $this->jurisprudencia->delete($item);
        $this->addFlash('success', 'Registro excluído.');

        return $this->redirectToRoute('app_juridico_jurisprudencia');
    }
}
