<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use App\Domain\Messaging\Adapters\MetaWhatsAppSender;
use App\Domain\Messaging\Adapters\PostmarkTransactionalMailer;
use App\Domain\Messaging\DTOs\EmailMessage;
use App\Domain\Messaging\DTOs\WhatsAppMessage;
use App\Domain\Messaging\Enums\DeliveryResultStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('MetaWhatsAppSender fails closed when unconfigured or missing credentials', function (): void {
    $sender = new MetaWhatsAppSender(phoneNumberId: null, systemUserToken: null);

    $message = new WhatsAppMessage(
        recipientPhone: '+94771234567',
        templateName: 'booking_submitted_v1',
        templateParams: ['reference' => 'SPL-123'],
    );

    $result = $sender->send($message);

    expect($result->status)->toBe(DeliveryResultStatus::Failed)
        ->and($result->errorCode)->toBe('META_NOT_CONFIGURED')
        ->and($result->isRetryable)->toBeFalse();
});

test('MetaWhatsAppSender formats valid Meta Graph API v21.0 payload and strips non-digits', function (): void {
    Http::fake([
        'https://graph.facebook.com/v21.0/123456789/messages' => Http::response([
            'messaging_product' => 'whatsapp',
            'contacts' => [['input' => '94771234567', 'wa_id' => '94771234567']],
            'messages' => [['id' => 'wamid.HBgLMTIzNDU2Nw==']],
        ], 200),
    ]);

    $sender = new MetaWhatsAppSender(
        phoneNumberId: '123456789',
        systemUserToken: 'EAAB_test_mock_token_123',
    );

    $message = new WhatsAppMessage(
        recipientPhone: '+94 77 123-4567',
        templateName: 'booking_submitted_v1',
        templateParams: ['reference' => 'SPL-123'],
    );

    $result = $sender->send($message);

    expect($result->status)->toBe(DeliveryResultStatus::Accepted)
        ->and($result->providerMessageId)->toBe('wamid.HBgLMTIzNDU2Nw==');

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $request->url() === 'https://graph.facebook.com/v21.0/123456789/messages'
            && $body['to'] === '94771234567'
            && $body['type'] === 'template'
            && $body['template']['name'] === 'booking_submitted_v1'
            && $request->header('Authorization')[0] === 'Bearer EAAB_test_mock_token_123';
    });
});

test('MetaWhatsAppSender returns DeliveryResult unknown on network timeout', function (): void {
    Http::fake([
        'https://graph.facebook.com/*' => function () {
            throw new ConnectionException('cURL error 28: Operation timed out');
        },
    ]);

    $sender = new MetaWhatsAppSender(
        phoneNumberId: '123456789',
        systemUserToken: 'mock_token',
    );

    $message = new WhatsAppMessage(
        recipientPhone: '+94771234567',
        templateName: 'booking_submitted_v1',
        templateParams: ['reference' => 'SPL-123'],
    );

    $result = $sender->send($message);

    expect($result->status)->toBe(DeliveryResultStatus::Unknown)
        ->and($result->errorCode)->toBe('META_SOCKET_READ_TIMEOUT')
        ->and($result->isRetryable)->toBeFalse();
});

test('PostmarkTransactionalMailer fails closed when unconfigured', function (): void {
    $mailer = new PostmarkTransactionalMailer(serverToken: null);

    $message = new EmailMessage(
        recipientEmail: 'guest@example.com',
        subject: 'Reservation Update',
        templateName: 'booking_submitted_v1',
        templateParams: ['reference' => 'SPL-123'],
    );

    $result = $mailer->send($message);

    expect($result->status)->toBe(DeliveryResultStatus::Failed)
        ->and($result->errorCode)->toBe('POSTMARK_NOT_CONFIGURED')
        ->and($result->isRetryable)->toBeFalse();
});

test('PostmarkTransactionalMailer formats Postmark payload and sends request', function (): void {
    Http::fake([
        'https://api.postmarkapp.com/email' => Http::response([
            'To' => 'guest@example.com',
            'SubmittedAt' => '2026-11-25T13:00:00Z',
            'MessageID' => 'b7ad2854-e0a5-4ebc-8fb8-8319fb66c118',
            'ErrorCode' => 0,
            'Message' => 'OK',
        ], 200),
    ]);

    $mailer = new PostmarkTransactionalMailer(
        serverToken: 'mock-postmark-token-xyz',
        senderEmail: 'dispatch@spinellanka.com',
    );

    $message = new EmailMessage(
        recipientEmail: 'guest@example.com',
        subject: 'Spinellanka: Advance Request Received — SPL-123',
        templateName: 'booking_submitted_v1',
        templateParams: ['reference' => 'SPL-123'],
    );

    $result = $mailer->send($message);

    expect($result->status)->toBe(DeliveryResultStatus::Accepted)
        ->and($result->providerMessageId)->toBe('b7ad2854-e0a5-4ebc-8fb8-8319fb66c118');

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $request->url() === 'https://api.postmarkapp.com/email'
            && $body['From'] === 'dispatch@spinellanka.com'
            && $body['To'] === 'guest@example.com'
            && $body['TrackOpens'] === false
            && $request->header('X-Postmark-Server-Token')[0] === 'mock-postmark-token-xyz';
    });
});

test('PostmarkTransactionalMailer returns unknown status on connection timeout', function (): void {
    Http::fake([
        'https://api.postmarkapp.com/email' => function () {
            throw new ConnectionException('cURL error 28: Operation timed out');
        },
    ]);

    $mailer = new PostmarkTransactionalMailer(
        serverToken: 'mock-postmark-token-xyz',
        senderEmail: 'dispatch@spinellanka.com',
    );

    $message = new EmailMessage(
        recipientEmail: 'guest@example.com',
        subject: 'Test Subject',
        templateName: 'booking_submitted_v1',
        templateParams: ['reference' => 'SPL-123'],
    );

    $result = $mailer->send($message);

    expect($result->status)->toBe(DeliveryResultStatus::Unknown)
        ->and($result->errorCode)->toBe('POSTMARK_SOCKET_READ_TIMEOUT')
        ->and($result->isRetryable)->toBeFalse();
});
