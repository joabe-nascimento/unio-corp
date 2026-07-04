<?php

namespace App\Controller\Module\Admin;

use App\Entity\Empresa;
use App\Entity\PlatformAuditLog;
use App\Entity\User;
use App\Entity\UserProductGrant;
use App\Repository\EmpresaRepository;
use App\Repository\UserProductGrantRepository;
use App\Repository\UserRepository;
use App\Service\OnboardingProgressService;
use App\Service\PermissionService;
use App\Service\PlatformConfigService;
use App\Service\Platform\PlatformAuditService;
use App\Service\SvgAssetBundlingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_TENANT')]
class AdminController extends AbstractController
{
    private const T = 'modules/admin/';

    private const PERFIS = [
        'MEMBRO'            => 'Membro',
        'SUPERVISOR_EQUIPE' => 'Supervisor de Equipe',
        'SUPERVISOR'        => 'Supervisor Geral',
        'GESTOR_EQUIPE'     => 'Gestor de Equipe',
        'GESTOR'            => 'Gestor',
        'TENANT'            => 'Tenant',
    ];

    /** @var list<string> */
    private const IMAGE_URL_FIELDS = ['logo_url', 'logo_mark_url', 'logo_full_url', 'favicon_url'];

    public function __construct(
        private UserRepository $userRepo,
        private EmpresaRepository $empresaRepo,
        private UserProductGrantRepository $grantRepo,
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
        private PlatformConfigService $platformConfig,
        private SvgAssetBundlingService $svgAssetBundling,
        private PlatformAuditService $platformAudit,
    ) {}

    // ────────────────────────── INDEX ──────────────────────────────────

    #[Route('', name: 'app_admin')]
    public function index(): Response
    {
        return $this->render(self::T . 'index.html.twig', [
            'total_usuarios' => $this->userRepo->count([]),
            'total_ativos'   => $this->userRepo->count(['ativo' => true]),
            'total_empresas' => $this->empresaRepo->count([]),
        ]);
    }

    // ────────────────────────── USUÁRIOS ──────────────────────────────

    #[Route('/usuarios', name: 'app_admin_usuarios')]
    public function usuarios(Request $request): Response
    {
        $busca      = trim((string) $request->query->get('q', ''));
        $perfil     = (string) $request->query->get('perfil', '');
        $status     = (string) $request->query->get('status', '');
        $empresaFiltro = (string) $request->query->get('empresa_id', '');

        $qb = $this->userRepo->createQueryBuilder('u')
            ->leftJoin('u.empresa', 'e')
            ->addSelect('e')
            ->orderBy('u.nome', 'ASC');

        if ($busca !== '') {
            $qb->andWhere('u.nome LIKE :q OR u.email LIKE :q')
               ->setParameter('q', '%' . $busca . '%');
        }
        if ($perfil !== '') {
            $qb->andWhere('u.perfil = :perfil')->setParameter('perfil', $perfil);
        }
        if ($status === 'ativo') {
            $qb->andWhere('u.ativo = true');
        } elseif ($status === 'inativo') {
            $qb->andWhere('u.ativo = false');
        }
        if ($empresaFiltro !== '') {
            $qb->andWhere('u.empresa = :emp')->setParameter('emp', (int) $empresaFiltro);
        }

        $usuarios = $qb->getQuery()->getResult();
        $empresas = $this->empresaRepo->findBy([], ['nome' => 'ASC']);

        $kpi = [
            'total'   => $this->userRepo->count([]),
            'ativos'  => $this->userRepo->count(['ativo' => true]),
            'inativos'=> $this->userRepo->count(['ativo' => false]),
            'tenants' => count($this->userRepo->findBy(['perfil' => 'TENANT'])),
            'platform_owners' => count($this->userRepo->findBy(['perfil' => 'PLATFORM_OWNER'])),
        ];

        return $this->render(self::T . 'usuarios.html.twig', [
            'usuarios'        => $usuarios,
            'empresas'        => $empresas,
            'perfis'          => self::PERFIS,
            'busca'           => $busca,
            'filtro_perfil'   => $perfil,
            'filtro_status'   => $status,
            'filtro_empresa'  => $empresaFiltro,
            'kpi'             => $kpi,
            'scopes'          => PermissionService::SCOPES,
            'grant_profiles'  => PermissionService::ASSIGNABLE_PROFILES,
            'csrf_acao'       => $this->container->get('security.csrf.token_manager')
                                     ->getToken('admin_usuario_acao')->getValue(),
        ]);
    }

    #[Route('/usuarios/{id}/grants', name: 'app_admin_usuario_grants', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function usuarioGrants(int $id): JsonResponse
    {
        $user = $this->userRepo->find($id);
        if (!$user) {
            return new JsonResponse([], 404);
        }

        $result = [];
        foreach ($user->getProductGrants() as $grant) {
            $result[$grant->getGrantKey()] = $grant->getPerfilGrant();
        }

        return new JsonResponse($result);
    }

    #[Route('/usuarios/novo', name: 'app_admin_usuario_novo', methods: ['POST'])]
    public function novoUsuario(Request $request, OnboardingProgressService $onboarding): Response
    {
        if (!$this->isCsrfTokenValid('admin_usuario_form', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token inválido.');
            return $this->redirectToRoute('app_admin_usuarios');
        }

        $email  = mb_strtolower(trim((string) $request->request->get('email', '')));
        $nome   = trim((string) $request->request->get('nome', ''));
        $perfil = (string) $request->request->get('perfil', 'MEMBRO');
        $senha  = (string) $request->request->get('senha', '');

        if ($email === '' || $nome === '') {
            $this->addFlash('error', 'Nome e e-mail são obrigatórios.');
            return $this->redirectToRoute('app_admin_usuarios', ['open_novo' => 1]);
        }
        if (!array_key_exists($perfil, self::PERFIS)) {
            $perfil = 'MEMBRO';
        }
        if ($this->userRepo->findOneBy(['email' => $email])) {
            $this->platformAudit->record(
                PlatformAuditLog::CATEGORY_ADMIN,
                PlatformAuditLog::ACTION_CREATE,
                PlatformAuditLog::OUTCOME_FAILURE,
                'Falha ao criar usuário: e-mail já existe',
                $this->getUser() instanceof User ? $this->getUser() : null,
                null,
                'user',
                null,
                $email,
                $request,
            );
            $this->addFlash('error', 'Já existe um usuário com esse e-mail.');
            return $this->redirectToRoute('app_admin_usuarios', ['open_novo' => 1]);
        }
        if ($pwdErr = $this->platformConfig->validatePassword($senha)) {
            $this->addFlash('error', $pwdErr);
            return $this->redirectToRoute('app_admin_usuarios', ['open_novo' => 1]);
        }

        $empresaId = (int) $request->request->get('empresa_id', 0);
        $empresa   = $empresaId > 0 ? $this->empresaRepo->find($empresaId) : null;

        $user = (new User())
            ->setEmail($email)
            ->setNome($nome)
            ->setPerfil($perfil)
            ->setRoles([$this->rolePorPerfil($perfil)])
            ->setAtivo(true);

        if ($empresa) {
            $user->setEmpresa($empresa);
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $senha));
        $this->em->persist($user);
        $this->em->flush();

        $this->platformAudit->record(
            PlatformAuditLog::CATEGORY_ADMIN,
            PlatformAuditLog::ACTION_CREATE,
            PlatformAuditLog::OUTCOME_SUCCESS,
            sprintf('Usuário criado: %s (%s)', $nome, $email),
            $this->getUser() instanceof User ? $this->getUser() : null,
            null,
            'user',
            $user->getId(),
            $nome,
            $request,
            ['perfil' => $perfil, 'empresa_id' => $empresa?->getId()],
        );

        $onboarding->markStepComplete('invite_user');

        $this->addFlash('success', "Usu\u{00E1}rio \"{$nome}\" criado com sucesso.");
        return $this->redirectToRoute('app_admin_usuarios');
    }

    #[Route('/usuarios/{id}/editar', name: 'app_admin_usuario_editar', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function editarUsuario(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin_usuario_form', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token inválido.');
            return $this->redirectToRoute('app_admin_usuarios');
        }

        $user = $this->userRepo->find($id);
        if (!$user) {
            $this->addFlash('error', 'Usuário não encontrado.');
            return $this->redirectToRoute('app_admin_usuarios');
        }

        /** @var User $me */
        $me = $this->getUser();
        if ($user->isPlatformOwner() && !$me->isPlatformOwner()) {
            $this->addFlash('error', 'A conta do dono da plataforma não pode ser alterada por outro administrador.');
            return $this->redirectToRoute('app_admin_usuarios');
        }

        $nome   = trim((string) $request->request->get('nome', ''));
        $perfil = (string) $request->request->get('perfil', $user->getPerfil());

        if ($nome !== '') {
            $user->setNome($nome);
        }
        if (array_key_exists($perfil, self::PERFIS)) {
            $user->setPerfil($perfil);
            $user->setRoles([$this->rolePorPerfil($perfil)]);
        }

        $empresaId = $request->request->get('empresa_id');
        if ($empresaId !== null) {
            $empresa = (int) $empresaId > 0 ? $this->empresaRepo->find((int) $empresaId) : null;
            $user->setEmpresa($empresa);
        }

        $senha  = (string) $request->request->get('senha', '');
        $senha2 = (string) $request->request->get('senha2', '');
        if ($senha !== '') {
            if ($senha !== $senha2) {
                $this->addFlash('error', 'As senhas não conferem.');
                return $this->redirectToRoute('app_admin_usuarios');
            }
            if ($pwdErr = $this->platformConfig->validatePassword($senha)) {
                $this->addFlash('error', $pwdErr);
                return $this->redirectToRoute('app_admin_usuarios');
            }
            $user->setPassword($this->passwordHasher->hashPassword($user, $senha));
        }

        /** @var User $me */
        $me = $this->getUser();
        if ($request->request->has('ativo') && $user->getId() !== $me->getId()) {
            $user->setAtivo($request->request->getBoolean('ativo'));
        }

        // ── Grants de módulos ──
        $grantInput = $request->request->all('grant');
        if (is_array($grantInput)) {
            $this->syncGrants($user, $grantInput);
        }

        $this->em->flush();

        $this->platformAudit->record(
            PlatformAuditLog::CATEGORY_ADMIN,
            PlatformAuditLog::ACTION_UPDATE,
            PlatformAuditLog::OUTCOME_SUCCESS,
            sprintf('Usuário editado: %s', $user->getEmail()),
            $me,
            null,
            'user',
            $user->getId(),
            $user->getNome(),
            $request,
            ['perfil' => $user->getPerfil(), 'ativo' => $user->isAtivo()],
        );

        $this->addFlash('success', 'Usuário atualizado com sucesso.');
        return $this->redirectToRoute('app_admin_usuarios');
    }

    #[Route('/usuarios/{id}/acao', name: 'app_admin_usuario_acao', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function acaoUsuario(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin_usuario_acao', (string) $request->request->get('_token'))) {
            return new JsonResponse(['ok' => false, 'error' => 'csrf'], 403);
        }

        $user = $this->userRepo->find($id);
        if (!$user) {
            return new JsonResponse(['ok' => false, 'error' => 'not_found'], 404);
        }

        /** @var User $me */
        $me = $this->getUser();
        if ($user->getId() === $me->getId()) {
            return new JsonResponse(['ok' => false, 'error' => 'self'], 400);
        }
        if ($user->isPlatformOwner()) {
            return new JsonResponse(['ok' => false, 'error' => 'protected'], 403);
        }

        $acao = (string) $request->request->get('acao', '');
        if ($acao === 'desativar') {
            $user->setAtivo(false);
        } elseif ($acao === 'ativar') {
            $user->setAtivo(true);
        }

        $this->em->flush();

        $this->platformAudit->record(
            PlatformAuditLog::CATEGORY_ADMIN,
            $user->isAtivo() ? PlatformAuditLog::ACTION_ACTIVATE : PlatformAuditLog::ACTION_DEACTIVATE,
            PlatformAuditLog::OUTCOME_SUCCESS,
            sprintf('Usuário %s: %s', $user->isAtivo() ? 'ativado' : 'desativado', $user->getEmail()),
            $me,
            null,
            'user',
            $user->getId(),
            $user->getNome(),
            $request,
        );

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['ok' => true, 'ativo' => $user->isAtivo()]);
        }
        $this->addFlash('success', 'Usuário atualizado.');
        return $this->redirectToRoute('app_admin_usuarios');
    }

    // ────────────────────────── EMPRESAS ───────────────────────────────

    #[Route('/empresas', name: 'app_admin_empresas')]
    public function empresas(Request $request): Response
    {
        $busca = trim((string) $request->query->get('q', ''));

        $qb = $this->empresaRepo->createQueryBuilder('e')
            ->orderBy('e.nome', 'ASC');

        if ($busca !== '') {
            $qb->andWhere('e.nome LIKE :q OR e.cnpj LIKE :q OR e.setor LIKE :q')
               ->setParameter('q', '%' . $busca . '%');
        }

        $empresas = $qb->getQuery()->getResult();

        return $this->render(self::T . 'empresas.html.twig', [
            'empresas'  => $empresas,
            'busca'     => $busca,
            'csrf_acao' => $this->container->get('security.csrf.token_manager')
                               ->getToken('admin_empresa_acao')->getValue(),
            'csrf_form' => $this->container->get('security.csrf.token_manager')
                               ->getToken('admin_empresa_form')->getValue(),
        ]);
    }

    #[Route('/empresas/nova', name: 'app_admin_empresa_nova', methods: ['POST'])]
    public function novaEmpresa(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin_empresa_form', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token inválido.');
            return $this->redirectToRoute('app_admin_empresas');
        }

        $nome  = trim((string) $request->request->get('nome', ''));
        $cnpj  = trim((string) $request->request->get('cnpj', ''));
        $setor = trim((string) $request->request->get('setor', ''));

        if ($nome === '' || $cnpj === '') {
            $this->addFlash('error', 'Nome e CNPJ são obrigatórios.');
            return $this->redirectToRoute('app_admin_empresas', ['open_novo' => 1]);
        }
        if ($this->empresaRepo->findOneBy(['cnpj' => $cnpj])) {
            $this->addFlash('error', 'Já existe uma empresa com esse CNPJ.');
            return $this->redirectToRoute('app_admin_empresas', ['open_novo' => 1]);
        }

        $empresa = (new Empresa())
            ->setNome($nome)
            ->setCnpj($cnpj)
            ->setSetor($setor ?: null)
            ->setAtivo(true);

        $logo = trim((string) $request->request->get('logo', ''));
        if ($logo !== '') {
            $empresa->setLogo($logo);
        }

        $this->em->persist($empresa);
        $this->em->flush();

        $actor = $this->getUser();
        $this->platformAudit->record(
            PlatformAuditLog::CATEGORY_ADMIN,
            PlatformAuditLog::ACTION_CREATE,
            PlatformAuditLog::OUTCOME_SUCCESS,
            sprintf('Empresa criada: %s', $nome),
            $actor instanceof User ? $actor : null,
            null,
            'empresa',
            $empresa->getId(),
            $nome,
            $request,
            ['cnpj' => $cnpj],
        );

        $this->addFlash('success', "Empresa \"{$nome}\" criada com sucesso.");
        return $this->redirectToRoute('app_admin_empresas');
    }

    #[Route('/empresas/{id}/editar', name: 'app_admin_empresa_editar', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function editarEmpresa(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin_empresa_form', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token inválido.');
            return $this->redirectToRoute('app_admin_empresas');
        }

        $empresa = $this->empresaRepo->find($id);
        if (!$empresa) {
            $this->addFlash('error', 'Empresa não encontrada.');
            return $this->redirectToRoute('app_admin_empresas');
        }

        $nome  = trim((string) $request->request->get('nome', ''));
        $setor = trim((string) $request->request->get('setor', ''));
        $cnpj  = trim((string) $request->request->get('cnpj', ''));
        $logo  = trim((string) $request->request->get('logo', ''));

        if ($nome !== '') {
            $empresa->setNome($nome);
        }
        $empresa->setSetor($setor ?: null);

        if ($cnpj !== '' && $cnpj !== $empresa->getCnpj()) {
            $existing = $this->empresaRepo->findOneBy(['cnpj' => $cnpj]);
            if ($existing && $existing->getId() !== $empresa->getId()) {
                $this->addFlash('error', 'Já existe outra empresa com esse CNPJ.');
                return $this->redirectToRoute('app_admin_empresas');
            }
            $empresa->setCnpj($cnpj);
        }

        if ($logo !== '') {
            $empresa->setLogo($logo);
        }

        if ($request->request->has('ativo')) {
            $empresa->setAtivo($request->request->getBoolean('ativo'));
        }

        $this->em->flush();

        $actor = $this->getUser();
        $this->platformAudit->record(
            PlatformAuditLog::CATEGORY_ADMIN,
            PlatformAuditLog::ACTION_UPDATE,
            PlatformAuditLog::OUTCOME_SUCCESS,
            sprintf('Empresa editada: %s', $empresa->getNome()),
            $actor instanceof User ? $actor : null,
            null,
            'empresa',
            $empresa->getId(),
            $empresa->getNome(),
            $request,
            ['ativo' => $empresa->isAtivo()],
        );

        $this->addFlash('success', 'Empresa atualizada com sucesso.');
        return $this->redirectToRoute('app_admin_empresas');
    }

    #[Route('/empresas/{id}/acao', name: 'app_admin_empresa_acao', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function acaoEmpresa(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin_empresa_acao', (string) $request->request->get('_token'))) {
            return new JsonResponse(['ok' => false, 'error' => 'csrf'], 403);
        }

        $empresa = $this->empresaRepo->find($id);
        if (!$empresa) {
            return new JsonResponse(['ok' => false, 'error' => 'not_found'], 404);
        }

        $acao = (string) $request->request->get('acao', '');
        if ($acao === 'desativar') {
            $empresa->setAtivo(false);
        } elseif ($acao === 'ativar') {
            $empresa->setAtivo(true);
        }

        $this->em->flush();

        $actor = $this->getUser();
        $this->platformAudit->record(
            PlatformAuditLog::CATEGORY_ADMIN,
            $empresa->isAtivo() ? PlatformAuditLog::ACTION_ACTIVATE : PlatformAuditLog::ACTION_DEACTIVATE,
            PlatformAuditLog::OUTCOME_SUCCESS,
            sprintf('Empresa %s: %s', $empresa->isAtivo() ? 'ativada' : 'desativada', $empresa->getNome()),
            $actor instanceof User ? $actor : null,
            null,
            'empresa',
            $empresa->getId(),
            $empresa->getNome(),
            $request,
        );

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['ok' => true, 'ativo' => $empresa->isAtivo()]);
        }
        $this->addFlash('success', 'Empresa atualizada.');
        return $this->redirectToRoute('app_admin_empresas');
    }

    // ────────────────────────── CONFIGURAÇÕES ──────────────────────────

    #[Route('/configuracoes', name: 'app_admin_configuracoes', methods: ['GET', 'POST'])]
    public function configuracoes(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $token = (string) $request->request->get('_token');
            if (!$this->isCsrfTokenValid('admin_config', $token)) {
                $contentLength = (int) $request->headers->get('Content-Length', 0);
                if ($contentLength > 0 && $token === '' && $request->request->count() === 0) {
                    $this->addFlash('error', sprintf(
                        'O envio excedeu o limite do PHP (post_max_size: %s). Reduza o tamanho dos arquivos ou aumente o limite no php.ini.',
                        ini_get('post_max_size') ?: '8M'
                    ));
                } else {
                    $this->addFlash('error', 'Sessão expirada ou token inválido. Recarregue a página e tente salvar novamente.');
                }

                return $this->redirectToRoute('app_admin_configuracoes');
            }

            $fields = [
                'plataforma_nome', 'plataforma_tagline', 'logo_url', 'logo_mark_url', 'logo_full_url', 'favicon_url',
                'cor_primaria', 'tema',
                'suporte_email', 'suporte_telefone', 'website', 'rodape_texto',
                'operadora_razao_social', 'operadora_nome_fantasia', 'operadora_cnpj', 'operadora_endereco',
                'encarregado_dados_nome', 'encarregado_dados_email', 'legal_ultima_atualizacao',
                'msg_manutencao',
            ];
            $newConfig = [];
            $hadAssetErrors = false;

            // ── File uploads (logo, favicon, sidebar logos) ──
            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/config';
            @mkdir($uploadDir, 0777, true);

            foreach (['logo_file' => 'logo_url', 'favicon_file' => 'favicon_url', 'logo_mark_file' => 'logo_mark_url', 'logo_full_file' => 'logo_full_url'] as $inputName => $cfgKey) {
                $file = $request->files->get($inputName);
                if (!$file instanceof UploadedFile) {
                    continue;
                }

                if (!$file->isValid()) {
                    if (\in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                    $label = match($inputName) {
                        'logo_file'      => 'do logotipo',
                        'favicon_file'   => 'do favicon',
                        'logo_mark_file' => 'do ícone da sidebar',
                        'logo_full_file' => 'do logotipo completo da sidebar',
                        default          => 'do arquivo',
                    };
                        $this->addFlash('error', sprintf(
                            'Arquivo %s excede o limite de upload do PHP (%s).',
                            $label,
                            ini_get('upload_max_filesize') ?: '2M'
                        ));
                        $hadAssetErrors = true;
                    }
                    continue;
                }

                $maxBytes = $this->phpIniBytes('upload_max_filesize');
                if ($file->getSize() > $maxBytes) {
                    $this->addFlash('error', sprintf(
                        'Arquivo %s excede o limite de upload do PHP (%s).',
                        match($inputName) {
                            'logo_file'      => 'do logotipo',
                            'favicon_file'   => 'do favicon',
                            'logo_mark_file' => 'do ícone da sidebar',
                            'logo_full_file' => 'do logotipo completo da sidebar',
                            default          => 'do arquivo',
                        },
                        ini_get('upload_max_filesize') ?: '2M'
                    ));
                    $hadAssetErrors = true;
                    continue;
                }

                $ext      = $this->resolveUploadExtension($file);
                $baseName = match($cfgKey) {
                    'logo_url'      => 'logo',
                    'favicon_url'   => 'favicon',
                    'logo_mark_url' => 'logo-mark',
                    'logo_full_url' => 'logo-full',
                    default         => 'upload',
                };
                $safeName = $baseName . '-' . uniqid() . '.' . $ext;
                try {
                    $file->move($uploadDir, $safeName);
                    $movedPath = $uploadDir . '/' . $safeName;
                    if (!$this->isValidAssetFile($movedPath)) {
                        @unlink($movedPath);
                        $this->addFlash('error', sprintf(
                            'Arquivo %s inválido ou muito pequeno. Envie uma imagem real (mín. 8×8 px).',
                            match($inputName) {
                                'logo_file'      => 'do logotipo',
                                'favicon_file'   => 'do favicon',
                                'logo_mark_file' => 'do ícone da sidebar',
                                'logo_full_file' => 'do logotipo completo da sidebar',
                                default          => 'enviado',
                            }
                        ));
                        $hadAssetErrors = true;
                        continue;
                    }

                    if (str_ends_with(strtolower($movedPath), '.svg')) {
                        $this->svgAssetBundling->bundleExternalAssets(
                            $movedPath,
                            $this->getParameter('kernel.project_dir') . '/public'
                        );
                    }

                    $old = $this->platformConfig->get($cfgKey, '');
                    if (str_starts_with((string) $old, '/uploads/config/')) {
                        $oldPath = $this->getParameter('kernel.project_dir') . '/public' . $old;
                        if (is_file($oldPath)) {
                            @unlink($oldPath);
                        }
                    }
                    $newConfig[$cfgKey] = '/uploads/config/' . $safeName;
                } catch (FileException) {
                    $this->addFlash('warning', "Não foi possível salvar o arquivo {$inputName}.");
                    $hadAssetErrors = true;
                }
            }

            foreach ($fields as $f) {
                if (\in_array($f, self::IMAGE_URL_FIELDS, true)) {
                    // Upload neste request tem prioridade sobre _clear e URL vazia
                    if (isset($newConfig[$f])) {
                        continue;
                    }
                    if ($request->request->getBoolean($f . '_clear')) {
                        $newConfig[$f] = '';
                        continue;
                    }
                    $value = trim((string) $request->request->get($f, ''));
                    if ($value !== '') {
                        $newConfig[$f] = $value;
                    }
                    continue;
                }

                $newConfig[$f] = trim((string) $request->request->get($f, ''));
            }

            $bools = ['manutencao', 'senha_maiuscula', 'senha_numero', 'registro_publico'];
            foreach ($bools as $b) {
                $newConfig[$b] = $request->request->has($b);
            }

            $newConfig['senha_min']      = max(6, min(32, (int) $request->request->get('senha_min', 8)));
            $newConfig['sessao_timeout'] = max(15, min(1440, (int) $request->request->get('sessao_timeout', 120)));

            $this->platformConfig->save($newConfig);

            $actor = $this->getUser();
            $this->platformAudit->record(
                PlatformAuditLog::CATEGORY_CONFIG,
                PlatformAuditLog::ACTION_SAVE,
                $hadAssetErrors ? PlatformAuditLog::OUTCOME_WARNING : PlatformAuditLog::OUTCOME_SUCCESS,
                'Configurações da plataforma salvas',
                $actor instanceof User ? $actor : null,
                null,
                'config',
                null,
                'platform_config',
                $request,
                ['manutencao' => $request->request->has('manutencao')],
            );

            if ($hadAssetErrors) {
                $this->addFlash('warning', 'Algumas configurações foram salvas, mas houve problemas com um ou mais arquivos de imagem.');
            } else {
                $this->addFlash('success', "Configura\u{00E7}\u{00F5}es salvas com sucesso.");
            }
            return $this->redirectToRoute('app_admin_configuracoes', ['saved' => 1]);
        }

        $config = $this->platformConfig->all();
        $config['mem_limit']  = @ini_get('memory_limit') ?: '—';
        $config['upload_max'] = @ini_get('upload_max_filesize') ?: '—';
        $config['post_max']   = @ini_get('post_max_size') ?: '—';

        return $this->render(self::T . 'configuracoes.html.twig', [
            'config' => $config,
        ]);
    }

    #[Route('/configuracoes/limpar-cache', name: 'app_admin_configuracoes_clear_cache', methods: ['POST'])]
    public function clearConfigCache(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin_clear_cache', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token inválido.');
            return $this->redirectToRoute('app_admin_configuracoes', ['_fragment' => 'cfg-sistema']);
        }

        try {
            $this->clearAppCache();
            $this->addFlash('success', 'Cache limpo com sucesso.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Não foi possível limpar o cache: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_admin_configuracoes', ['_fragment' => 'cfg-sistema']);
    }

    // ────────────────────────── Helpers ────────────────────────────────

    private function syncGrants(User $user, array $input): void
    {
        // index existing grants by key for quick lookup
        $existing = [];
        foreach ($user->getProductGrants() as $grant) {
            $existing[$grant->getGrantKey()] = $grant;
        }

        foreach ($input as $scopeId => $products) {
            if (!is_array($products)) {
                continue;
            }
            foreach ($products as $productId => $perfilGrant) {
                $perfilGrant = trim((string) $perfilGrant);
                $key = $scopeId . ':' . $productId;

                if ($perfilGrant === '') {
                    // Remove grant if it exists
                    if (isset($existing[$key])) {
                        $user->removeProductGrant($existing[$key]);
                        $this->em->remove($existing[$key]);
                    }
                    continue;
                }

                if (isset($existing[$key])) {
                    $existing[$key]->setPerfilGrant($perfilGrant);
                } else {
                    $grant = (new UserProductGrant())
                        ->setUser($user)
                        ->setScope($scopeId)
                        ->setProductId($productId)
                        ->setPerfilGrant($perfilGrant);
                    $this->em->persist($grant);
                    $user->addProductGrant($grant);
                }
            }
        }
    }

    private function resolveUploadExtension(UploadedFile $file): string
    {
        $allowed = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'ico'];
        $ext     = strtolower(ltrim((string) $file->getClientOriginalExtension(), '.'));

        if (!\in_array($ext, $allowed, true)) {
            $name = strtolower((string) $file->getClientOriginalName());
            $ext  = match (true) {
                str_ends_with($name, '.svg')  => 'svg',
                str_ends_with($name, '.webp') => 'webp',
                str_ends_with($name, '.ico')  => 'ico',
                str_ends_with($name, '.gif')  => 'gif',
                str_ends_with($name, '.jpg'), str_ends_with($name, '.jpeg') => 'jpg',
                default => 'png',
            };
        }

        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }

        return preg_match('/^[a-z0-9]{2,5}$/', $ext) ? $ext : 'png';
    }

    private function isValidAssetFile(string $path): bool
    {
        if (!is_file($path)) {
            return false;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $minBytes = $ext === 'svg' ? 80 : 200;
        if (filesize($path) < $minBytes) {
            return false;
        }

        if ($ext === 'svg') {
            return true;
        }

        $info = @getimagesize($path);
        if ($info === false) {
            return $ext === 'ico';
        }

        return $info[0] >= 8 && $info[1] >= 8;
    }

    private function clearAppCache(): void
    {
        $projectDir = $this->getParameter('kernel.project_dir');
        $process = new Process(
            [\PHP_BINARY, 'bin/console', 'cache:clear', '--no-warmup'],
            $projectDir,
            null,
            null,
            120
        );
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput()));
        }
    }

    private function phpIniBytes(string $iniKey): int
    {
        $value = ini_get($iniKey);
        if ($value === false || $value === '') {
            return 2 * 1024 * 1024;
        }

        $value = trim((string) $value);
        $unit  = strtolower(substr($value, -1));
        $num   = (float) $value;

        return match ($unit) {
            'g' => (int) ($num * 1024 * 1024 * 1024),
            'm' => (int) ($num * 1024 * 1024),
            'k' => (int) ($num * 1024),
            default => (int) $num,
        };
    }

    private function rolePorPerfil(string $perfil): string
    {
        return match ($perfil) {
            'PLATFORM_OWNER'    => User::ROLE_PLATFORM_OWNER,
            'TENANT'            => User::ROLE_TENANT,
            'GESTOR'            => User::ROLE_GESTOR,
            'GESTOR_EQUIPE'     => User::ROLE_GESTOR_EQUIPE,
            'SUPERVISOR'        => User::ROLE_SUPERVISOR,
            'SUPERVISOR_EQUIPE' => User::ROLE_SUPERVISOR_EQUIPE,
            default             => User::ROLE_MEMBRO,
        };
    }
}
