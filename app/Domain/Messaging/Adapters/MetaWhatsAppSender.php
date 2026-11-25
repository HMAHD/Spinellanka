<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Adapters;

use App\Domain\Messaging\Contracts\WhatsAppSender;
use App\Domain\Messaging\DTOs\DeliveryResult;
use App\Domain\Messaging\DTOs\WhatsAppMessage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class MetaWhatsAppSender implements WhatsAppSender
{
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

        // Build positional parameters array for Meta template components
        $parameters = [];
        foreach ($message->templateParams as $val) {
            $parameters[] = [
                'type' => 'text',
                'text' => (string) $val,
            ];
        }

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
                $messageId = $body['messages'][0]['id'] ?? 'wamid_unknown_'.uniqid();

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

            // Sanitize error message to prevent token or PII leakage
            $safeMessage = $this->sanitizeError($metaMessage);

            // Rate limits (429) or Server Errors (500, 502, 503, 504) are retryable
            $isRetryable = $status === 429 || ($status >= 500 && $status < 600);

            return DeliveryResult::failed(
                errorCode: "META_HTTP_{$metaCode}",
                errorMessage: $safeMessage,
                isRetryable: $isRetryable,
                rawResponse: ['status' => $status, 'error_code' => $metaCode]
            );
        } catch (ConnectionException $e) {
            $msg = $e->getMessage();
            // Ambiguity check: if socket read timeout after transmission, cannot verify acceptance safely
            if (str_contains(strtolower($msg), 'timed out') && ! str_contains(strtolower($msg), 'connection timed out')) {
                return DeliveryResult::unknown(
                    errorCode: 'META_SOCKET_READ_TIMEOUT',
                    errorMessage: 'HTTP read timeout waiting for Meta response; acceptance status unknown.'
                );
            }

            return DeliveryResult::failed(
                errorCode: 'META_CONNECT_TIMEOUT',
                errorMessage: 'Network connection to Meta API timed out before connecting.',
                isRetryable: true
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

        return mb_substr($error, 0, 500);
    }
}

