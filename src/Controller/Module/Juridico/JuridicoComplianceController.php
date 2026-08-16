<?php

namespace App\Controller\Module\Juridico;

use App\Entity\JuridicoComplianceIncidente;
use App\Exception\JuridicoProcessException;
use App\Repository\JuridicoComplianceIncidenteRepository;
use App\Service\Juridico\JurisFlowAiClient;
use App\Service\WorkspaceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/juridico/compliance')]
#[IsGranted('ROLE_USER')]
class JuridicoComplianceController extends AbstractController
{
    use JuridicoEmpresaScopeTrait;

    public function __construct(
        private WorkspaceService $workspace,
        private JuridicoComplianceIncidenteRepository $repo,
        private EntityManagerInterface $em,
        private JurisFlowAiClient $ai,
    ) {
    }

    protected function getWorkspace(): WorkspaceService
    {
        return $this->workspace;
    }

    #[Route('', name: 'app_juridico_compliance')]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        if ($request->isMethod('POST') && $request->request->get('titulo')) {
            try {
                $this->requireCsrf($request, 'juridico_compliance_form');
                $titulo = trim((string) $request->request->get('titulo'));
                if ($titulo === '') {
                    throw new JuridicoProcessException('Informe o título do incidente.');
                }
                $inc = (new JuridicoComplianceIncidente())
                    ->setEmpresa($empresa)
                    ->setTitulo($titulo)
                    ->setDescricao(trim((string) $request->request->get('descricao')) ?: null)
                    ->setCategoria((string) $request->request->get('categoria', 'lgpd'));
                $this->em->persist($inc);
                $this->em->flush();
                $this->addFlash('success', 'Incidente registrado na trilha de compliance.');

                return $this->redirectToRoute('app_juridico_compliance');
            } catch (JuridicoProcessException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        $redacted = '';
        if ($request->isMethod('POST') && $request->request->get('texto_redact')) {
            $this->requireCsrf($request, 'juridico_compliance_redact');
            $redacted = $this->ai->redactPii((string) $request->request->get('texto_redact'));
        }

        $lista = $this->repo->findForEmpresa($empresa);

        return $this->render('modules/juridico/compliance_list.html.twig', [
            'incidentes' => $lista,
            'redacted' => $redacted,
            'metricas' => [
                'abertos' => $this->repo->countAbertos($empresa),
                'total' => \count($lista),
            ],
        ]);
    }

    #[Route('/{id}/resolver', name: 'app_juridico_compliance_resolver', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function resolver(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $this->requireCsrf($request, 'juridico_compliance_resolver_'.$id);
        $inc = $this->repo->findOneByEmpresa($empresa, $id);
        if ($inc) {
            $inc->setStatus(JuridicoComplianceIncidente::STATUS_RESOLVIDO)->setResolvidoEm(new \DateTimeImmutable());
            $this->em->flush();
        }

        return $this->redirectToRoute('app_juridico_compliance');
    }
}
