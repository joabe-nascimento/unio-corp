<?php

namespace App\Controller\Marketing;

use App\Config\PlannedHubRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class ApresentacaoController extends AbstractController
{
    #[Route('/apresentacao', name: 'app_apresentacao', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('marketing/apresentacao.html.twig', [
            'hub_groups' => $this->buildHubGroups(),
            'hub_categories' => $this->buildHubCategories(),
            'tour_screens' => $this->buildTourScreens(),
        ]);
    }

    #[Route('/apresentacao/preview/{scene}', name: 'app_apresentacao_preview', methods: ['GET'])]
    public function preview(string $scene): Response
    {
        $templates = [
            'workspace' => 'marketing/preview/workspace.html.twig',
            'hubs' => 'marketing/preview/hubs.html.twig',
            'hub-dock' => 'marketing/preview/hub_dock.html.twig',
        ];

        if (!isset($templates[$scene])) {
            throw new NotFoundHttpException();
        }

        return $this->render($templates[$scene], [
            'hub_groups' => $this->buildHubGroups(),
            'empresas' => $this->mockEmpresas(),
            'dock_items' => $this->mockDockItems(),
            'capture_mode' => true,
        ]);
    }

    /** @return list<array{label: string, hubs: list<array{id: string, label: string, icon: string}>}> */
    private function buildHubGroups(): array
    {
        $groups = [];

        $groups[] = [
            'label' => 'Operação',
            'hubs' => [
                ['id' => 'operacoes', 'label' => 'Núcleo de Operações', 'icon' => 'fa-building'],
                ['id' => 'talentos', 'label' => 'Núcleo de Talentos', 'icon' => 'fa-gem'],
                ['id' => 'maturidade', 'label' => 'Núcleo de Maturidade', 'icon' => 'fa-seedling'],
            ],
        ];

        foreach (PlannedHubRegistry::GROUP_ORDER as $groupKey) {
            $label = PlannedHubRegistry::GROUP_LABELS[$groupKey] ?? $groupKey;
            $hubs = [];
            foreach (PlannedHubRegistry::HUBS as $hub) {
                if ((PlannedHubRegistry::HUB_GROUP[$hub['id']] ?? '') !== $groupKey) {
                    continue;
                }
                $hubs[] = [
                    'id' => $hub['id'],
                    'label' => $hub['label'],
                    'icon' => $hub['icon'],
                ];
            }
            if ($hubs !== []) {
                $groups[] = ['label' => $label, 'hubs' => $hubs];
            }
        }

        $groups[] = [
            'label' => 'Plataforma',
            'hubs' => [
                ['id' => 'admin', 'label' => 'Plataforma', 'icon' => 'fa-shield-halved'],
            ],
        ];

        return $groups;
    }

    /** @return list<array{title: string, hubs: list<string>}> */
    private function buildHubCategories(): array
    {
        return [
            ['title' => 'Pessoas & RH', 'hubs' => ['Operações', 'Talentos', 'Maturidade', 'Clima', 'Portal do Colaborador', 'Recrutamento']],
            ['title' => 'Negócios & Growth', 'hubs' => ['Comercial (CRM)', 'Benefícios', 'Academy', 'Customer Success', 'Marketing']],
            ['title' => 'Tecnologia', 'hubs' => ['Núcleo TI', 'Integrações', 'Inovação', 'Segurança da Informação']],
            ['title' => 'Finanças & Compliance', 'hubs' => ['Financeiro', 'Jurídico', 'Compliance', 'Licitações']],
            ['title' => 'Operações & Ativos', 'hubs' => ['Obras', 'Suprimentos', 'Facilities', 'Qualidade', 'PMO']],
            ['title' => 'Inteligência', 'hubs' => ['Analytics', 'Conhecimento', 'Data & Lakehouse', 'Unio Cortex']],
        ];
    }

    /** @return list<array{step: string, title: string, desc: string, url: string, image: string, caption: string}> */
    private function buildTourScreens(): array
    {
        return [
            [
                'step' => '01',
                'title' => 'Acesso à plataforma',
                'desc' => 'Login seguro com identidade Unio, tema claro ou escuro e formulário protegido.',
                'url' => 'unio.app / login',
                'image' => 'login.png',
                'caption' => 'Tela de entrada, com autenticação CSRF, lembrar dispositivo e recuperação de senha.',
            ],
            [
                'step' => '02',
                'title' => 'Seleção de workspace',
                'desc' => 'Após o login, o usuário escolhe a empresa ou unidade em que vai operar.',
                'url' => 'unio.app / workspace',
                'image' => 'workspace.png',
                'caption' => 'Multi-empresa nativo, com Unio 1, Unio 2 e Unio 3 como áreas de trabalho isoladas.',
            ],
            [
                'step' => '03',
                'title' => 'Boas-vindas e atalhos',
                'desc' => 'Grid de núcleos liberados para o perfil do colaborador na área escolhida.',
                'url' => 'unio.app / bem-vindo',
                'image' => 'hub-dock.png',
                'caption' => 'Ponto de partida personalizado, com RH, Comercial (CRM), Operações, TI e demais módulos.',
            ],
            [
                'step' => '04',
                'title' => 'Navegação por núcleos',
                'desc' => 'Sidebar com 30+ centros de excelência agrupados por categoria de negócio.',
                'url' => 'unio.app · Núcleo selecionado',
                'image' => 'hubs-sidebar.png',
                'caption' => 'Picker modular, com Operação, Negócios (CRM), Tecnologia, Finanças, Inteligência e Plataforma.',
            ],
        ];
    }

    /** @return list<array{id: int, nome: string, setor: string|null}> */
    private function mockEmpresas(): array
    {
        return [
            ['id' => 1, 'nome' => 'Unio 1', 'setor' => 'Workspace · Demo'],
            ['id' => 2, 'nome' => 'Unio 2', 'setor' => 'Workspace · Demo'],
            ['id' => 3, 'nome' => 'Unio 3', 'setor' => 'Workspace · Demo'],
        ];
    }

    /** @return list<array{id: string, title: string, icon: string, subtitle: string}> */
    private function mockDockItems(): array
    {
        return [
            ['id' => 'operacoes', 'title' => 'Operações', 'icon' => 'fa-building', 'subtitle' => 'Núcleo de Operações'],
            ['id' => 'rh', 'title' => 'RH', 'icon' => 'fa-id-card', 'subtitle' => 'Módulo RH'],
            ['id' => 'comercial', 'title' => 'Comercial', 'icon' => 'fa-handshake', 'subtitle' => 'CRM · leads & pipeline'],
            ['id' => 'ti', 'title' => 'Núcleo TI', 'icon' => 'fa-tower-broadcast', 'subtitle' => 'Service desk & NOC'],
            ['id' => 'integracoes', 'title' => 'Integrações', 'icon' => 'fa-plug', 'subtitle' => 'Conectores & APIs'],
            ['id' => 'analytics', 'title' => 'Analytics', 'icon' => 'fa-chart-line', 'subtitle' => 'Indicadores'],
            ['id' => 'maturidade', 'title' => 'Maturidade', 'icon' => 'fa-seedling', 'subtitle' => 'Radar & evolução'],
            ['id' => 'talentos', 'title' => 'Talentos', 'icon' => 'fa-gem', 'subtitle' => 'Atração & PDIs'],
            ['id' => 'admin', 'title' => 'Plataforma', 'icon' => 'fa-shield-halved', 'subtitle' => 'Governança'],
        ];
    }
}
