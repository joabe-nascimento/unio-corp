<?php

namespace App\Controller\Module\Pessoas;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pessoas')]
#[IsGranted('ROLE_SUPERVISOR_EQUIPE')]
class PessoasController extends AbstractController
{
    private const T = 'modules/pessoas/';

    #[Route('', name: 'app_pessoas')]
    public function index(): Response
    {
        return $this->render(self::T . 'index.html.twig');
    }

    // ── Membros ─────────────────────────────────────────────────────────────

    #[Route('/membros', name: 'app_pessoas_membros')]
    public function membros(): Response
    {
        return $this->render(self::T . 'membros.html.twig');
    }

    #[Route('/membros/novo', name: 'app_pessoas_membro_novo')]
    #[IsGranted('ROLE_GESTOR_EQUIPE')]
    public function membroNovo(): Response
    {
        return $this->render(self::T . 'membro_form.html.twig', ['modo' => 'novo']);
    }

    #[Route('/membros/{id}', name: 'app_pessoas_membro_ficha', requirements: ['id' => '\d+'])]
    public function membroFicha(int $id): Response
    {
        return $this->render(self::T . 'ficha.html.twig', ['membro_id' => $id]);
    }

    // ── Equipes ─────────────────────────────────────────────────────────────

    #[Route('/equipes', name: 'app_pessoas_equipes')]
    public function equipes(): Response
    {
        return $this->render(self::T . 'equipes.html.twig');
    }

    #[Route('/equipes/nova', name: 'app_pessoas_equipe_nova')]
    #[IsGranted('ROLE_GESTOR_EQUIPE')]
    public function equipeNova(): Response
    {
        return $this->render(self::T . 'equipe_form.html.twig', ['modo' => 'nova']);
    }

    #[Route('/equipes/{id}', name: 'app_pessoas_equipe_detalhe', requirements: ['id' => '\d+'])]
    public function equipeDetalhe(int $id): Response
    {
        return $this->render(self::T . 'equipe_detalhe.html.twig', ['equipe_id' => $id]);
    }

    // ── Demais submódulos ────────────────────────────────────────────────────

    #[Route('/cargos', name: 'app_pessoas_cargos')]
    public function cargos(): Response
    {
        return $this->render(self::T . 'cargos.html.twig');
    }

    #[Route('/organograma', name: 'app_pessoas_organograma')]
    public function organograma(): Response
    {
        return $this->render(self::T . 'organograma.html.twig');
    }

    #[Route('/avaliacao', name: 'app_pessoas_avaliacao')]
    public function avaliacao(): Response
    {
        return $this->render(self::T . 'avaliacao.html.twig');
    }
}
