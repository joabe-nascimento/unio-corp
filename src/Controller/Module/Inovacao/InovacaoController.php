<?php

namespace App\Controller\Module\Inovacao;

use App\Entity\User;
use App\Service\InovacaoIdeiaService;
use App\Service\InovacaoService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/inovacao')]
#[IsGranted('ROLE_USER')]
final class InovacaoController extends AbstractController
{
    private const T = 'modules/inovacao/';

    public function __construct(
        private InovacaoService $service,
        private InovacaoIdeiaService $ideias,
        private WorkspaceService $workspace,
    ) {}

    #[Route('', name: 'app_inovacao')]
    public function overview(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        return $this->render(self::T . '_base.html.twig', $this->service->getDashboard($user));
    }

    #[Route('/pipeline', name: 'app_inovacao_pipeline')]
    public function pipeline(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        return $this->render(self::T . 'pipeline.html.twig', $this->service->getSection('pipeline', $user));
    }

    #[Route('/laboratorio', name: 'app_inovacao_laboratorio')]
    public function laboratorio(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        return $this->render(self::T . 'laboratorio.html.twig', $this->service->getSection('laboratorio', $user));
    }

    #[Route('/experimentos', name: 'app_inovacao_experimentos')]
    public function experimentos(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        return $this->render(self::T . 'experimentos.html.twig', $this->service->getSection('experimentos', $user));
    }

    #[Route('/backlog', name: 'app_inovacao_backlog')]
    public function backlog(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        return $this->render(self::T . 'backlog.html.twig', $this->service->getSection('backlog', $user));
    }

    #[Route('/analytics', name: 'app_inovacao_analytics')]
    public function analytics(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        return $this->render(self::T . 'analytics.html.twig', $this->service->getSection('analytics', $user));
    }

    #[Route('/conexoes', name: 'app_inovacao_conexoes')]
    public function conexoes(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        return $this->render(self::T . 'conexoes.html.twig', $this->service->getSection('conexoes', $user));
    }

    #[Route('/impact', name: 'app_inovacao_impact')]
    public function impact(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        return $this->render(self::T . 'impact.html.twig', $this->service->getSection('impact', $user));
    }

    #[Route('/tendencias', name: 'app_inovacao_tendencias')]
    public function tendencias(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        return $this->render(self::T . 'tendencias.html.twig', $this->service->getSection('tendencias', $user));
    }

    #[Route('/portfolio', name: 'app_inovacao_portfolio')]
    public function portfolio(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        return $this->render(self::T . 'portfolio.html.twig', $this->service->getSection('portfolio', $user));
    }

    #[Route('/novidades', name: 'app_inovacao_novidades')]
    public function novidades(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        return $this->render(self::T . 'novidades.html.twig', $this->service->getSection('novidades', $user));
    }

    #[Route('/nova-ideia', name: 'app_inovacao_nova_ideia', methods: ['GET'])]
    public function novaIdeia(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        return $this->render(self::T . 'nova_ideia.html.twig', $this->service->getSection('nova_ideia', $user));
    }

    #[Route('/nova-ideia/submit', name: 'app_inovacao_nova_ideia_submit', methods: ['POST'])]
    public function submitIdeia(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();
        if (!$empresa) {
            $this->addFlash('error', 'Selecione uma área de trabalho para registrar a ideia.');
            return $this->redirectToRoute('app_inovacao_nova_ideia');
        }

        if (!$this->isCsrfTokenValid('inovacao_nova_ideia', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token de segurança inválido.');
        }

        $categoryId = (string) $request->request->get('category', '');
        $hub = (string) $request->request->get('related_hub', '');
        $tags = array_values(array_filter([$this->categoryLabel($categoryId), $hub !== '' ? $hub : null]));

        try {
            $this->ideias->createFromForm($empresa, $user, [
                'titulo' => $request->request->get('title'),
                'problema' => $request->request->get('problem'),
                'hipotese' => $request->request->get('hypothesis'),
                'metrica_sucesso' => $request->request->get('metric'),
                'metodo_teste' => $request->request->get('test_method'),
                'hub_relacionado' => $hub !== '' ? $hub : null,
                'categoria' => $categoryId !== '' ? $categoryId : null,
                'impacto' => $request->request->get('impact'),
                'esforco' => $request->request->get('effort'),
                'urgencia' => $request->request->get('urgency'),
                'resumo' => $request->request->get('problem'),
                'tags' => $tags,
                'owner_nome' => $user->getNome() ?: $user->getEmail(),
            ]);
            $this->addFlash('success', 'Ideia registrada com sucesso no backlog de inovação!');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('app_inovacao_nova_ideia');
        }

        return $this->redirectToRoute('app_inovacao_backlog');
    }

    private function categoryLabel(string $id): ?string
    {
        $map = [
            'automacao' => 'Automação',
            'ia_ml' => 'IA',
            'produto' => 'Produto',
            'processo' => 'Processo',
            'experiencia' => 'UX',
            'integracao' => 'Integrações',
            'dados' => 'Analytics',
            'cultura' => 'Cultura',
        ];

        return $map[$id] ?? null;
    }
}
