<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Adapters;

use App\Domain\Messaging\Contracts\WhatsAppSender;
use App\Domain\Messaging\DTOs\DeliveryResult;
use App\Domain\Messaging\DTOs\WhatsAppMessage;
use App\Domain\Messaging\Services\TemplateParameterMapper;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class MetaWhatsAppSender implements WhatsAppSender
{
    /** @var list<string> */
    public const APPROVED_TEMPLATES = [
        'booking_submitted_v1',
        'booking_confirmed_v1',
        'booking_declined_v1',
        'booking_cancelled_v1',
        'staff_new_booking_alert_v1',
    ];

    private ?string $phoneNumberId;

    private ?string $systemUserToken;

    private string $apiVersion;

    public function __construct(
        ?string $phoneNumberId = null,
        ?string $systemUserToken = null,
        string $apiVersion = 'v21.0',
    ) {
        $this->phoneNumberId = $phoneNumberId ?? config('services.meta_whatsapp.phone_number_id');
        $this->systemUserToken = $systemUserToken ?? config('services.meta_whatsapp.system_user_token');
        $this->apiVersion = $apiVersion;
    }

    public function send(WhatsAppMessage $message): DeliveryResult
    {
        // Provider Gate: Keep disabled when secrets/credentials are missing
        if (empty($this->phoneNumberId) || empty($this->systemUserToken)) {
            return DeliveryResult::failed(
                errorCode: 'META_NOT_CONFIGURED',
                errorMessage: 'Meta WhatsApp Cloud API credentials are not configured or provider is disabled.',
                isRetryable: false
            );
        }

        // Normalize phone number to strictly digits (E.164 format without '+')
        $normalizedPhone = preg_replace('/\D/', '', $message->recipientPhone) ?? '';
        if ($normalizedPhone === '') {
            return DeliveryResult::failed(
                errorCode: 'INVALID_PHONE_NUMBER',
                errorMessage: 'Recipient phone number contains no valid digits.',
                isRetryable: false
            );
        }

        if (! in_array($message->templateName, self::APPROVED_TEMPLATES, true)) {
            return DeliveryResult::failed(
                errorCode: 'UNAPPROVED_TEMPLATE',
                errorMessage: "WhatsApp template '{$message->templateName}' is not in the approved allowlist.",
                isRetryable: false
            );
        }

        // Format ordered positional parameters aligned with canonical template schemas
        $orderedParams = app(TemplateParameterMapper::class)->formatMetaParams(
            $message->templateName,
            $message->templateParams
        );

        $parameters = array_map(
            fn (string $val): array => [
                'type' => 'text',
                'text' => $val,
            ],
            $orderedParams
        );

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $normalizedPhone,
            'type' => 'template',
            'template' => [
                'name' => $message->templateName,
                'language' => [
                    'code' => $message->language ?: 'en_US',
                ],
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => $parameters,
                    ],
                ],
            ],
        ];

        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages";

        try {
            $response = Http::withToken($this->systemUserToken)
                ->connectTimeout(3)
                ->timeout(8)
                ->post($url, $payload);

            if ($response->successful()) {
                $body = $response->json();
                $messageId = $body['messages'][0]['id'] ?? null;

                if (! $messageId) {
                    return DeliveryResult::unknown(
                        errorCode: 'META_MISSING_MESSAGE_ID',
                        errorMessage: 'Meta responded with HTTP 200 but omitted message ID in response body.',
                        rawResponse: ['status' => $response->status(), 'body' => $body]
                    );
                }

                return DeliveryResult::accepted(
                    providerMessageId: (string) $messageId,
                    rawResponse: ['status' => $response->status(), 'id' => $messageId]
                );
            }

            $status = $response->status();
            $body = $response->json();
            $errorData = $body['error'] ?? [];
            $metaCode = (string) ($errorData['code'] ?? $status);
            $metaMessage = (string) ($errorData['message'] ?? 'Meta API error');

            $safeMessage = $this->sanitizeError($metaMessage);

            // Invariant 4: Edge gateway errors (502, 504) leave acceptance ambiguous.
            // Meta does not support client idempotency keys, so auto-retrying 5xx is forbidden.
            if (in_array($status, [502, 504], true)) {
                return DeliveryResult::unknown(
                    errorCode: "META_GATEWAY_{$status}",
                    errorMessage: "Meta gateway returned HTTP {$status}; upstream message acceptance unknown.",
                    rawResponse: ['status' => $status, 'error_code' => $metaCode]
                );
            }

            // Only HTTP 429 rate limits are safely retryable before acceptance
            $isRetryable = ($status === 429);

            return DeliveryResult::failed(
                errorCode: "META_HTTP_{$metaCode}",
                errorMessage: $safeMessage,
                isRetryable: $isRetryable,
                rawResponse: ['status' => $status, 'error_code' => $metaCode]
            );
        } catch (ConnectionException $e) {
            $msg = strtolower($e->getMessage());

            // Pre-transmission failure: DNS resolution failure, connection refused, or connect timeout
            if (str_contains($msg, 'resolving') || str_contains($msg, 'name lookup') || str_contains($msg, 'connection timed out') || str_contains($msg, 'could not resolve host')) {
                return DeliveryResult::failed(
                    errorCode: 'META_CONNECT_TIMEOUT',
                    errorMessage: 'Network connection to Meta API timed out before connecting.',
                    isRetryable: true
                );
            }

            // Post-transmission failure: socket read timeout after request was sent
            return DeliveryResult::unknown(
                errorCode: 'META_SOCKET_READ_TIMEOUT',
                errorMessage: 'HTTP read timeout waiting for Meta response; acceptance status unknown.'
            );
        } catch (Throwable $e) {
            return DeliveryResult::failed(
                errorCode: 'META_TRANSPORT_EXCEPTION',
                errorMessage: $this->sanitizeError($e->getMessage()),
                isRetryable: false
            );
        }
    }

    private function sanitizeError(string $error): string
    {
        if ($this->systemUserToken) {
            $error = str_replace($this->systemUserToken, '[REDACTED_TOKEN]', $error);
        }

        $error = (string) preg_replace('/Bearer\s+[A-Za-z0-9_\-\.]+/i', 'Bearer [REDACTED]', $error);
        $error = (string) preg_replace('/\b\+?\d{9,15}\b/', '[REDACTED_PHONE]', $error);
        $error = (string) preg_replace('/[a-zA-Z0-9_\.\+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-\.]+/', '[REDACTED_EMAIL]', $error);

        return mb_substr($error, 0, 500);
    }
}
