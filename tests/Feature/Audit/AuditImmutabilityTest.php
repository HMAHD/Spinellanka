<?php

use App\Domain\Audit\Exceptions\ImmutableAuditLogException;
use App\Domain\Audit\Services\AuditService;
use App\Domain\Identity\Role;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();
});

test('audit event model update throws immutable exception', function (): void {
    $event = AuditEvent::create([
        'actor_type' => 'system',
        'action' => 'test.event',
        'summary' => 'Initial event summary',
        'created_at' => now(),
    ]);

    expect(fn () => $event->update(['summary' => 'Modified summary']))
        ->toThrow(ImmutableAuditLogException::class, 'Audit events cannot be modified.');
});

test('audit event model delete throws immutable exception', function (): void {
    $event = AuditEvent::create([
        'actor_type' => 'system',
        'action' => 'test.event',
        'summary' => 'Event to be deleted',
        'created_at' => now(),
    ]);

    expect(fn () => $event->delete())
        ->toThrow(ImmutableAuditLogException::class, 'Audit events cannot be deleted.');
});

test('gate denies update and delete on audit event even for owner admin', function (): void {
    $owner = User::create([
        'name' => 'Owner For Audit',
        'email' => 'owneraudit@spinellanka.test',
        'password' => Hash::make('Password123456!'),
        'is_active' => true,
        'token_version' => 1,
    ]);
    $owner->assignRole(Role::OwnerAdmin->value);

    $event = AuditEvent::create([
        'actor_type' => 'system',
        'action' => 'test.event',
        'summary' => 'Policy check event',
        'created_at' => now(),
    ]);

    expect(Gate::forUser($owner)->allows('update', $event))->toBeFalse();
    expect(Gate::forUser($owner)->allows('delete', $event))->toBeFalse();
});

test('database trigger aborts raw sql update and delete on audit events', function (): void {
    $id = DB::table('audit_events')->insertGetId([
        'actor_type' => 'system',
        'action' => 'raw.sql.test',
        'summary' => 'Raw SQL Trigger Test',
        'created_at' => now()->toDateTimeString(),
    ]);

    // Raw UPDATE should fail due to DB trigger
    expect(fn () => DB::table('audit_events')->where('id', $id)->update(['summary' => 'Hacked']))
        ->toThrow(Exception::class);

    // Raw DELETE should fail due to DB trigger
    expect(fn () => DB::table('audit_events')->where('id', $id)->delete())
        ->toThrow(Exception::class);
});

test('audit service sanitizes sensitive keys and invalid email from metadata', function (): void {
    $auditService = app(AuditService::class);

    $event = $auditService->record(
        action: 'auth.failed',
        actor: null,
        summary: 'Failed login with accidental password leak',
        metadata: [
            'password' => 'SuperSecret123!',
            'token' => 'bearer_token_xyz',
            'attempted_email' => 'MyPasswordTypedInEmailField', // Invalid email format
            'valid_field' => 'KeepMe',
        ],
        actorType: 'system'
    );

    expect($event->metadata['password'])->toBe('[REDACTED]');
    expect($event->metadata['token'])->toBe('[REDACTED]');
    expect($event->metadata['attempted_email'])->toBe('[MALFORMED_NON_EMAIL_INPUT]');
    expect($event->metadata['valid_field'])->toBe('KeepMe');
});
