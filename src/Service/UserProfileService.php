<?php

namespace App\Service;

use App\Entity\User;
use App\Exception\UserProfileException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserProfileService
{
    private const UPLOAD_DIR = 'public/uploads/users';
    private const MAX_PHOTO_BYTES = 2_097_152;
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(
        private EntityManagerInterface $em,
        private PlatformConfigService $platformConfig,
        private string $projectDir,
    ) {}

    public function updateIdentity(User $user, string $nome, ?UploadedFile $avatarFile = null, bool $removeAvatar = false): void
    {
        $nome = trim($nome);
        if ($nome === '') {
            throw new UserProfileException('Informe seu nome.');
        }
        if (mb_strlen($nome) > 100) {
            throw new UserProfileException('Nome muito longo (máximo 100 caracteres).');
        }

        $user->setNome($nome);

        if ($removeAvatar) {
            $this->deleteAvatarFile($user->getAvatar());
            $user->setAvatar(null);
        }

        if ($avatarFile instanceof UploadedFile && $avatarFile->isValid()) {
            $this->deleteAvatarFile($user->getAvatar());
            $user->setAvatar($this->storeAvatar($avatarFile));
        }

        $this->em->flush();
    }

    public function changePassword(
        User $user,
        string $currentPlain,
        string $newPlain,
        UserPasswordHasherInterface $hasher,
    ): void {
        if (!$hasher->isPasswordValid($user, $currentPlain)) {
            throw new UserProfileException('Senha atual incorreta.');
        }

        if ($hasher->isPasswordValid($user, $newPlain)) {
            throw new UserProfileException('A nova senha deve ser diferente da atual.');
        }

        $policyError = $this->platformConfig->validatePassword($newPlain);
        if ($policyError !== null) {
            throw new UserProfileException($policyError);
        }

        $user->setPassword($hasher->hashPassword($user, $newPlain));
        $this->em->flush();
    }

    private function storeAvatar(UploadedFile $file): string
    {
        if ($file->getSize() > self::MAX_PHOTO_BYTES) {
            throw new UserProfileException('Foto muito grande. Máximo 2 MB.');
        }

        $mime = (string) $file->getMimeType();
        if (!\in_array($mime, self::ALLOWED_MIME, true)) {
            throw new UserProfileException('Formato não suportado. Use JPG, PNG ou WebP.');
        }

        $ext = match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        $dir = $this->projectDir . '/' . self::UPLOAD_DIR;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new UserProfileException('Não foi possível salvar a foto.');
        }

        $name = uniqid('user_', true) . '.' . $ext;
        $file->move($dir, $name);

        return '/uploads/users/' . $name;
    }

    private function deleteAvatarFile(?string $path): void
    {
        if (!$path || !str_starts_with($path, '/uploads/users/')) {
            return;
        }

        $full = $this->projectDir . '/public' . $path;
        if (is_file($full)) {
            @unlink($full);
        }
    }
}
