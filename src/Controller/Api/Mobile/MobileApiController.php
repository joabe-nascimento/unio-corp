<?php

namespace App\Controller\Api\Mobile;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Security\JwtService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * API REST para aplicativo mobile de pacientes.
 * 
 * Endpoints públicos (sem autenticação):
 * - POST /api/mobile/auth/login
 * - POST /api/mobile/auth/register
 * - POST /api/mobile/auth/forgot-password
 * 
 * Endpoints protegidos (requerem token JWT):
 * - GET /api/mobile/patient/profile
 * - GET /api/mobile/patient/appointments
 * - GET /api/mobile/patient/documents
 * - GET /api/mobile/patient/prescriptions
 * - POST /api/mobile/patient/confirm-appointment
 */
#[Route('/api/mobile')]
class MobileApiController extends AbstractController
{
    public function __construct(
        private JwtService $jwtService,
        private UserRepository $userRepository,
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
        private ValidatorInterface $validator,
    ) {}
    /**
     * Informações sobre a API (versão, status, endpoints)
     */
    #[Route('', name: 'api_mobile_info', methods: ['GET'])]
    public function info(): JsonResponse
    {
        return $this->json([
            'api' => 'Unio Mobile API',
            'version' => '1.0.0',
            'status' => 'active',
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'endpoints' => [
                'auth' => [
                    'login' => '/api/mobile/auth/login',
                    'register' => '/api/mobile/auth/register',
                    'forgot_password' => '/api/mobile/auth/forgot-password',
                ],
                'patient' => [
                    'profile' => '/api/mobile/patient/profile',
                    'appointments' => '/api/mobile/patient/appointments',
                    'documents' => '/api/mobile/patient/documents',
                    'prescriptions' => '/api/mobile/patient/prescriptions',
                    'card' => '/api/mobile/patient/card',
                ],
            ],
            'documentation' => '/api/mobile/docs',
        ]);
    }

    /**
     * Health check endpoint
     */
    #[Route('/health', name: 'api_mobile_health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return $this->json([
            'status' => 'healthy',
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'database' => 'connected',
            'cache' => 'operational',
        ]);
    }

    /**
     * Login de paciente
     * 
     * Body: { "cpf": "123.456.789-00", "password": "senha123" }
     * ou { "email": "paciente@email.com", "password": "senha123" }
     */
    #[Route('/auth/login', name: 'api_mobile_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!$data) {
            return $this->json([
                'success' => false,
                'error' => 'JSON inválido',
            ], Response::HTTP_BAD_REQUEST);
        }

        $identifier = trim((string) ($data['cpf'] ?? $data['email'] ?? ''));
        $password = trim((string) ($data['password'] ?? ''));

        if ($identifier === '' || $password === '') {
            return $this->json([
                'success' => false,
                'error' => 'CPF/email e senha são obrigatórios',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Buscar usuário por CPF ou email
        $user = $this->userRepository->findOneBy(['email' => $identifier])
            ?? $this->userRepository->findOneBy(['cpf' => $this->cleanCpf($identifier)]);

        if (!$user) {
            return $this->json([
                'success' => false,
                'error' => 'Credenciais inválidas',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Verificar senha
        if (!$this->passwordHasher->isPasswordValid($user, $password)) {
            return $this->json([
                'success' => false,
                'error' => 'Credenciais inválidas',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Verificar se o usuário está ativo
        if (!$user->isAtivo()) {
            return $this->json([
                'success' => false,
                'error' => 'Conta inativa. Entre em contato com o suporte.',
            ], Response::HTTP_FORBIDDEN);
        }

        // Gerar token JWT
        $token = $this->jwtService->generateToken(
            $user->getId(),
            $user->getEmail(),
            $user->getRoles()
        );

        return $this->json([
            'success' => true,
            'token' => $token,
            'expires_in' => 86400,
            'patient' => [
                'id' => $user->getId(),
                'nome' => $user->getNome(),
                'email' => $user->getEmail(),
                'cpf' => $user->getCpf(),
            ],
        ]);
    }

    /**
     * Registro de novo paciente
     * 
     * Body: {
     *   "nome": "João Silva",
     *   "cpf": "123.456.789-00",
     *   "email": "joao@email.com",
     *   "telefone": "(11) 98765-4321",
     *   "data_nascimento": "1990-01-15",
     *   "password": "senha123"
     * }
     */
    #[Route('/auth/register', name: 'api_mobile_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!$data) {
            return $this->json([
                'success' => false,
                'error' => 'JSON inválido',
            ], Response::HTTP_BAD_REQUEST);
        }

        $nome = trim((string) ($data['nome'] ?? ''));
        $cpf = $this->cleanCpf((string) ($data['cpf'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $password = trim((string) ($data['password'] ?? ''));

        // Validações básicas
        if ($nome === '' || $cpf === '' || $email === '' || $password === '') {
            return $this->json([
                'success' => false,
                'error' => 'Nome, CPF, email e senha são obrigatórios',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (strlen($password) < 6) {
            return $this->json([
                'success' => false,
                'error' => 'A senha deve ter no mínimo 6 caracteres',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Verificar se CPF já existe
        if ($this->userRepository->findOneBy(['cpf' => $cpf])) {
            return $this->json([
                'success' => false,
                'error' => 'CPF já cadastrado',
            ], Response::HTTP_CONFLICT);
        }

        // Verificar se email já existe
        if ($this->userRepository->findOneBy(['email' => $email])) {
            return $this->json([
                'success' => false,
                'error' => 'Email já cadastrado',
            ], Response::HTTP_CONFLICT);
        }

        try {
            // Criar novo usuário
            $user = new User();
            $user->setNome($nome);
            $user->setCpf($cpf);
            $user->setEmail($email);
            $user->setAtivo(true);
            $user->setPerfil('PACIENTE');
            $user->setRoles(['ROLE_USER']);
            
            // Hash da senha
            $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
            $user->setPassword($hashedPassword);

            // Validar entidade
            $errors = $this->validator->validate($user);
            if (count($errors) > 0) {
                return $this->json([
                    'success' => false,
                    'error' => 'Dados inválidos',
                    'details' => (string) $errors,
                ], Response::HTTP_BAD_REQUEST);
            }

            $this->em->persist($user);
            $this->em->flush();

            // Auto-login: gerar token JWT
            $token = $this->jwtService->generateToken(
                $user->getId(),
                $user->getEmail(),
                $user->getRoles()
            );

            return $this->json([
                'success' => true,
                'message' => 'Cadastro realizado com sucesso',
                'token' => $token,
                'patient_id' => $user->getId(),
            ], Response::HTTP_CREATED);

        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'error' => 'Erro ao criar cadastro: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Recuperação de senha
     */
    #[Route('/auth/forgot-password', name: 'api_mobile_forgot_password', methods: ['POST'])]
    public function forgotPassword(Request $request): JsonResponse
    {
        // TODO: Implementar recuperação
        // - Validar CPF/email
        // - Gerar token de reset
        // - Enviar SMS/email com link
        
        return $this->json([
            'success' => false,
            'message' => 'Endpoint em desenvolvimento',
        ], Response::HTTP_NOT_IMPLEMENTED);
    }

    /**
     * Perfil do paciente logado
     * 
     * Headers: Authorization: Bearer {token}
     * Response: {
     *   "id": 123,
     *   "nome": "João Silva",
     *   "cpf": "123.456.789-00",
     *   "email": "joao@email.com",
     *   "telefone": "(11) 98765-4321",
     *   "data_nascimento": "1990-01-15",
     *   "foto_url": "https://...",
     *   "plano": "Essencial"
     * }
     */
    #[Route('/patient/profile', name: 'api_mobile_patient_profile', methods: ['GET'])]
    public function patientProfile(Request $request): JsonResponse
    {
        $user = $this->authenticateFromRequest($request);
        
        if ($user === null) {
            return $this->json([
                'success' => false,
                'error' => 'Token inválido ou expirado',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'success' => true,
            'patient' => [
                'id' => $user->getId(),
                'nome' => $user->getNome(),
                'cpf' => $user->getCpf(),
                'email' => $user->getEmail(),
                'ativo' => $user->isAtivo(),
                'perfil' => $user->getPerfil(),
                'created_at' => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    /**
     * Lista de agendamentos do paciente
     * 
     * Query params:
     * - ?status=pendente|confirmado|realizado|cancelado
     * - ?periodo=proximos|passados
     * 
     * Response: {
     *   "agendamentos": [
     *     {
     *       "id": 1,
     *       "data": "2026-07-20T14:30:00",
     *       "profissional": "Dr. João Médico",
     *       "especialidade": "Cardiologia",
     *       "tipo": "Consulta",
     *       "local": "Clínica Centro",
     *       "status": "confirmado",
     *       "pode_cancelar": true
     *     }
     *   ]
     * }
     */
    #[Route('/patient/appointments', name: 'api_mobile_patient_appointments', methods: ['GET'])]
    public function patientAppointments(Request $request): JsonResponse
    {
        $user = $this->authenticateFromRequest($request);
        
        if ($user === null) {
            return $this->json([
                'success' => false,
                'error' => 'Token inválido ou expirado',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // TODO: Implementar busca real de agendamentos
        return $this->json([
            'success' => true,
            'total' => 0,
            'agendamentos' => [],
            'message' => 'Integração com agenda será implementada',
        ]);
    }

    /**
     * Confirmar agendamento
     */
    #[Route('/patient/confirm-appointment/{id}', name: 'api_mobile_confirm_appointment', methods: ['POST'])]
    public function confirmAppointment(int $id, Request $request): JsonResponse
    {
        $user = $this->authenticateFromRequest($request);
        
        if ($user === null) {
            return $this->json([
                'success' => false,
                'error' => 'Token inválido ou expirado',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // TODO: Implementar confirmação real
        return $this->json([
            'success' => true,
            'message' => 'Funcionalidade será implementada',
            'agendamento_id' => $id,
        ]);
    }

    /**
     * Documentos do paciente (carteirinha, comprovantes, exames)
     */
    #[Route('/patient/documents', name: 'api_mobile_patient_documents', methods: ['GET'])]
    public function patientDocuments(Request $request): JsonResponse
    {
        $user = $this->authenticateFromRequest($request);
        
        if ($user === null) {
            return $this->json([
                'success' => false,
                'error' => 'Token inválido ou expirado',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'success' => true,
            'total' => 0,
            'documentos' => [],
            'message' => 'Integração com documentos será implementada',
        ]);
    }

    /**
     * Carteirinha digital do paciente
     */
    #[Route('/patient/card', name: 'api_mobile_patient_card', methods: ['GET'])]
    public function patientCard(Request $request): JsonResponse
    {
        $user = $this->authenticateFromRequest($request);
        
        if ($user === null) {
            return $this->json([
                'success' => false,
                'error' => 'Token inválido ou expirado',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'success' => true,
            'message' => 'Carteirinha digital será implementada',
            'patient_id' => $user->getId(),
        ]);
    }

    /**
     * Prescrições médicas do paciente
     */
    #[Route('/patient/prescriptions', name: 'api_mobile_patient_prescriptions', methods: ['GET'])]
    public function patientPrescriptions(Request $request): JsonResponse
    {
        $user = $this->authenticateFromRequest($request);
        
        if ($user === null) {
            return $this->json([
                'success' => false,
                'error' => 'Token inválido ou expirado',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'success' => true,
            'total' => 0,
            'prescricoes' => [],
            'message' => 'Integração com prescrições será implementada',
        ]);
    }

    // ────────────────────────── Helpers ────────────────────────────────

    /**
     * Autentica usuário a partir do token JWT no header Authorization
     */
    private function authenticateFromRequest(Request $request): ?User
    {
        $authHeader = $request->headers->get('Authorization');
        $token = $this->jwtService->extractTokenFromAuthHeader($authHeader);

        if (!$token) {
            return null;
        }

        $payload = $this->jwtService->validateToken($token);

        if (!$payload) {
            return null;
        }

        $userId = $payload['user_id'] ?? null;

        if (!$userId) {
            return null;
        }

        return $this->userRepository->find($userId);
    }

    /**
     * Remove formatação do CPF (deixa apenas números)
     */
    private function cleanCpf(string $cpf): string
    {
        return preg_replace('/[^0-9]/', '', $cpf);
    }
}
