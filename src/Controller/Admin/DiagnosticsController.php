<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Controller\Admin;

use PrestaShopBundle\Security\Attribute\AdminSecurity;
use Qrk\Commerce\Shipping\Adapter\PrestaShop\Diagnostics\FoundationDiagnosticsService;
use Qrk\Commerce\Shipping\Adapter\PrestaShop\Multistore\ShopContextResolver;
use Qrk\Commerce\Shipping\Application\Settings\SettingsCatalog;
use Qrk\Commerce\Shipping\Application\Settings\SettingsService;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class DiagnosticsController extends AbstractQrkShippingController
{
    public const TAB_CLASS_NAME = 'AdminQrkShippingDiagnostics';

    public function __construct(
        private readonly ShopContextResolver $shopContextResolver,
        private readonly FoundationDiagnosticsService $diagnostics,
        private readonly SettingsService $settings,
    ) {
    }

    #[AdminSecurity("is_granted('read', request.get('_legacy_controller'))")]
    public function indexAction(): Response
    {
        $context = $this->shopContextResolver->current();
        $report = $this->diagnostics->run();
        $detailLevel = 'standard';
        $detailLevelReadable = true;

        try {
            $detailLevel = (string) $this->settings->resolve(
                SettingsCatalog::DIAGNOSTICS_DETAIL_LEVEL,
                $context->scope(),
            )->value();
        } catch (Throwable) {
            $detailLevelReadable = false;
        }

        return $this->render('@Modules/qrkshipping/views/templates/admin/diagnostics.html.twig', [
            'layoutTitle' => $this->trans('QRK Shipping diagnostics', [], self::ADMIN_DOMAIN),
            'shopContext' => $this->shopContextView($context),
            'report' => $report,
            'detailLevel' => $detailLevel,
            'detailLevelReadable' => $detailLevelReadable,
        ]);
    }
}
