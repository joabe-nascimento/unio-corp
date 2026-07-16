<?php

namespace App\Controller\Module\PosOperatorio;

use App\Entity\ClinicPacote;
use App\Entity\Empresa;
use App\Entity\User;
use App\Service\PosOperatorio\ClinicPacoteService;
use App\Service\PosOperatorio\ClinicProcedimentoService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pos-operatorio/pacotes')]
#[IsGranted('ROLE_USER')]
final class PosOperatorioPacoteController extends AbstractController
{
    public function __construct(
        private WorkspaceService $workspace,
        private ClinicPacoteService $pacotes,
        private ClinicProcedimentoService $procedimentos,
    ) {}

    #[Route('', name: 'app_pos_operatorio_pacotes', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $empresa = $this->requireEmpresa();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('clinic_pacote_new', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Token inválido.');

                return $this->redirectToRoute('app_pos_operatorio_pacotes');
            }

            try {
                $this->pacotes->create($empresa, $this->payload($request, true));
                $this->addFlash('success', 'Pacote cadastrado.');
            } catch (\Throwable $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('app_pos_operatorio_pacotes');
        }

        $ativo = $request->query->getString('ativo', '');
        $q = trim($request->query->getString('q'));
        $all = $this->pacotes->list($empresa);
        $pacotes = array_values(array_filter(
            $all,
            static function (ClinicPacote $pacote) use ($ativo, $q): bool {
                if ($ativo === '1' && !$pacote->isAtivo()) {
                    return false;
                }
                if ($ativo === '0' && $pacote->isAtivo()) {
                    return false;
                }
                if ($q !== '' && !str_contains(mb_strtolower($pacote->getNome()), mb_strtolower($q))) {
                    return false;
                }

                return true;
            },
        ));
        $ativos = \count(array_filter($all, static fn (ClinicPacote $p) => $p->isAtivo()));

        return $this->render('modules/pos-operatorio/pacotes/index.html.twig', [
            'empresa' => $empresa,
            'pos_section' => 'pacotes',
            'pacotes' => $pacotes,
            'procedimentos' => $this->procedimentos->list($empresa, true),
            'filter_ativo' => $ativo,
            'filter_q' => $q,
            'pacote_counts' => [
                'total' => \count($all),
                'ativos' => $ativos,
                'inativos' => \count($all) - $ativos,
            ],
        ]);
    }

    #[Route('/{id}', name: 'app_pos_operatorio_pacotes_editar', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function editar(int $id, Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $pacote = $this->pacotes->findForEmpresa($empresa, $id);
        if ($pacote === null) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('clinic_pacote_'.$id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token inválido.');

            return $this->redirectToRoute('app_pos_operatorio_pacotes');
        }

        try {
            $this->pacotes->update($pacote, $empresa, $this->payload($request, false));
            $this->addFlash('success', 'Pacote atualizado.');
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_pos_operatorio_pacotes');
    }

    /** @return array<string, mixed> */
    private function payload(Request $request, bool $creating): array
    {
        return [
            'nome' => $request->request->getString('nome'),
            'descricao' => $request->request->getString('descricao'),
            'valor' => $request->request->getString('valor'),
            'itens' => $request->request->getString('itens'),
            'ativo' => $creating ? true : $request->request->getBoolean('ativo'),
        ];
    }

    private function requireEmpresa(): Empresa
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        $empresa = $this->workspace->getActiveEmpresa($user) ?? $user->getEmpresa();
        if ($empresa === null) {
            throw $this->createAccessDeniedException('Área de trabalho indisponível.');
        }

        return $empresa;
    }
}
