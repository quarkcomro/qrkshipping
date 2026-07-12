<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Controller\Admin;

use InvalidArgumentException;
use PrestaShopBundle\Security\Attribute\AdminSecurity;
use PrestaShopBundle\Security\Attribute\DemoRestricted;
use Qrk\Commerce\Shipping\Adapter\PrestaShop\Multistore\ShopContextResolver;
use Qrk\Commerce\Shipping\Application\Lifecycle\LifecyclePolicy;
use Qrk\Commerce\Shipping\Application\Lifecycle\LifecyclePolicyService;
use Qrk\Commerce\Shipping\Application\Provider\ProviderAccountService;
use Qrk\Commerce\Shipping\Application\Settings\SettingsCatalog;
use Qrk\Commerce\Shipping\Application\Settings\SettingsService;
use Qrk\Commerce\Shipping\Domain\Provider\ProviderCode;
use Qrk\Commerce\Shipping\Domain\Settings\SettingScopeType;
use Qrk\Commerce\Shipping\ModuleMetadata;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Throwable;

final class PreferencesController extends AbstractQrkShippingController
{
    public const TAB_CLASS_NAME = 'AdminQrkShippingPreferences';
    private const CSRF_TOKEN_ID = 'qrkshipping_preferences';

    public function __construct(
        private readonly ShopContextResolver $shopContextResolver,
        private readonly SettingsService $settings,
        private readonly LifecyclePolicyService $lifecyclePolicy,
        private readonly ProviderAccountService $providerAccounts,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[AdminSecurity("is_granted('read', request.get('_legacy_controller'))")]
    public function indexAction(): Response
    {
        $context = $this->shopContextResolver->current();
        $diagnosticsDetailLevel = 'standard';
        $diagnosticsSource = $this->trans('Unavailable', [], self::ERROR_DOMAIN);
        $diagnosticsInherited = false;
        $diagnosticsPreferenceReadable = true;

        try {
            $resolved = $this->settings->resolve(
                SettingsCatalog::DIAGNOSTICS_DETAIL_LEVEL,
                $context->scope(),
            );
            $diagnosticsDetailLevel = (string) $resolved->value();
            $diagnosticsSource = $this->scopeSourceLabel($resolved->sourceScope());
            $diagnosticsInherited = $resolved->usesDefault() || $resolved->isInheritedAt($context->scope());
        } catch (Throwable) {
            $diagnosticsPreferenceReadable = false;
        }

        $policy = LifecyclePolicy::defaults();
        $lifecyclePolicyReadable = true;
        try {
            $policy = $this->lifecyclePolicy->current();
        } catch (Throwable) {
            $lifecyclePolicyReadable = false;
        }

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

        return $this->render('@Modules/qrkshipping/views/templates/admin/preferences.html.twig', [
            'layoutTitle' => $this->trans('QRK Shipping preferences', [], self::ADMIN_DOMAIN),
            'shopContext' => $this->shopContextView($context),
            'diagnosticsDetailLevel' => $diagnosticsDetailLevel,
            'diagnosticsSource' => $diagnosticsSource,
            'diagnosticsInherited' => $diagnosticsInherited,
            'diagnosticsPreferenceReadable' => $diagnosticsPreferenceReadable,
            'lifecyclePolicyReadable' => $lifecyclePolicyReadable,
            'purgeOnUninstall' => $policy->purgeOnUninstall(),
            'resetToDefaults' => $policy->resetToDefaults(),
            'providerAccount' => $account,
            'providerAccountReadable' => $accountReadable,
            'csrfToken' => $this->csrfTokenManager->getToken(self::CSRF_TOKEN_ID)->getValue(),
        ]);
    }

    #[AdminSecurity("is_granted('update', request.get('_legacy_controller'))")]
    #[DemoRestricted(redirectRoute: 'admin_qrkshipping_preferences')]
    public function updateAction(Request $request): RedirectResponse
    {
        $token = new CsrfToken(self::CSRF_TOKEN_ID, $request->request->getString('_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $this->addFlash('error', $this->trans(
                'The security token is invalid. Please retry.',
                [],
                self::ERROR_DOMAIN,
            ));

            return $this->redirectToRoute('admin_qrkshipping_preferences');
        }

        $context = $this->shopContextResolver->current();
        $this->assertShopContextAuthorized($context);
        $action = $request->request->getString('action');

        try {
            if ($action === 'save_diagnostics') {
                if ($request->request->getString('inherit') === '1') {
                    $this->settings->removeOverride(
                        SettingsCatalog::DIAGNOSTICS_DETAIL_LEVEL,
                        $context->scope(),
                        $this->auditActor(),
                    );
                } else {
                    $this->settings->set(
                        SettingsCatalog::DIAGNOSTICS_DETAIL_LEVEL,
                        $context->scope(),
                        $request->request->getString('diagnostics_detail_level'),
                        $this->auditActor(),
                    );
                }

                $this->addFlash('success', $this->trans('Diagnostic preferences were saved.', [], self::ADMIN_DOMAIN));
            } elseif ($action === 'save_lifecycle_policy') {
                if ($context->scope()->type() !== SettingScopeType::ALL) {
                    $this->addFlash('error', $this->trans(
                        'Lifecycle policy can be changed only in the All stores context.',
                        [],
                        self::ERROR_DOMAIN,
                    ));

                    return $this->redirectToRoute('admin_qrkshipping_preferences');
                }

                $this->lifecyclePolicy->save(
                    $request->request->getString('purge_on_uninstall') === '1',
                    $request->request->getString('reset_to_defaults') === '1',
                    $this->auditActor(),
                );

                $this->addFlash('success', $this->trans(
                    'The global lifecycle policy was saved.',
                    [],
                    self::ADMIN_DOMAIN,
                ));
            } elseif ($action === 'save_provider_account') {
                if (!$context->isSingleShop()) {
                    throw new InvalidArgumentException('Provider account editing requires a single-shop context.');
                }

                $this->providerAccounts->saveDraftLabel(
                    ProviderCode::fromString(ModuleMetadata::INITIAL_PROVIDER_CODE),
                    $context->scope()->shopId(),
                    $context->scope()->shopGroupId(),
                    $request->request->getString('provider_account_label'),
                    $this->auditActor(),
                );

                $this->addFlash('success', $this->trans(
                    'The Cargus draft account label was saved.',
                    [],
                    self::ADMIN_DOMAIN,
                ));
            } else {
                throw new InvalidArgumentException('Unknown preferences action.');
            }
        } catch (InvalidArgumentException) {
            $this->addFlash('error', $this->trans('The submitted values are invalid.', [], self::ERROR_DOMAIN));
        } catch (Throwable) {
            $this->addFlash('error', $this->trans('The change could not be saved safely.', [], self::ERROR_DOMAIN));
        }

        return $this->redirectToRoute('admin_qrkshipping_preferences');
    }
}
