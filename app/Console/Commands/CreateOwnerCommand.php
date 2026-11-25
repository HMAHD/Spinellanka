<?php

namespace App\Console\Commands;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Identity\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class CreateOwnerCommand extends Command
{
    protected $signature = 'staff:create-owner
                            {--name= : Name of the owner}
                            {--email= : Email address of the owner}
                            {--generate-password : Automatically generate a secure 24-character random password}
                            {--force : Force creation without prompt if an owner already exists}';

    protected $description = 'Provision a staff owner account with Owner/Admin role and TOTP MFA support';

    public function handle(AuditService $auditService): int
    {
        $this->info('Spinellanka — Staff Owner Provisioning');

        // Check if an active owner already exists
        $existingOwnerCount = User::role(Role::OwnerAdmin->value)
            ->where('is_active', true)
            ->count();

        if ($existingOwnerCount > 0 && ! $this->option('force')) {
            if (! $this->confirm("{$existingOwnerCount} active Owner account(s) already exist. Proceed with provisioning another Owner?", false)) {
                $this->warn('Aborted.');

                return self::FAILURE;
            }
        }

        $name = $this->option('name') ?: $this->ask('Enter owner full name');
        $email = $this->option('email') ?: $this->ask('Enter owner email address');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email],
            [
                'name' => ['required', 'string', 'max:128'],
                'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        if ($this->option('generate-password')) {
            $password = Str::password(24, true, true, true, false);
            $this->warn('A secure random password has been generated for this account.');
        } else {
            $password = $this->secret('Enter owner password (minimum 12 characters, mixed case, numbers, symbols)');
            $confirmation = $this->secret('Confirm owner password');

            if ($password !== $confirmation) {
                $this->error('Passwords do not match.');

                return self::FAILURE;
            }
        }

        $passwordValidator = Validator::make(
            ['password' => $password],
            [
                'password' => [
                    'required',
                    'string',
                    Password::min(12)
                        ->mixedCase()
                        ->letters()
                        ->numbers()
                        ->symbols()
                        ->uncompromised(),
                ],
            ]
        );

        if ($passwordValidator->fails()) {
            foreach ($passwordValidator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'is_active' => true,
            'token_version' => 1,
        ]);

        $user->assignRole(Role::OwnerAdmin->value);

        $auditService->record(
            action: 'user.created',
            actor: null,
            summary: "Owner account {$user->email} provisioned via CLI",
            metadata: [
                'user_id' => $user->id,
                'email' => $user->email,
                'role' => Role::OwnerAdmin->value,
                'provisioning_method' => 'artisan staff:create-owner',
            ],
            auditable: $user,
            actorType: 'system'
        );

        $this->newLine();
        $this->info("✓ Owner account successfully provisioned for {$user->email}.");

        if ($this->option('generate-password')) {
            $this->line('----------------------------------------------------');
            $this->comment("One-time password: {$password}");
            $this->line('----------------------------------------------------');
            $this->warn('Record this password securely now. It will not be displayed again.');
        }

        $this->line('Next steps:');
        $this->line('1. Log in at: /admin/login');
        $this->line('2. Configure TOTP MFA (optional locally; required outside local development).');

        return self::SUCCESS;
    }
}
