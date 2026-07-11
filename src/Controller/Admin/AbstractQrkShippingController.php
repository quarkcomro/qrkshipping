<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Controller\Admin;

use PrestaShopBundle\Controller\Admin\PrestaShopAdminController;
use Qrk\Commerce\Shipping\Adapter\PrestaShop\Multistore\BackOfficeShopContext;
use Qrk\Commerce\Shipping\Domain\Audit\AuditActor;
use Qrk\Commerce\Shipping\Domain\Settings\SettingScope;
use Qrk\Commerce\Shipping\Domain\Settings\SettingScopeType;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

abstract class AbstractQrkShippingController extends PrestaShopAdminController
{
    protected const ADMIN_DOMAIN = 'Modules.Qrkshipping.Admin';
    protected const ERROR_DOMAIN = 'Modules.Qrkshipping.Errors';

    protected function auditActor(): AuditActor
    {
        $employee = $this->getEmployeeContext()->getEmployee();

        return $employee === null || $employee->getId() <= 0
            ? AuditActor::system()
            : AuditActor::employee($employee->getId());
    }

    /**
     * @return array{
     *   type: string,
     *   shop_group_id: int,
     *   shop_id: int,
     *   shop_name: string,
     *   associated_shop_ids: list<int>
     * }
     */
    protected function shopContextView(BackOfficeShopContext $context): array
    {
        $scope = $context->scope();

        return [
            'type' => $scope->type()->value,
            'shop_group_id' => $scope->shopGroupId(),
            'shop_id' => $scope->shopId(),
            'shop_name' => $context->shopName(),
            'associated_shop_ids' => $context->associatedShopIds(),
        ];
    }

    protected function assertShopContextAuthorized(BackOfficeShopContext $context): void
    {
        $scope = $context->scope();
        $employeeContext = $this->getEmployeeContext();

        $authorized = match ($scope->type()) {
            SettingScopeType::ALL => $employeeContext->hasAuthorizationForAllShops(),
            SettingScopeType::SHOP_GROUP => $employeeContext->hasAuthorizationOnShopGroup($scope->shopGroupId()),
            SettingScopeType::SHOP => $employeeContext->hasAuthorizationOnShop($scope->shopId()),
        };

        if (!$authorized) {
            throw new AccessDeniedHttpException('The employee is not authorized for this shop context.');
        }
    }

    protected function scopeSourceLabel(?SettingScope $scope): string
    {
        if ($scope === null) {
            return $this->trans('Catalog default', [], self::ADMIN_DOMAIN);
        }

        return match ($scope->type()) {
            SettingScopeType::ALL => $this->trans('All stores', [], self::ADMIN_DOMAIN),
            SettingScopeType::SHOP_GROUP => $this->trans(
                'Shop group #%id%',
                ['%id%' => (string) $scope->shopGroupId()],
                self::ADMIN_DOMAIN,
            ),
            SettingScopeType::SHOP => $this->trans(
                'Shop #%id%',
                ['%id%' => (string) $scope->shopId()],
                self::ADMIN_DOMAIN,
            ),
        };
    }
}
