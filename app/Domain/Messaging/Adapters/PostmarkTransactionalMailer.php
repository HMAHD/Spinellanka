<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Adapters;

use App\Domain\Messaging\Contracts\TransactionalMailer;
use App\Domain\Messaging\DTOs\DeliveryResult;
use App\Domain\Messaging\DTOs\EmailMessage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class PostmarkTransactionalMailer implements TransactionalMailer
{
    private ?string $serverToken;

    private ?string $senderEmail;

    public function __construct(
        ?string $serverToken = null,
        ?string $senderEmail = null,
    ) {
        $this->serverToken = $serverToken ?? config('services.postmark.server_token');
        $this->senderEmail = $senderEmail ?? config('mail.from.address', 'dispatch@spinellanka.com');
    }

    public function send(EmailMessage $message): DeliveryResult
    {
        // Provider Gate: Keep disabled when secrets/credentials are missing
        if (empty($this->serverToken)) {
            return DeliveryResult::failed(
                errorCode: 'POSTMARK_NOT_CONFIGURED',
                errorMessage: 'Postmark server token is not configured or provider is disabled.',
                isRetryable: false
            );
        }

        $payload = [
            'From' => $this->senderEmail,
            'To' => trim($message->recipientEmail),
            'Subject' => $message->subject,
            'Tag' => $message->templateName,
            'TrackOpens' => false,
            'TrackLinks' => 'None',
            'TextBody' => $this->renderTextBody($message),
            'MessageStream' => 'outbound',
        ];

        $url = 'https://api.postmarkapp.com/email';

        try {
            $response = Http::withHeaders([
                'X-Postmark-Server-Token' => $this->serverToken,
                'Accept' => 'application/json',
            ])
                ->connectTimeout(3)
                ->timeout(8)
                ->post($url, $payload);

            if ($response->successful()) {
                $body = $response->json();
                $messageId = $body['MessageID'] ?? 'pm_unknown_'.uniqid();

                return DeliveryResult::accepted(
                    providerMessageId: (string) $messageId,
                    rawResponse: ['status' => $response->status(), 'id' => $messageId]
                );
            }

            $status = $response->status();
            $body = $response->json();
            $pmCode = (string) ($body['ErrorCode'] ?? $status);
            $pmMessage = (string) ($body['Message'] ?? 'Postmark API error');

            $isRetryable = $status === 429 || ($status >= 500 && $status < 600);

            return DeliveryResult::failed(
                errorCode: "POSTMARK_HTTP_{$pmCode}",
                errorMessage: $this->sanitizeError($pmMessage),
                isRetryable: $isRetryable,
                rawResponse: ['status' => $status, 'error_code' => $pmCode]
            );
        } catch (ConnectionException $e) {
            $msg = $e->getMessage();
            if (str_contains(strtolower($msg), 'timed out') && ! str_contains(strtolower($msg), 'connection timed out')) {
                return DeliveryResult::unknown(
                    errorCode: 'POSTMARK_SOCKET_READ_TIMEOUT',
                    errorMessage: 'HTTP read timeout waiting for Postmark response; acceptance status unknown.'
                );
            }

            return DeliveryResult::failed(
                errorCode: 'POSTMARK_CONNECT_TIMEOUT',
                errorMessage: 'Network connection to Postmark API timed out before connecting.',
                isRetryable: true
            );
        } catch (Throwable $e) {
            return DeliveryResult::failed(
                errorCode: 'POSTMARK_TRANSPORT_EXCEPTION',
                errorMessage: $this->sanitizeError($e->getMessage()),
                isRetryable: false
            );
        }
    }

    private function renderTextBody(EmailMessage $message): string
    {
        $lines = ["Subject: {$message->subject}", ''];
        foreach ($message->templateParams as $key => $val) {
            $label = ucwords(str_replace('_', ' ', (string) $key));
            $lines[] = "{$label}: {$val}";
        }

        return implode("\n", $lines);
    }

    private function sanitizeError(string $error): string
    {
        if ($this->serverToken) {
            $error = str_replace($this->serverToken, '[REDACTED_TOKEN]', $error);
        }

        return mb_substr($error, 0, 500);
    }
}

