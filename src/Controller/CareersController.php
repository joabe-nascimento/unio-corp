<?php

namespace App\Controller;

use App\Entity\RhCandidatoAprovacao;
use App\Exception\RhProcessException;
use App\Service\Rh\RhCarreirasService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/carreiras')]
class CareersController extends AbstractController
{
    private const T = 'public/carreiras/';

    public function __construct(
        private RhCarreirasService $carreiras,
    ) {}

    #[Route('/{empresaSlug}', name: 'app_carreiras_index', methods: ['GET'])]
    public function index(string $empresaSlug): Response
    {
        try {
            $empresa = $this->carreiras->resolveEmpresa($empresaSlug);
        } catch (RhProcessException $e) {
            throw $this->createNotFoundException($e->getMessage());
        }

        return $this->render(self::T . 'index.html.twig', [
            'empresa' => $empresa,
            'vagas' => $this->carreiras->listVagasPublicas($empresa),
        ]);
    }

    #[Route('/{empresaSlug}/{vagaSlug}', name: 'app_carreiras_vaga', methods: ['GET', 'POST'])]
    public function vaga(string $empresaSlug, string $vagaSlug, Request $request): Response
    {
        try {
            $empresa = $this->carreiras->resolveEmpresa($empresaSlug);
            $vaga = $this->carreiras->resolveVaga($empresa, $vagaSlug);
        } catch (RhProcessException $e) {
            throw $this->createNotFoundException($e->getMessage());
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('carreiras_apply_' . $vaga->getId(), (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Sessão expirada. Tente novamente.');
            } else {
                try {
                    $file = $request->files->get('curriculo');
                    $this->carreiras->apply(
                        $vaga,
                        (string) $request->request->get('nome', ''),
                        (string) $request->request->get('email', ''),
                        (string) $request->request->get('telefone', ''),
                        (string) $request->request->get('linkedin', ''),
                        $file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile ? $file : null,
                    );

                    return $this->redirectToRoute('app_carreiras_obrigado', [
                        'empresaSlug' => $empresaSlug,
                        'vagaSlug' => $vagaSlug,
                    ]);
                } catch (RhProcessException $e) {
                    $this->addFlash('error', $e->getMessage());
                }
            }
        }

        return $this->render(self::T . 'vaga.html.twig', [
            'empresa' => $empresa,
            'vaga' => $vaga,
        ]);
    }

    #[Route('/{empresaSlug}/{vagaSlug}/obrigado', name: 'app_carreiras_obrigado', methods: ['GET'])]
    public function obrigado(string $empresaSlug, string $vagaSlug): Response
    {
        try {
            $empresa = $this->carreiras->resolveEmpresa($empresaSlug);
            $vaga = $this->carreiras->resolveVaga($empresa, $vagaSlug);
        } catch (RhProcessException $e) {
            throw $this->createNotFoundException($e->getMessage());
        }

        return $this->render(self::T . 'obrigado.html.twig', [
            'empresa' => $empresa,
            'vaga' => $vaga,
        ]);
    }
}
