<?php

namespace App\Service;

use App\Entity\Empresa;
use App\Entity\User;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class GlobalSearchService
{
    public function __construct(
        private PermissionService $permissions,
        private NavigationService $navigation,
        private UrlGeneratorInterface $url,
    ) {
    }

    /**
     * @return list<array{type: string, label: string, subtitle: string, url: string, initials: string}>
     */
    public function getMemberItems(User $user, ?Empresa $empresa): array
    {
        if (!$this->navigation->showModuloPessoas($user)) {
            return [];
        }

        $items = [];
        foreach ($this->permissions->getMembersForSearch($empresa) as $member) {
            $url = $member['ficha_id']
                ? $this->url->generate('app_pessoas_membro_ficha', ['id' => $member['ficha_id']])
                : $this->url->generate('app_pessoas_membros');

            $subtitle = trim($member['equipe'] . ' · ' . $member['cargo'], ' ·');

            $items[] = [
                'type' => 'member',
                'label' => $member['nome'],
                'subtitle' => $subtitle !== '' ? $subtitle : $member['email'],
                'url' => $url,
                'initials' => $member['initials'],
            ];
        }

        return $items;
    }
}
