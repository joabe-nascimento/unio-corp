<?php

namespace App\Controller\Module\Recrutamento;

use App\Controller\Module\Rh\RhEmpresaScopeTrait;
use App\Entity\RhCandidatoAprovacao;
use App\Entity\RhTalentoPool;
use App\Entity\RhVaga;
use App\Entity\User;
use App\Exception\RhProcessException;
use App\Repository\RhCandidatoAprovacaoRepository;
use App\Repository\RhCandidatoRepository;
use App\Repository\RhEmailEventRepository;
use App\Repository\RhTalentoPoolRepository;
use App\Repository\RhVagaRepository;
use App\Repository\UserRepository;
use App\Rh\RhCandidatoEtapa;
use App\Rh\RhEntrevistaTipo;
use App\Security\ProductGrantAccess;
use App\Service\Rh\RhCandidatoAttachmentService;
use App\Service\Rh\RhCarreirasService;
use App\Service\Rh\RhRecruitmentExtendedService;
use App\Service\Rh\RhTalentoPoolService;
use App\Service\WorkspaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/recrutamento')]
#[IsGranted('ROLE_USER')]
class RecrutamentoExtendedController extends AbstractController
{
    use RhEmpresaScopeTrait;

    private const T = 'modules/recrutamento/';

    public function __construct(
        private WorkspaceService $workspace,
        private RhCarreirasService $carreiras,
        private RhTalentoPoolService $talentos,
        private RhRecruitmentExtendedService $extended,
        private RhCandidatoAttachmentService $attachments,
        private RhVagaRepository $vagaRepo,
        private RhCandidatoRepository $candidatoRepo,
        private RhTalentoPoolRepository $poolRepo,
        private RhCandidatoAprovacaoRepository $aprovacaoRepo,
        private RhEmailEventRepository $emailEventRepo,
        private UserRepository $userRepo,
        private ProductGrantAccess $grants,
    ) {}

    protected function getWorkspace(): WorkspaceService
    {
        return $this->workspace;
    }

    #[Route('/carreiras', name: 'app_recrutamento_carreiras', methods: ['GET', 'POST'])]
    public function carreirasConfig(Request $request): Response
    {
        $empresa = $this->requireEmpresa();

        if ($request->isMethod('POST')) {
            $this->denyUnlessCanManageVagas();
            try {
                $this->requireCsrf($request, 'recrutamento_carreiras');
                $this->carreiras->updateCarreirasConfig(
                    $empresa,
                    $request->request->getBoolean('carreiras_ativo'),
                    (string) $request->request->get('carreiras_titulo', ''),
                    (string) $request->request->get('carreiras_descricao', ''),
                    (string) $request->request->get('slug', ''),
                );
                $this->addFlash('success', 'Página de carreiras atualizada.');
            } catch (RhProcessException $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('app_recrutamento_carreiras');
        }

        return $this->render(self::T . 'carreiras_config.html.twig', [
            'empresa' => $empresa,
            'public_url' => $this->carreiras->publicUrl($empresa),
            'vagas_publicadas' => $this->vagaRepo->findPublicadasForEmpresa($empresa),
            'vagas_publicadas_count' => $this->vagaRepo->countPublicadasByEmpresa($empresa),
            'vagas_abertas_count' => $this->vagaRepo->countAbertasByEmpresa($empresa),
        ]);
    }

    #[Route('/vagas/{id}/publicar', name: 'app_recrutamento_vaga_publicar', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function publicarVaga(int $id, Request $request): Response
    {
        $this->denyUnlessCanManageVagas();
        $empresa = $this->requireEmpresa();
        $vaga = $this->vagaRepo->findOneForEmpresa($id, $empresa);
        if (!$vaga) {
            throw $this->createNotFoundException();
        }

        try {
            $this->requireCsrf($request, 'recrutamento_vaga_publicar_' . $id);
            if (!$empresa->isCarreirasAtivo()) {
                throw new RhProcessException('Ative a página de carreiras antes de publicar vagas.');
            }
            $this->carreiras->ensureEmpresaSlug($empresa);
            $this->carreiras->publishVaga($vaga);
            $this->addFlash('success', 'Vaga publicada na página de carreiras.');
        } catch (RhProcessException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_recrutamento_vagas_show', ['id' => $id]);
    }

    #[Route('/vagas/{id}/despublicar', name: 'app_recrutamento_vaga_despublicar', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function despublicarVaga(int $id, Request $request): Response
    {
        $this->denyUnlessCanManageVagas();
        $empresa = $this->requireEmpresa();
        $vaga = $this->vagaRepo->findOneForEmpresa($id, $empresa);
        if (!$vaga) {
            throw $this->createNotFoundException();
        }

        try {
            $this->requireCsrf($request, 'recrutamento_vaga_despublicar_' . $id);
            $this->carreiras->unpublishVaga($vaga);
            $this->addFlash('success', 'Vaga removida da página pública.');
        } catch (RhProcessException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_recrutamento_vagas_show', ['id' => $id]);
    }

    #[Route('/banco-talentos', name: 'app_recrutamento_talentos', methods: ['GET'])]
    public function bancoTalentos(Request $request): Response
    {
        $empresa = $this->requireEmpresa();
        $q = trim((string) $request->query->get('q', ''));

        return $this->render(self::T . 'talentos.html.twig', [
            'talentos' => $this->talentos->listForEmpresa($empresa, $q !== '' ? $q : null),
            'vagas_inscricao' => $this->vagaRepo->findInscritiveisForEmpresa($empresa),
            'vagas_abertas_count' => $this->vagaRepo->countAbertasByEmpresa($empresa),
            'filter_q' => $q,
            'talentos_count' => $this->poolRepo->countByEmpresa($empresa),
            'has_filters' => $q !== '',
        ]);
    }

    #[Route('/banco-talentos/{id}/inscrever', name: 'app_recrutamento_talento_inscrever', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function inscreverTalento(int $id, Request $request): Response
    {
        $this->denyUnlessCanManageVagas();
        $empresa = $this->requireEmpresa();
        $talento = $this->poolRepo->find($id);
        if (!$talento instanceof RhTalentoPool || $talento->getEmpresa()->getId() !== $empresa->getId()) {
            throw $this->createNotFoundException();
        }

        $vagaId = (int) $request->request->get('vaga_id', 0);
        $vaga = $this->vagaRepo->findOneForEmpresa($vagaId, $empresa);
        if (!$vaga) {
            $this->addFlash('error', 'Selecione uma vaga válida.');

            return $this->redirectToRoute('app_recrutamento_talentos');
        }
        if ($vaga->getStatus() === RhVaga::STATUS_FECHADA) {
            $this->addFlash('error', 'Esta vaga está fechada. Reabra-a antes de inscrever talentos.');

            return $this->redirectToRoute('app_recrutamento_talentos');
        }

        try {
            $this->requireCsrf($request, 'recrutamento_talento_inscrever_' . $id);
            /** @var User $user */
            $user = $this->getUser();
            $candidato = $this->talentos->inscreverEmVaga($talento, $vaga, $user);
            $this->addFlash('success', 'Talento inscrito na vaga. Candidato #' . $candidato->getId() . ' criado.');
        } catch (RhProcessException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_recrutamento_talentos');
    }

    #[Route('/integracoes', name: 'app_recrutamento_integracoes', methods: ['GET', 'POST'])]
    public function integracoes(Request $request): Response
    {
        $empresa = $this->requireEmpresa();

        if ($request->isMethod('POST')) {
            $this->denyUnlessCanManageVagas();
            try {
                $this->requireCsrf($request, 'recrutamento_integracoes');
                $this->extended->saveIntegracoes($empresa, $request->request->all());
                $this->addFlash('success', 'Integrações salvas.');
            } catch (RhProcessException $e) {
                $this->addFlash('error', $e->getMessage());
            }

            return $this->redirectToRoute('app_recrutamento_integracoes');
        }

        return $this->render(self::T . 'integracoes.html.twig', [
            'empresa' => $empresa,
            'integracoes' => $empresa->getRecruitmentIntegracoes() ?? [],
            'aprovacoes_pendentes' => $this->extended->listPendentes($empresa),
            'emails_pendentes' => $this->emailEventRepo->countPendentesByEmpresa($empresa),
        ]);
    }

    #[Route('/candidatos/{id}/entrevista', name: 'app_recrutamento_candidato_entrevista', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function agendarEntrevista(int $id, Request $request): Response
    {
        $this->denyUnlessCanManagePipeline();
        $empresa = $this->requireEmpresa();
        $candidato = $this->candidatoRepo->findOneForEmpresa($id, $empresa);
        if (!$candidato) {
            throw $this->createNotFoundException();
        }

        try {
            $this->requireCsrf($request, 'recrutamento_candidato_entrevista_' . $id);
            /** @var User $user */
            $user = $this->getUser();
            $raw = trim((string) $request->request->get('entrevista_em', ''));
            $when = $raw !== '' ? new \DateTimeImmutable($raw) : null;
            if ($when === null) {
                throw new RhProcessException('Informe data e hora da entrevista.');
            }
            $tipo = strtoupper(trim((string) $request->request->get('entrevista_tipo', RhEntrevistaTipo::ONLINE)));
            $entrevistadorId = (int) $request->request->get('entrevista_entrevistador', 0);
            $entrevistador = null;
            if ($entrevistadorId > 0) {
                $entrevistador = $this->userRepo->find($entrevistadorId);
                if (!$entrevistador || $entrevistador->getEmpresa()?->getId() !== $empresa->getId()) {
                    throw new RhProcessException('Entrevistador inválido.');
                }
            }
            $this->extended->scheduleEntrevista(
                $candidato,
                $when,
                (string) $request->request->get('entrevista_link', ''),
                $tipo,
                $entrevistador,
                $user,
            );
            $this->addFlash('success', 'Entrevista agendada e e-mail enviado para a fila.');
        } catch (\Throwable $e) {
            $this->addFlash('error', $e instanceof RhProcessException ? $e->getMessage() : 'Data/hora inválida.');
        }

        return $this->redirectToRoute('app_recrutamento_candidatos_show', ['id' => $id, 'tab' => 'entrevista']);
    }

    #[Route('/candidatos/{id}/scorecard', name: 'app_recrutamento_candidato_scorecard', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function scorecard(int $id, Request $request): Response
    {
        $this->denyUnlessCanManagePipeline();
        $empresa = $this->requireEmpresa();
        $candidato = $this->candidatoRepo->findOneForEmpresa($id, $empresa);
        if (!$candidato) {
            throw $this->createNotFoundException();
        }

        $etapa = (string) $request->request->get('etapa', $candidato->getEtapa());

        try {
            $this->requireCsrf($request, 'recrutamento_candidato_scorecard_' . $id);
            /** @var User $user */
            $user = $this->getUser();
            $scores = (array) $request->request->all('scores');
            $this->extended->saveScorecard(
                $candidato,
                $etapa,
                $scores,
                (string) $request->request->get('comentario', ''),
                $user,
            );
            $this->addFlash('success', 'Scorecard salvo.');
        } catch (RhProcessException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_recrutamento_candidatos_show', ['id' => $id]);
    }

    #[Route('/candidatos/{id}/curriculo', name: 'app_recrutamento_candidato_curriculo', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function uploadCurriculo(int $id, Request $request): Response
    {
        $this->denyUnlessCanManageVagas();
        $empresa = $this->requireEmpresa();
        $candidato = $this->candidatoRepo->findOneForEmpresa($id, $empresa);
        if (!$candidato) {
            throw $this->createNotFoundException();
        }

        try {
            $this->requireCsrf($request, 'recrutamento_candidato_curriculo_' . $id);
            /** @var User $user */
            $user = $this->getUser();
            $file = $request->files->get('curriculo');
            if (!$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                throw new RhProcessException('Selecione um arquivo PDF.');
            }
            $this->attachments->uploadCurriculo($candidato, $file, $user);
            $this->addFlash('success', 'Currículo anexado.');
        } catch (RhProcessException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_recrutamento_candidatos_show', ['id' => $id]);
    }

    #[Route('/candidatos/{id}/banco-talentos', name: 'app_recrutamento_candidato_banco_talentos', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function addBancoTalentos(int $id, Request $request): Response
    {
        $this->denyUnlessCanManageVagas();
        $empresa = $this->requireEmpresa();
        $candidato = $this->candidatoRepo->findOneForEmpresa($id, $empresa);
        if (!$candidato) {
            throw $this->createNotFoundException();
        }

        try {
            $this->requireCsrf($request, 'recrutamento_candidato_banco_' . $id);
            /** @var User $user */
            $user = $this->getUser();
            $this->talentos->addToPoolFromCandidato($candidato, $user);
            $this->addFlash('success', 'Candidato adicionado ao banco de talentos.');
        } catch (RhProcessException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_recrutamento_candidatos_show', ['id' => $id]);
    }

    #[Route('/aprovacoes/{id}/decidir', name: 'app_recrutamento_aprovacao_decidir', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function decidirAprovacao(int $id, Request $request): Response
    {
        $this->denyUnlessCanManagePipeline();
        $empresa = $this->requireEmpresa();
        $aprovacao = $this->aprovacaoRepo->find($id);
        if (!$aprovacao instanceof RhCandidatoAprovacao) {
            throw $this->createNotFoundException();
        }
        if ($aprovacao->getCandidato()->getVaga()->getEmpresa()->getId() !== $empresa->getId()) {
            throw $this->createNotFoundException();
        }

        try {
            $this->requireCsrf($request, 'recrutamento_aprovacao_' . $id);
            /** @var User $user */
            $user = $this->getUser();
            $aprovar = (string) $request->request->get('aprovar', '0') === '1';
            $candidato = $this->extended->decideApproval(
                $aprovacao,
                $aprovar,
                $user,
                (string) $request->request->get('comentario', ''),
            );
            $this->addFlash('success', $aprovar ? 'Aprovação concedida.' : 'Solicitação rejeitada.');
            $redirect = (string) $request->request->get('redirect_to', 'candidato');
            if ($redirect === 'integracoes') {
                return $this->redirectToRoute('app_recrutamento_integracoes');
            }

            return $this->redirectToRoute('app_recrutamento_candidatos_show', ['id' => $candidato->getId()]);
        } catch (RhProcessException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_recrutamento_integracoes');
    }

    private function denyUnlessCanManageVagas(): void
    {
        if (!$this->canManageVagas()) {
            throw $this->createAccessDeniedException();
        }
    }

    private function denyUnlessCanManagePipeline(): void
    {
        if (!$this->canManageVagas() && !$this->canManagePipeline()) {
            throw $this->createAccessDeniedException();
        }
    }

    private function canManageVagas(): bool
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return $this->grants->grantAtLeast($user, 'hub_recrutamento', 'vagas', 'GESTOR_EQUIPE')
            || $this->grants->grantAtLeast($user, 'product_rh', 'recrutamento', 'GESTOR_EQUIPE');
    }

    private function canManagePipeline(): bool
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return $this->grants->grantAtLeast($user, 'hub_recrutamento', 'pipeline', 'GESTOR_EQUIPE')
            || $this->grants->grantAtLeast($user, 'product_rh', 'recrutamento', 'GESTOR_EQUIPE');
    }
}
