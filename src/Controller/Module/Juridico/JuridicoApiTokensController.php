<?php

namespace App\Controller\Module\Juridico;

use App\Entity\ApiToken;
use App\Entity\User;
use App\Repository\ApiTokenRepository;
use App\Service\Juridico\ApiTokenService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gestão de tokens da API Pública do Unio Jurídico e documentação de integração.
 */
#[Route('/juridico/api-publica')]
#[IsGranted('ROLE_USER')]
class JuridicoApiTokensController extends AbstractController
{
    use JuridicoEmpresaScopeTrait;

    public function __construct(
        private WorkspaceService $workspace,
        private ApiTokenRepository $tokenRepo,
        private ApiTokenService $tokenService,
    ) {
    }

    protected function getWorkspace(): WorkspaceService
    {
        return $this->workspace;
    }

    #[Route('', name: 'app_juridico_api_tokens')]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();

        return $this->render('modules/juridico/api_publica.html.twig', [
            'tokens' => $this->tokenRepo->findForEmpresa($empresa),
            'base_url' => $request->getSchemeAndHttpHost(),
            'novo_token' => $request->getSession()->remove('juridico_api_token_novo') ?: null,
        ]);
    }

    #[Route('/gerar', name: 'app_juridico_api_tokens_gerar', methods: ['POST'])]
    #[IsGranted('ROLE_GESTOR')]
    public function gerar(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'juridico_api_token_gerar');

        $nome = trim((string) $request->request->get('nome', ''));
        $escrita = $request->request->getBoolean('escrita');
        $scopes = [ApiToken::SCOPE_LEITURA];
        if ($escrita) {
            $scopes[] = ApiToken::SCOPE_ESCRITA;
        }

        /** @var User $user */
        $user = $this->getUser();
        $resultado = $this->tokenService->gerar($empresa, $nome, $scopes, $user);

        $request->getSession()->set('juridico_api_token_novo', $resultado['raw']);
        $this->addFlash('success', 'Token gerado com sucesso. Copie agora — ele não será exibido novamente.');

        return $this->redirectToRoute('app_juridico_api_tokens');
    }

    #[Route('/{id}/revogar', name: 'app_juridico_api_tokens_revogar', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_GESTOR')]
    public function revogar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'juridico_api_token_revogar_' . $id);

        $token = $this->tokenRepo->findOneBy(['id' => $id, 'empresa' => $empresa]);
        if ($token !== null) {
            $this->tokenService->revogar($token);
            $this->addFlash('success', 'Token revogado.');
        }

        return $this->redirectToRoute('app_juridico_api_tokens');
    }
}
