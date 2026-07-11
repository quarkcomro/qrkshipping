<?php

namespace {
    abstract class Module
    {
        public const MULTISTORE_COMPATIBILITY_YES = 20;

        public string $name;
        public string $version;
        public string $author;
        public string $tab;
        public int $need_instance;
        public bool $bootstrap;
        public int $is_configurable;
        public int $multistoreCompatibility;
        /** @var array{min: string, max: string} */
        public array $ps_versions_compliancy;
        public string $displayName;
        public string $description;
        public string $confirmUninstall;
        public int|null $id;
        public bool $active = true;
        /** @var list<string> */
        protected array $_errors = [];
        /** @var list<array<string, mixed>> */
        protected array $tabs = [];
        public object $context;

        public function __construct() {}
        public function install(): bool { return true; }
        public function uninstall(): bool { return true; }
        public static function getInstanceByName(string $name): Module|false { return false; }
        /** @param array<string, string> $parameters */
        public function trans(string $id, array $parameters = [], string|null $domain = null, string|null $locale = null): string { return $id; }
    }

    abstract class CarrierModule extends Module
    {
        abstract public function getOrderShippingCost($params, $shipping_cost);
        abstract public function getOrderShippingCostExternal($params);
    }

    final class Language
    {
        /** @return list<array{locale: string}> */
        public static function getLanguages(bool $active = true): array { return []; }
    }

    final class Tools
    {
        public static function redirectAdmin(string $url): void {}
    }

    final class PrestaShopLogger
    {
        public const LOG_SEVERITY_LEVEL_ERROR = 3;
        public static function addLog(
            string $message,
            int $severity = 1,
            int|null $errorCode = null,
            string|null $objectType = null,
            int|null $objectId = null,
            bool $allowDuplicate = false,
        ): bool { return true; }
    }

    class Db
    {
        public static function getInstance(bool $useMaster = true): self { return new self(); }
        public function execute(string $sql): bool { return true; }
        /** @return list<array<string, mixed>>|false */
        public function executeS(string $sql): array|false { return []; }
        public function escape(string $value, bool $htmlOk = false, bool $bqSql = false): string { return $value; }
        public function Insert_ID(): int { return 0; }
        public function Affected_Rows(): int { return 0; }
        public function getMsgError(): string { return ''; }
    }
}

namespace PrestaShop\PrestaShop\Core\Context {
    final class Employee
    {
        public function getId(): int { return 1; }
    }

    final class EmployeeContext
    {
        public function getEmployee(): Employee|null { return new Employee(); }
    }

    class ShopContext
    {
        /** @param list<int> $associatedShopIds */
        public function __construct(
            object $shopConstraint,
            int $id,
            string $name,
            int $shopGroupId,
            int $categoryId,
            string $themeName,
            string $color,
            string $physicalUri,
            string $virtualUri,
            string $domain,
            string $domainSsl,
            bool $active,
            bool $secured,
            array $associatedShopIds,
            bool $isMultiShopEnabled,
            bool $isMultiShopUsed,
            bool $groupSharingStocks,
            bool $groupSharingCustomers,
            bool $groupSharingOrders,
        ) {}
        public function isAllShopContext(): bool { return false; }
        public function isShopGroupContext(): bool { return false; }
        public function isSingleShopContext(): bool { return true; }
        public function getId(): int { return 1; }
        public function getName(): string { return 'Shop'; }
        public function getShopGroupId(): int { return 1; }
        /** @return list<int> */
        public function getAssociatedShopIds(): array { return [1]; }
    }
}

namespace PrestaShopBundle\Controller\Admin {
    use PrestaShop\PrestaShop\Core\Context\EmployeeContext;
    use Symfony\Component\HttpFoundation\RedirectResponse;
    use Symfony\Component\HttpFoundation\Response;

    abstract class PrestaShopAdminController
    {
        protected function getEmployeeContext(): EmployeeContext { return new EmployeeContext(); }
        /** @param array<string, string> $parameters */
        protected function trans(string $id, array $parameters = [], string|null $domain = null, string|null $locale = null): string { return $id; }
        /** @param array<string, mixed> $parameters */
        protected function render(string $view, array $parameters = []): Response { return new Response(); }
        protected function addFlash(string $type, mixed $message): void {}
        /** @param array<string, mixed> $parameters */
        protected function redirectToRoute(string $route, array $parameters = [], int $status = 302): RedirectResponse { return new RedirectResponse('/'); }
    }
}

namespace PrestaShopBundle\Security\Attribute {
    #[\Attribute(\Attribute::TARGET_METHOD)]
    final class AdminSecurity
    {
        public function __construct(string $expression) {}
    }

    #[\Attribute(\Attribute::TARGET_METHOD)]
    final class DemoRestricted
    {
        /** @param array<string, mixed> $redirectQueryParamsToKeep */
        public function __construct(
            string|null $redirectRoute = null,
            string $message = 'This functionality has been disabled.',
            string $domain = 'Admin.Notifications.Error',
            array $redirectQueryParamsToKeep = [],
        ) {}
    }
}

namespace PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject {
    final class ShopConstraint
    {
        public static function allShops(bool $isStrict = false): self { return new self(); }
        public static function shopGroup(int $shopGroupId, bool $isStrict = false): self { return new self(); }
        public static function shop(int $shopId, bool $isStrict = false): self { return new self(); }
    }
}
