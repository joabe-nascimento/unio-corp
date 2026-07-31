<?php

declare(strict_types=1);

namespace App\Controller\Module\Juridico;

use App\Service\Juridico\AgenteAutonomoStatusStore;
use App\Service\Organismo\OrganismoCopyService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Studio Sasha — workspace imersivo de chat jurídico (tela dedicada).
 */
#[Route('/juridico/studio')]
#[IsGranted('ROLE_USER')]
final class JuridicoSashaStudioController extends AbstractController
{
    public function __construct(
        private OrganismoCopyService $organismoCopy,
        private AgenteAutonomoStatusStore $agenteStatus,
    ) {
    }

    #[Route('', name: 'app_juridico_sasha_studio', methods: ['GET'])]
    public function index(): Response
    {
        if (!$this->organismoCopy->isJuridicoProfile()) {
            throw new AccessDeniedHttpException('Studio disponível apenas no Unio Jurídico.');
        }

        return $this->render('modules/juridico/sasha_studio.html.twig', [
            'agente' => $this->agenteStatus->resumo(),
            'assistant_name' => $this->organismoCopy->lumen(),
        ]);
    }
}
