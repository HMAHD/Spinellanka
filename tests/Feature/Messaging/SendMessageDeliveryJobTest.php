<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use App\Domain\Messaging\Adapters\FakeTransactionalMailer;
use App\Domain\Messaging\Adapters\FakeWhatsAppSender;
use App\Domain\Messaging\DTOs\DeliveryResult;
use App\Domain\Messaging\Enums\MessageChannel;
use App\Domain\Messaging\Enums\MessageDeliveryStatus;
use App\Jobs\SendMessageDeliveryJob;
use App\Models\MessageDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();
    $this->waSender = new FakeWhatsAppSender;
    $this->mailer = new FakeTransactionalMailer;
});

test('successful delivery transitions status to sent with timestamp and provider id', function (): void {
    $delivery = MessageDelivery::factory()->create([
        'channel' => MessageChannel::WhatsApp,
        'status' => MessageDeliveryStatus::Pending,
        'attempts' => 0,
    ]);

    $this->waSender->fakeNextResult(DeliveryResult::accepted('wamid.HBgLMTIzNDU2Nw=='));

    $job = new SendMessageDeliveryJob($delivery->id);
    $job->handle($this->waSender, $this->mailer);

    $delivery->refresh();
    expect($delivery->status)->toBe(MessageDeliveryStatus::Sent)
        ->and($delivery->sent_at)->not->toBeNull()
        ->and($delivery->provider_message_id)->toBe('wamid.HBgLMTIzNDU2Nw==')
        ->and($delivery->attempts)->toBe(1)
        ->and($delivery->last_error_code)->toBeNull();
});

test('ambiguous provider timeout transitions status to delivery_unknown and strictly does not auto-retry', function (): void {
    $delivery = MessageDelivery::factory()->create([
        'channel' => MessageChannel::WhatsApp,
        'status' => MessageDeliveryStatus::Pending,
        'attempts' => 0,
        'max_attempts' => 3,
    ]);

    $this->waSender->fakeNextResult(DeliveryResult::unknown(
        errorCode: 'HTTP_TIMEOUT',
        errorMessage: 'Connection timed out waiting for Meta WhatsApp acceptance.'
    ));

    $job = new SendMessageDeliveryJob($delivery->id);
    $job->handle($this->waSender, $this->mailer);

    $delivery->refresh();
    expect($delivery->status)->toBe(MessageDeliveryStatus::DeliveryUnknown)
        ->and($delivery->attempts)->toBe(1)
        ->and($delivery->last_error_code)->toBe('HTTP_TIMEOUT')
        ->and($delivery->last_error_message)->toContain('timed out')
        ->and($delivery->sent_at)->toBeNull();
});

test('SendMessageDeliveryJob refuses to claim record in delivery_unknown status', function (): void {
    $delivery = MessageDelivery::factory()->deliveryUnknown()->create();

    $job = new SendMessageDeliveryJob($delivery->id);
    $job->handle($this->waSender, $this->mailer);

    $delivery->refresh();
    // Status must remain DeliveryUnknown; attempts must NOT increment
    expect($delivery->status)->toBe(MessageDeliveryStatus::DeliveryUnknown)
        ->and($delivery->attempts)->toBe(1);
    expect($this->waSender->getSentMessages())->toBeEmpty();
});

test('permanent failure marks status failed immediately without retrying', function (): void {
    $delivery = MessageDelivery::factory()->create([
        'channel' => MessageChannel::WhatsApp,
        'status' => MessageDeliveryStatus::Pending,
        'attempts' => 0,
        'max_attempts' => 3,
    ]);

    $this->waSender->fakeNextResult(DeliveryResult::failed(
        errorCode: 'INVALID_PHONE_NUMBER',
        errorMessage: 'The destination number is not registered on WhatsApp.',
        isRetryable: false
    ));

    $job = new SendMessageDeliveryJob($delivery->id);
    $job->handle($this->waSender, $this->mailer);

    $delivery->refresh();
    expect($delivery->status)->toBe(MessageDeliveryStatus::Failed)
        ->and($delivery->failed_at)->not->toBeNull()
        ->and($delivery->attempts)->toBe(1)
        ->and($delivery->last_error_code)->toBe('INVALID_PHONE_NUMBER');
});

test('email delivery uses TransactionalMailer and updates status', function (): void {
    $delivery = MessageDelivery::factory()->email()->create([
        'status' => MessageDeliveryStatus::Pending,
        'attempts' => 0,
    ]);

    $this->mailer->fakeNextResult(DeliveryResult::accepted('pm_msg_123456789'));

    $job = new SendMessageDeliveryJob($delivery->id);
    $job->handle($this->waSender, $this->mailer);

    $delivery->refresh();
    expect($delivery->status)->toBe(MessageDeliveryStatus::Sent)
        ->and($delivery->provider_message_id)->toBe('pm_msg_123456789');

    expect($this->mailer->getSentMessages())->toHaveCount(1)
        ->and($this->mailer->getSentMessages()[0]->recipientEmail)->toBe($delivery->recipient_identifier);
});

test('failed hook marks delivery failed when queue attempts are exhausted and redacts tokens', function (): void {
    $delivery = MessageDelivery::factory()->create([
        'status' => MessageDeliveryStatus::Sending,
        'attempts' => 3,
    ]);

    $job = new SendMessageDeliveryJob($delivery->id);
    $job->failed(new \RuntimeException('Connection failed with Bearer EAAB_SECRET_12345 for +94771234567'));

    $delivery->refresh();
    expect($delivery->status)->toBe(MessageDeliveryStatus::Failed)
        ->and($delivery->failed_at)->not->toBeNull()
        ->and($delivery->last_error_code)->toBe('JOB_FAILED')
        ->and($delivery->last_error_message)->not->toContain('EAAB_SECRET_12345')
        ->and($delivery->last_error_message)->toContain('[REDACTED]');
});
