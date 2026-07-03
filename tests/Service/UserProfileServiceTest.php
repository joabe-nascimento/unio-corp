<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Exception\UserProfileException;
use App\Service\PlatformConfigService;
use App\Service\UserProfileService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserProfileServiceTest extends TestCase
{
    public function testUpdateIdentityRejectsEmptyName(): void
    {
        $service = $this->createService();
        $user = $this->user();

        $this->expectException(UserProfileException::class);
        $this->expectExceptionMessage('Informe seu nome.');

        $service->updateIdentity($user, '   ');
    }

    public function testChangePasswordRejectsWrongCurrentPassword(): void
    {
        $service = $this->createService();
        $user = $this->user();

        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->method('isPasswordValid')->willReturn(false);

        $this->expectException(UserProfileException::class);
        $this->expectExceptionMessage('Senha atual incorreta.');

        $service->changePassword($user, 'wrong', 'NewPass123', $hasher);
    }

    public function testChangePasswordRejectsSamePassword(): void
    {
        $service = $this->createService();
        $user = $this->user();

        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->method('isPasswordValid')->willReturn(true);

        $this->expectException(UserProfileException::class);
        $this->expectExceptionMessage('diferente da atual');

        $service->changePassword($user, 'unio123', 'unio123', $hasher);
    }

    public function testUpdateIdentityStoresAvatar(): void
    {
        $projectDir = sys_get_temp_dir() . '/unio-profile-test-' . uniqid('', true);
        mkdir($projectDir . '/public/uploads/users', 0777, true);

        $tmp = tempnam(sys_get_temp_dir(), 'avatar');
        file_put_contents(
            $tmp,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='),
        );

        $service = $this->createService($projectDir);
        $user = $this->user();

        $file = new UploadedFile($tmp, 'avatar.png', 'image/png', null, true);

        $service->updateIdentity($user, 'Gestor Atualizado', $file);

        self::assertSame('Gestor Atualizado', $user->getNome());
        self::assertNotNull($user->getAvatar());
        self::assertStringStartsWith('/uploads/users/', $user->getAvatar());

        $full = $projectDir . '/public' . $user->getAvatar();
        self::assertFileExists($full);

        @unlink($full);
        @unlink($tmp);
        @rmdir($projectDir . '/public/uploads/users');
        @rmdir($projectDir . '/public/uploads');
        @rmdir($projectDir . '/public');
        @rmdir($projectDir);
    }

    private function createService(?string $projectDir = null): UserProfileService
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('flush');

        $platform = $this->createMock(PlatformConfigService::class);
        $platform->method('validatePassword')->willReturn(null);

        return new UserProfileService(
            $em,
            $platform,
            $projectDir ?? sys_get_temp_dir(),
        );
    }

    private function user(): User
    {
        $user = new User();
        $user->setEmail('profile-test@unio.dev');
        $user->setNome('Profile Test');
        $user->setPassword('secret');
        $user->setPerfil('GESTOR');

        return $user;
    }
}
