<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Controller\Admin;

use PrestaShopBundle\Security\Attribute\AdminSecurity;
use Qrk\Commerce\Shipping\Adapter\PrestaShop\Diagnostics\FoundationDiagnosticsService;
use Qrk\Commerce\Shipping\Adapter\PrestaShop\Multistore\ShopContextResolver;
use Qrk\Commerce\Shipping\Application\Provider\ProviderAccountService;
use Qrk\Commerce\Shipping\Domain\Provider\ProviderCode;
use Qrk\Commerce\Shipping\ModuleMetadata;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class DashboardController extends AbstractQrkShippingController
{
    public const TAB_CLASS_NAME = 'AdminQrkShippingDashboard';

    public function __construct(
        private readonly ShopContextResolver $shopContextResolver,
        private readonly FoundationDiagnosticsService $diagnostics,
        private readonly ProviderAccountService $providerAccounts,
    ) {
    }

    #[AdminSecurity("is_granted('read', request.get('_legacy_controller'))")]
    public function indexAction(): Response
    {
        $context = $this->shopContextResolver->current();
        $report = $this->diagnostics->run();
        $account = null;
        $accountReadable = true;

        if ($context->isSingleShop()) {
            try {
                $account = $this->providerAccounts->find(
                    ProviderCode::fromString(ModuleMetadata::INITIAL_PROVIDER_CODE),
                    $context->scope()->shopId(),
                );
            } catch (Throwable) {
                $accountReadable = false;
            }
        }

        return $this->render('@Modules/qrkshipping/views/templates/admin/dashboard.html.twig', [
            'layoutTitle' => $this->trans('QRK Shipping', [], self::ADMIN_DOMAIN),
            'moduleVersion' => ModuleMetadata::VERSION,
            'schemaVersion' => ModuleMetadata::SCHEMA_VERSION,
            'shopContext' => $this->shopContextView($context),
            'diagnosticsHealthy' => $report->isHealthy(),
            'diagnosticChecks' => $report->checks(),
            'providerAccount' => $account,
            'providerAccountReadable' => $accountReadable,
        ]);
    }
}
