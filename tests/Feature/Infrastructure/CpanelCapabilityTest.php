<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use Tests\TestCase;

class CpanelCapabilityTest extends TestCase
{
    public function test_cpanel_assessment_tool_runs_and_identifies_blocked_gates(): void
    {
        $projectRoot = base_path();
        $cmd = sprintf('bash %s/scripts/cpanel-assess 2>&1', escapeshellarg($projectRoot));
        exec($cmd, $output, $exitCode);

        // When unconfigured with cPanel facts, exit code must be 1 (Blocked)
        $this->assertSame(1, $exitCode);
        $outputStr = implode("\n", $output);

        $this->assertStringContainsString('Spinellanka cPanel Capability Assessment', $outputStr);
        $this->assertStringContainsString('Verdict: Blocked — hosting', $outputStr);
        $this->assertStringContainsString('1. PHP web runtime', $outputStr);
        $this->assertStringContainsString('2. PHP CLI runtime', $outputStr);
        $this->assertStringContainsString('3. PHP extensions', $outputStr);
        $this->assertStringContainsString('4. Database engine', $outputStr);
        $this->assertStringContainsString('5. Web root isolation', $outputStr);
        $this->assertStringContainsString('10. Backups', $outputStr);
        $this->assertStringContainsString('11. Limits & Environment', $outputStr);
    }

    public function test_planning_documentation_records_blocked_hosting_verdict_and_disabled_deployment(): void
    {
        $planningDoc = file_get_contents(base_path('docs/planning/05-cpanel-deployment-and-operations.md'));
        $this->assertNotFalse($planningDoc);

        $this->assertStringContainsString('Blocked — hosting', $planningDoc);
        $this->assertStringContainsString('Disabled', $planningDoc);
        $this->assertStringContainsString('**Responsible Owner**: Hosting/Technical Owner (Client)', $planningDoc);
    }

    public function test_roadmap_records_phase_01_blocked_status(): void
    {
        $roadmap = file_get_contents(base_path('roadmap.md'));
        $this->assertNotFalse($roadmap);

        $this->assertStringContainsString('Blocked — hosting', $roadmap);
    }
}
