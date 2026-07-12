<?php

namespace App\Controller\Clinic;

use App\Service\Beneficiary\BeneficiaryAccessService;
use App\Service\Clinic\PatientHubService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/paciente')]
final class PatientAccessHubController extends AbstractController
{
    public function __construct(
        private PatientHubService $hub,
        private BeneficiaryAccessService $access,
    ) {}

    #[Route('', name: 'app_paciente_hub')]
    public function hub(Request $request): Response
    {
        $view = $this->access->isGranted()
            ? $this->hub->buildAuthenticatedHub()
            : $this->hub->buildPublicLanding();

        return $this->render('clinic/patient_hub.html.twig', [
            'hub' => $view,
            'authenticated' => $this->access->isGranted(),
            'secao' => $request->query->getString('secao', 'inicio'),
        ]);
    }

    #[Route('/trocar/{patientId}', name: 'app_paciente_trocar', requirements: ['patientId' => '\d+'])]
    public function trocarDependente(int $patientId): Response
    {
        if (!$this->access->switchToPatient($patientId)) {
            $this->addFlash('error', 'Não foi possível trocar o beneficiário.');

            return $this->redirectToRoute('app_paciente_hub');
        }

        return $this->redirectToRoute('app_paciente_hub');
    }
}
