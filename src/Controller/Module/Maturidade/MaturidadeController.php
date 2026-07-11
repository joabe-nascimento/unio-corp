<?php

namespace App\Controller\Module\Maturidade;

use App\Entity\User;
use App\Repository\DevProjetoRepository;
use App\Service\Organismo\OrganismoCopyService;
use App\Service\Organismo\StudioNavBadgeService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/maturidade')]
#[IsGranted('ROLE_USER')]
class MaturidadeController extends AbstractController
{
    private const T = 'modules/maturidade/';

    public function __construct(
        private OrganismoCopyService $organismoCopy,
        private WorkspaceService $workspace,
        private StudioNavBadgeService $studioBadges,
        private DevProjetoRepository $projetos,
    ) {
    }

    #[Route('', name: 'app_maturidade')]
    public function index(): Response
    {
        if ($this->organismoCopy->isClinicProfile()) {
            return $this->render(self::T . 'index.html.twig');
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->render(self::T . 'index_studio.html.twig', [
                'projetos' => [],
                'studio_badges' => $this->studioBadges->forEmpresa(null, 0),
            ]);
        }

        $empresa = $this->workspace->getActiveEmpresa($user);
        $empresas = $this->workspace->getAvailableEmpresas($user);

        return $this->render(self::T . 'index_studio.html.twig', [
            'projetos' => $empresa !== null ? $this->projetos->findRecentActive($empresa, 6) : [],
            'studio_badges' => $this->studioBadges->forEmpresa($empresa, \count($empresas)),
        ]);
    }

    #[Route('/avaliacao', name: 'app_maturidade_avaliacao')]
    public function avaliacao(): Response
    {
        return $this->render(self::T . 'avaliacao.html.twig');
    }

    #[Route('/radar', name: 'app_maturidade_radar')]
    public function radar(): Response
    {
        return $this->render(self::T . 'radar.html.twig');
    }

    #[Route('/plano', name: 'app_maturidade_plano')]
    public function plano(): Response
    {
        return $this->render(self::T . 'plano.html.twig');
    }

    #[Route('/historico', name: 'app_maturidade_historico')]
    public function historico(): Response
    {
        return $this->render(self::T . 'historico.html.twig');
    }
}
