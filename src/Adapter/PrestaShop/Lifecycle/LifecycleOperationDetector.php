<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Adapter\PrestaShop\Lifecycle;

use PrestaShop\PrestaShop\Adapter\SymfonyContainer;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Throwable;

final class LifecycleOperationDetector
{
    /**
     * @param list<string>|null $consoleArguments
     */
    public function __construct(
        private readonly ?RequestStack $requestStack = null,
        private readonly ?array $consoleArguments = null,
    ) {
    }

    public function isResetFor(string $moduleName): bool
    {
        return $this->isConsoleReset($moduleName) || $this->isBackOfficeReset($moduleName);
    }

    private function isBackOfficeReset(string $moduleName): bool
    {
        $request = $this->currentRequest();
        if (!$request instanceof Request) {
            return false;
        }

        return $this->requestValue($request, 'action') === 'reset'
            && $this->requestValue($request, 'module_name') === $moduleName;
    }

    private function isConsoleReset(string $moduleName): bool
    {
        $arguments = $this->consoleArguments ?? $this->runtimeConsoleArguments();
        $commandPosition = array_search('prestashop:module', $arguments, true);
        if (!is_int($commandPosition)) {
            return false;
        }

        return ($arguments[$commandPosition + 1] ?? null) === 'reset'
            && ($arguments[$commandPosition + 2] ?? null) === $moduleName;
    }

    private function currentRequest(): ?Request
    {
        if ($this->requestStack instanceof RequestStack) {
            return $this->requestStack->getCurrentRequest();
        }

        if (!class_exists(SymfonyContainer::class)) {
            return null;
        }

        try {
            $container = SymfonyContainer::getInstance();
            if (!$container instanceof ContainerInterface || !$container->has('request_stack')) {
                return null;
            }

            $requestStack = $container->get('request_stack');

            return $requestStack instanceof RequestStack ? $requestStack->getCurrentRequest() : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function requestValue(Request $request, string $name): ?string
    {
        foreach ([$request->attributes, $request->request, $request->query] as $parameters) {
            $value = $parameters->get($name);
            if (is_string($value)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function runtimeConsoleArguments(): array
    {
        $arguments = $_SERVER['argv'] ?? [];
        if (!is_array($arguments)) {
            return [];
        }

        $normalized = [];
        foreach ($arguments as $argument) {
            if (is_string($argument)) {
                $normalized[] = $argument;
            }
        }

        return $normalized;
    }
}
