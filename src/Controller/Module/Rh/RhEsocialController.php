<?php

namespace App\Controller\Module\Rh;

use App\Entity\User;
use App\Exception\RhProcessException;
use App\Repository\RhEsocialLoteRepository;
use App\Service\Rh\RhEsocialService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rh/esocial')]
#[IsGranted('ROLE_USER')]
class RhEsocialController extends AbstractController
{
    use RhEmpresaScopeTrait;

    private const T = 'modules/rh/esocial/';

    public function __construct(
        private WorkspaceService $workspace,
        private RhEsocialService $esocial,
        private RhEsocialLoteRepository $loteRepo,
    ) {}

    protected function getWorkspace(): WorkspaceService
    {
        return $this->workspace;
    }

    #[Route('', name: 'app_rh_esocial', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();

        if ($request->isMethod('POST')) {
            $action = (string) $request->request->get('action', 'criar');

            try {
                /** @var User $user */
                $user = $this->getUser();

                if ($action === 'processar') {
                    $this->requireCsrf($request, 'rh_esocial_processar');
                    $stats = $this->esocial->processQueue($empresa, 15, $user);
                    $this->addFlash('success', sprintf(
                        'Fila processada: %d enviado(s), %d com erro.',
                        $stats['enviados'],
                        $stats['erros'],
                    ));
                } else {
                    $this->requireCsrf($request, 'rh_esocial_lote');
                    $this->esocial->createLote(
                        $empresa,
                        (string) $request->request->get('referencia', (new \DateTimeImmutable())->format('Y-m')),
                        (string) $request->request->get('tipo_evento', 'S1200'),
                        $user,
                    );
                    $this->addFlash('success', 'Lote enfileirado. Use "Processar fila" para enviar.');
                }
            } catch (RhProcessException $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('app_rh_esocial');
        }

        return $this->render(self::T . 'index.html.twig', [
            'lotes' => $this->esocial->listForEmpresa($empresa),
            'queue' => $this->esocial->queueSummary($empresa),
        ]);
    }

    #[Route('/{id}/retry', name: 'app_rh_esocial_retry', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function retry(Request $request, int $id): Response
    {
        $empresa = $this->requireEmpresa();

        try {
            $this->requireCsrf($request, 'rh_esocial_retry_' . $id);
            $lote = $this->loteRepo->find($id);
            if ($lote === null || $lote->getEmpresa()->getId() !== $empresa->getId()) {
                throw new RhProcessException('Lote não encontrado.');
            }

            /** @var User $user */
            $user = $this->getUser();
            $this->esocial->retryLote($lote, $user);
            $this->addFlash('success', 'Lote recolocado na fila.');
        } catch (RhProcessException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_rh_esocial');
    }
}
