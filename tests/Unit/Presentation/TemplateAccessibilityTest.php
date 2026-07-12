<?php

declare(strict_types=1);

namespace Qrk\Commerce\Shipping\Tests\Unit\Presentation;

use PHPUnit\Framework\TestCase;

final class TemplateAccessibilityTest extends TestCase
{
    private string $templateDirectory;

    protected function setUp(): void
    {
        $this->templateDirectory = dirname(__DIR__, 3) . '/views/templates/admin';
    }

    public function testNavigationHasAnAccessibleNameAndLocalLinks(): void
    {
        $navigation = $this->template('_navigation.html.twig');

        self::assertStringContainsString('<nav', $navigation);
        self::assertStringContainsString('aria-label=', $navigation);
        self::assertStringNotContainsString('http://', $navigation);
        self::assertStringNotContainsString('https://', $navigation);
    }

    public function testPreferenceControlsHaveExplicitLabelsAndPostForms(): void
    {
        $preferences = $this->template('preferences.html.twig');

        $controlIds = [
            'diagnostics_detail_level',
            'inherit',
            'provider_account_label',
            'purge_on_uninstall',
            'reset_to_defaults',
        ];
        foreach ($controlIds as $controlId) {
            self::assertStringContainsString('id="' . $controlId . '"', $preferences);
            self::assertStringContainsString('for="' . $controlId . '"', $preferences);
        }

        self::assertSame(3, substr_count($preferences, '<form method="post"'));
        self::assertSame(3, substr_count($preferences, 'name="_token"'));
        self::assertStringContainsString('aria-describedby="purge_on_uninstall_help"', $preferences);
        self::assertStringContainsString('id="purge_on_uninstall_help"', $preferences);
        self::assertStringContainsString('aria-describedby="reset_to_defaults_help"', $preferences);
        self::assertStringContainsString('id="reset_to_defaults_help"', $preferences);
        self::assertStringContainsString('maxlength="160"', $preferences);
        self::assertStringContainsString('required', $preferences);
    }

    public function testPagesUseSemanticSectionsAndStatusMessages(): void
    {
        foreach (['dashboard.html.twig', 'preferences.html.twig', 'help.html.twig', 'diagnostics.html.twig'] as $file) {
            $template = $this->template($file);
            self::assertStringContainsString('<section', $template, $file . ' has no semantic section.');
            self::assertStringContainsString('<h2', $template, $file . ' has no section heading.');
        }

        self::assertStringContainsString('role="status"', $this->template('_shop_context.html.twig'));
        self::assertStringContainsString('scope="col"', $this->template('diagnostics.html.twig'));
    }

    public function testHelpDocumentsTheApprovedOperationalLimits(): void
    {
        $help = $this->template('help.html.twig');

        self::assertStringContainsString('There is no connection test or credential form', $help);
        self::assertStringContainsString('None is created in this increment.', $help);
        self::assertStringContainsString('Encrypted secrets are always deleted during uninstall and reset.', $help);
        self::assertStringContainsString('Lifecycle preferences are editable only in the All stores context.', $help);
        self::assertStringContainsString('fails closed', $help);
    }

    private function template(string $name): string
    {
        $content = file_get_contents($this->templateDirectory . '/' . $name);
        self::assertNotFalse($content, 'Cannot read template ' . $name . '.');

        return $content;
    }
}
