<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Controller\Admin;

use PrestaShopBundle\Security\Attribute\AdminSecurity;
use Qrk\Commerce\Shipping\Adapter\PrestaShop\Multistore\ShopContextResolver;
use Symfony\Component\HttpFoundation\Response;

final class HelpController extends AbstractQrkShippingController
{
    public const TAB_CLASS_NAME = 'AdminQrkShippingHelp';

    public function __construct(
        private readonly ShopContextResolver $shopContextResolver,
    ) {
    }

    #[AdminSecurity("is_granted('read', request.get('_legacy_controller'))")]
    public function indexAction(): Response
    {
        $context = $this->shopContextResolver->current();

        return $this->render('@Modules/qrkshipping/views/templates/admin/help.html.twig', [
            'layoutTitle' => $this->trans('QRK Shipping help', [], self::ADMIN_DOMAIN),
            'shopContext' => $this->shopContextView($context),
        ]);
    }
}
