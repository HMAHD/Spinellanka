<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use Tests\TestCase;

class SecretIsolationTest extends TestCase
{
    public function test_pull_request_verify_workflow_has_no_access_to_deployment_secrets(): void
    {
        $verifyWorkflow = file_get_contents(base_path('.github/workflows/verify.yml'));
        $this->assertNotFalse($verifyWorkflow);

        // Assert no deployment secrets are referenced in verify.yml
        $this->assertStringNotContainsString('CPANEL_SSH_KEY', $verifyWorkflow);
        $this->assertStringNotContainsString('CPANEL_SSH_HOST', $verifyWorkflow);
        $this->assertStringNotContainsString('CPANEL_SSH_USER', $verifyWorkflow);
        $this->assertStringNotContainsString('CPANEL_DEPLOY_PATH', $verifyWorkflow);
        $this->assertStringNotContainsString('secrets.', $verifyWorkflow);
    }

    public function test_deployment_workflow_enforces_environment_boundary_and_manual_dispatch(): void
    {
        $deployWorkflow = file_get_contents(base_path('.github/workflows/deploy.yml'));
        $this->assertNotFalse($deployWorkflow);

        // Must never run on pull_request
        $this->assertStringContainsString('workflow_dispatch:', $deployWorkflow);
        $this->assertStringNotContainsString('pull_request:', $deployWorkflow);

        // Must bind to staging environment only while production remains disabled
        $this->assertStringContainsString('environment: staging', $deployWorkflow);
        $this->assertStringNotContainsString('- production', $deployWorkflow);

        // Must enforce concurrency lock
        $this->assertStringContainsString('concurrency:', $deployWorkflow);

        // Must assert hosting acceptance check exists
        $this->assertStringContainsString('Verdict', $deployWorkflow);
    }

    public function test_no_secrets_or_env_files_are_committed_in_git(): void
    {
        exec('git ls-files', $trackedFiles, $exitCode);
        $this->assertSame(0, $exitCode);

        foreach ($trackedFiles as $file) {
            $this->assertFalse(
                $file === '.env' || str_ends_with($file, '/.env') || ($file !== '.env.example' && str_starts_with($file, '.env.')),
                "Production environment file must never be tracked in Git: $file"
            );
            $this->assertFalse(
                str_ends_with($file, '.pem') || str_ends_with($file, '.key') || str_ends_with($file, '.id_rsa'),
                "Private keys must never be tracked in Git: $file"
            );
        }
    }
}
