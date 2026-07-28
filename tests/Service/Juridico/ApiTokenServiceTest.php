<?php

namespace App\Tests\Service\Juridico;

use App\Entity\ApiToken;
use App\Entity\Empresa;
use App\Repository\ApiTokenRepository;
use App\Service\Juridico\ApiTokenService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class ApiTokenServiceTest extends TestCase
{
    private EntityManagerInterface&\PHPUnit\Framework\MockObject\MockObject $em;
    private ApiTokenRepository&\PHPUnit\Framework\MockObject\MockObject $tokenRepo;
    private ApiTokenService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->tokenRepo = $this->createMock(ApiTokenRepository::class);
        $this->service = new ApiTokenService($this->em, $this->tokenRepo);
    }

    public function testGerarCriaTokenComPrefixoEHashDiferentesDoBruto(): void
    {
        $empresa = new Empresa();

        $this->em->expects(self::once())->method('persist')->with(self::isInstanceOf(ApiToken::class));
        $this->em->expects(self::once())->method('flush');

        $resultado = $this->service->gerar($empresa, 'Meu ERP', [ApiToken::SCOPE_LEITURA], null);

        self::assertStringStartsWith('ujr_live_', $resultado['raw']);
        self::assertSame($empresa, $resultado['token']->getEmpresa());
        self::assertSame('Meu ERP', $resultado['token']->getNome());
        self::assertNotSame($resultado['raw'], $resultado['token']->getTokenHash());
        self::assertSame(hash('sha256', $resultado['raw']), $resultado['token']->getTokenHash());
        self::assertStringStartsWith('ujr_live_', $resultado['token']->getTokenPrefix());
    }

    public function testGerarUsaNomePadraoQuandoVazio(): void
    {
        $this->em->method('persist');
        $this->em->method('flush');

        $resultado = $this->service->gerar(new Empresa(), '', [], null);

        self::assertSame('Token de integração', $resultado['token']->getNome());
        self::assertSame([ApiToken::SCOPE_LEITURA], $resultado['token']->getScopes());
    }

    public function testValidarRejeitaTokenComPrefixoInvalido(): void
    {
        $this->tokenRepo->expects(self::never())->method('findActiveByHash');

        self::assertNull($this->service->validar('token_qualquer_invalido'));
    }

    public function testValidarRejeitaQuandoTokenNaoEncontrado(): void
    {
        $this->tokenRepo->method('findActiveByHash')->willReturn(null);

        self::assertNull($this->service->validar('ujr_live_abc123'));
    }

    public function testValidarRegistraUsoQuandoTokenAtivo(): void
    {
        $token = new ApiToken();
        $token->setEmpresa(new Empresa());
        $token->setNome('teste');
        $token->setTokenHash(hash('sha256', 'ujr_live_abc123'));
        $token->setTokenPrefix('ujr_live_abc1');

        $this->tokenRepo->method('findActiveByHash')->willReturn($token);
        $this->em->expects(self::once())->method('flush');

        $resultado = $this->service->validar('ujr_live_abc123');

        self::assertSame($token, $resultado);
        self::assertSame(1, $token->getTotalRequisicoes());
    }

    public function testRevogarMarcaTokenComoInativo(): void
    {
        $token = new ApiToken();
        $token->setEmpresa(new Empresa());
        $token->setNome('teste');
        $token->setTokenHash('hash');
        $token->setTokenPrefix('prefix');

        $this->em->expects(self::once())->method('flush');

        $this->service->revogar($token);

        self::assertFalse($token->isAtivo());
        self::assertNotNull($token->getRevogadoEm());
    }
}
