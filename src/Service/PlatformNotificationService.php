<?php

namespace App\Service;

use App\Entity\Empresa;
use App\Entity\PlatformNotificacao;
use App\Entity\User;
use App\Repository\PlatformNotificacaoRepository;
use Doctrine\ORM\EntityManagerInterface;

final class PlatformNotificationService
{
    public function __construct(
        private EntityManagerInterface $em,
        private PlatformNotificacaoRepository $repository,
        private PlatformNotificationPresenter $presenter,
    ) {}

    /**
     * @param array<string, mixed>|null $routeParams
     */
    public function notify(
        Empresa $empresa,
        User $user,
        string $modulo,
        string $tipo,
        string $titulo,
        string $mensagem,
        ?string $routeName = null,
        ?array $routeParams = null,
        string $icon = 'fa-bell',
        string $severidade = 'info',
    ): PlatformNotificacao {
        $n = (new PlatformNotificacao())
            ->setEmpresa($empresa)
            ->setUser($user)
            ->setModulo($modulo)
            ->setTipo($tipo)
            ->setTitulo($titulo)
            ->setMensagem($mensagem)
            ->setRouteName($routeName)
            ->setRouteParams($routeParams)
            ->setIcon($icon)
            ->setSeveridade($severidade);

        $this->em->persist($n);
        $this->em->flush();

        return $n;
    }

    /** @param list<User> $users */
    public function notifyMany(
        Empresa $empresa,
        array $users,
        string $modulo,
        string $tipo,
        string $titulo,
        string $mensagem,
        ?string $routeName = null,
        ?array $routeParams = null,
        string $icon = 'fa-bell',
        string $severidade = 'info',
        ?User $except = null,
    ): void {
        foreach ($users as $user) {
            if ($except !== null && $user->getId() === $except->getId()) {
                continue;
            }
            $this->notify(
                $empresa,
                $user,
                $modulo,
                $tipo,
                $titulo,
                $mensagem,
                $routeName,
                $routeParams,
                $icon,
                $severidade,
            );
        }
    }

    /** @return list<array<string, mixed>> */
    public function listForUser(Empresa $empresa, User $user, ?bool $unreadOnly = null, int $limit = 50): array
    {
        return array_map(
            fn (PlatformNotificacao $n) => $this->presenter->toView($n),
            $this->repository->findForUser($empresa, $user, $unreadOnly, $limit),
        );
    }

    public function countUnread(Empresa $empresa, User $user): int
    {
        return $this->repository->countUnread($empresa, $user);
    }

    public function markRead(Empresa $empresa, User $user, int $id): PlatformNotificacao
    {
        $n = $this->repository->find($id);
        if (!$n instanceof PlatformNotificacao
            || $n->getUser()->getId() !== $user->getId()
            || $n->getEmpresa()->getId() !== $empresa->getId()) {
            throw new \InvalidArgumentException('Notificação não encontrada.');
        }

        if (!$n->isLida()) {
            $n->setLida(true);
            $this->em->flush();
        }

        return $n;
    }

    public function markAllRead(Empresa $empresa, User $user): int
    {
        return $this->repository->markAllRead($empresa, $user);
    }

    public function findOwned(Empresa $empresa, User $user, int $id): ?PlatformNotificacao
    {
        $n = $this->repository->find($id);
        if (!$n instanceof PlatformNotificacao
            || $n->getUser()->getId() !== $user->getId()
            || $n->getEmpresa()->getId() !== $empresa->getId()) {
            return null;
        }

        return $n;
    }
}
