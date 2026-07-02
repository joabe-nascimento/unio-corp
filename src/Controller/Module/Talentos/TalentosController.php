<?php

namespace App\Controller\Module\Talentos;

use App\Repository\RhVagaRepository;
use App\Security\ProductGrantAccess;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/talentos')]
#[IsGranted('ROLE_USER')]
class TalentosController extends AbstractController
{
    private const T = 'modules/talentos/';

    public function __construct(
        private WorkspaceService $workspace,
        private RhVagaRepository $vagaRepo,
        private ProductGrantAccess $grants,
    ) {}

    #[Route('', name: 'app_talentos')]
    public function index(): Response
    {
        $user = $this->getUser();
        \assert($user instanceof \App\Entity\User);
        $empresa = $this->workspace->getActiveEmpresa($user);
        $vagasAbertas = 0;
        $totalVagas = 0;
        $showRecrutamento = false;

        if ($user && $empresa && $this->grants->isRouteAllowed($user, 'app_recrutamento_vagas')) {
            $showRecrutamento = true;
            $vagasAbertas = $this->vagaRepo->countAbertasByEmpresa($empresa);
            $totalVagas = \count($this->vagaRepo->findForEmpresa($empresa));
        }

        return $this->render(self::T . 'index.html.twig', [
            'vagas_abertas' => $vagasAbertas,
            'total_vagas' => $totalVagas,
            'show_recrutamento' => $showRecrutamento,
        ]);
    }

    #[Route('/banco', name: 'app_talentos_banco')]
    public function banco(): Response
    {
        return $this->render(self::T . 'banco.html.twig');
    }

    #[Route('/vagas', name: 'app_talentos_vagas')]
    public function vagas(): Response
    {
        if ($this->getUser() && $this->grants->isRouteAllowed($this->getUser(), 'app_recrutamento_vagas')) {
            return $this->redirectToRoute('app_recrutamento_vagas');
        }

        return $this->render(self::T . 'vagas.html.twig');
    }

    #[Route('/trilhas', name: 'app_talentos_trilhas')]
    public function trilhas(): Response
    {
        return $this->render(self::T . 'trilhas.html.twig');
    }

    #[Route('/mentoria', name: 'app_talentos_mentoria')]
    public function mentoria(): Response
    {
        return $this->render(self::T . 'mentoria.html.twig');
    }
}
