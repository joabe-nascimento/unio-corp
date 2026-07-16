<?php

namespace App\Twig;

use App\Entity\User;
use App\PosOperatorio\ClinicFeatureCatalog;
use App\PosOperatorio\ClinicProductCatalog;
use App\Service\Clinic\ClinicStaffAccess;
use App\Service\Organismo\OrganismoCopyService;
use App\Service\Organismo\OrganismoFeature;
use App\Service\PosOperatorio\ClinicProductConfigService;
use App\Service\WorkspaceService;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;

final class OrganismoTwigExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private OrganismoFeature $organismo,
        private OrganismoCopyService $copy,
        private Security $security,
        private WorkspaceService $workspace,
        private ClinicProductConfigService $productConfig,
        private ClinicStaffAccess $clinicStaffAccess,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('clinic_feature_route_active', [ClinicFeatureCatalog::class, 'isRouteActive']),
            new TwigFunction('clinic_pinned_features', [ClinicFeatureCatalog::class, 'pinnedFeatures']),
            new TwigFunction('clinic_features_for_group', [ClinicFeatureCatalog::class, 'featuresForGroup']),
            new TwigFunction('clinic_group_open', [ClinicFeatureCatalog::class, 'isGroupActive']),
            new TwigFunction('clinic_products_nav_open', [ClinicFeatureCatalog::class, 'isProductsNavActive']),
        ];
    }

    /** @return array<string, mixed> */
    public function getGlobals(): array
    {
        if (!$this->organismo->isEnabled()) {
            return [
                'organismo' => ['enabled' => false, 'pulso_home' => false, 'copy' => []],
                'org_clinic' => false,
                'org_brand_label' => null,
            ];
        }

        $isClinic = $this->copy->isClinicProfile();
        $enabledProducts = $this->resolveProductEnabledMap();
        $navFeatures = $isClinic
            ? ClinicFeatureCatalog::filterByProducts(ClinicFeatureCatalog::all(), $enabledProducts)
            : [];

        $user = $this->security->getUser();
        if ($isClinic && $user instanceof User) {
            $navFeatures = $this->clinicStaffAccess->filterFeatures($user, $navFeatures);
        }

        return [
            'organismo' => [
                'enabled' => true,
                'pulso_home' => $this->organismo->isPulsoHome(),
                'copy' => $this->copy->getGlobals(),
            ],
            'org_clinic' => $isClinic,
            'org_brand_label' => $this->copy->brandName(),
            'clinic_nav_sections' => $isClinic ? ClinicFeatureCatalog::sectionsForFeatures($navFeatures) : [],
            'clinic_nav_features' => $navFeatures,
            'clinic_products' => $isClinic ? $this->resolveClinicProducts() : [],
            'clinic_products_enabled' => $enabledProducts,
            'clinic_pos_operatorio_enabled' => $enabledProducts[ClinicProductCatalog::POS_OPERATORIO] ?? true,
        ];
    }

    /** @return array<string, bool> */
    private function resolveProductEnabledMap(): array
    {
        $defaults = ClinicProductCatalog::defaultEnabledMap();
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return $defaults;
        }

        $empresa = $this->workspace->getActiveEmpresa($user);
        if ($empresa === null) {
            return $defaults;
        }

        return $this->productConfig->get($empresa);
    }

    /** @return list<array<string, mixed>> */
    private function resolveClinicProducts(): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return $this->productsWithDefaultState();
        }

        $empresa = $this->workspace->getActiveEmpresa($user);
        if ($empresa === null) {
            return $this->productsWithDefaultState();
        }

        return $this->productConfig->productsForEmpresa($empresa);
    }

    /** @return list<array<string, mixed>> */
    private function productsWithDefaultState(): array
    {
        $items = [];
        foreach (ClinicProductCatalog::all() as $product) {
            $items[] = array_merge($product, [
                'enabled' => (bool) ($product['default_enabled'] ?? true),
            ]);
        }

        return $items;
    }
}
