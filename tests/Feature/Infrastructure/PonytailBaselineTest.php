<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use Tests\TestCase;

class PonytailBaselineTest extends TestCase
{
    public function test_claude_settings_pins_official_ponytail_baseline_v5_3_3(): void
    {
        $settingsPath = base_path('.claude/settings.json');
        $this->assertFileExists($settingsPath);

        $settings = json_decode((string) file_get_contents($settingsPath), true, flags: JSON_THROW_ON_ERROR);

        $ponytailMarketplace = $settings['extraKnownMarketplaces']['ponytail'] ?? [];
        $this->assertSame('github', $ponytailMarketplace['source']['source'] ?? null);
        $this->assertSame('DietrichGebert/ponytail', $ponytailMarketplace['source']['repo'] ?? null);
        $this->assertSame('v5.3.3', $ponytailMarketplace['source']['ref'] ?? null);
        $this->assertFalse($ponytailMarketplace['autoUpdate'] ?? true);

        $enabledPlugins = $settings['enabledPlugins'] ?? [];
        $this->assertTrue($enabledPlugins['ponytail@ponytail'] ?? false);
    }

    public function test_environment_check_script_passes_with_ponytail_verified(): void
    {
        $cmd = sprintf('bash %s/scripts/check-environment 2>&1', escapeshellarg(base_path()));
        exec($cmd, $output, $exitCode);

        $this->assertSame(0, $exitCode, implode("\n", $output));
        $outputStr = implode("\n", $output);
        $this->assertStringContainsString('Ponytail plugin 5.3.3 is installed', $outputStr);
        $this->assertStringContainsString('EGM, Matt Pocock, and Ponytail project settings match', $outputStr);
    }
}
