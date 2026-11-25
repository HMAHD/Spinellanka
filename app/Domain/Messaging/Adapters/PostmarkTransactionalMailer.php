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
                $messageId = $body['MessageID'] ?? null;

                if (! $messageId) {
                    return DeliveryResult::unknown(
                        errorCode: 'POSTMARK_MISSING_MESSAGE_ID',
                        errorMessage: 'Postmark responded with HTTP 200 but omitted MessageID in response body.',
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
            $msg = strtolower($e->getMessage());

            // Pre-transmission failure
            if (str_contains($msg, 'resolving') || str_contains($msg, 'name lookup') || str_contains($msg, 'connection timed out') || str_contains($msg, 'could not resolve host')) {
                return DeliveryResult::failed(
                    errorCode: 'POSTMARK_CONNECT_TIMEOUT',
                    errorMessage: 'Network connection to Postmark API timed out before connecting.',
                    isRetryable: true
                );
            }

            // Post-transmission socket read timeout
            return DeliveryResult::unknown(
                errorCode: 'POSTMARK_SOCKET_READ_TIMEOUT',
                errorMessage: 'HTTP read timeout waiting for Postmark response; acceptance status unknown.'
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
        $params = $message->templateParams;
        $guestName = $params['customer_name'] ?? 'Valued Guest';
        $ref = $params['reference'] ?? 'Reservation';

        $lines = [
            "Dear {$guestName},\n",
            "Reference: {$ref}",
        ];

        if (! empty($params['service_title'])) {
            $lines[] = "Service: {$params['service_title']}";
        }
        if (! empty($params['service_start'])) {
            $lines[] = "Scheduled Start: {$params['service_start']}";
        }
        if (! empty($params['origin_text']) && ! empty($params['destination_text'])) {
            $lines[] = "Journey: {$params['origin_text']} → {$params['destination_text']}";
        }
        if (! empty($params['vehicle_display'])) {
            $lines[] = "Vehicle: {$params['vehicle_display']}";
        }
        if (! empty($params['status_url'])) {
            $lines[] = "Live Booking Status & Access: {$params['status_url']}";
        }
        if (! empty($params['reason'])) {
            $lines[] = "Information: {$params['reason']}";
        }
        if (! empty($params['support_contact'])) {
            $lines[] = "Support & Inquiries: {$params['support_contact']}";
        }

        $lines[] = "\nWarm regards,\nSpinellanka Fleet Operations\nhttps://spinellanka.com";

        return implode("\n", $lines);
    }

    private function sanitizeError(string $error): string
    {
        if ($this->serverToken) {
            $error = str_replace($this->serverToken, '[REDACTED_TOKEN]', $error);
        }

        $error = (string) preg_replace('/Bearer\s+[A-Za-z0-9_\-\.]+/i', 'Bearer [REDACTED]', $error);
        $error = (string) preg_replace('/\b\+?\d{9,15}\b/', '[REDACTED_PHONE]', $error);
        $error = (string) preg_replace('/[a-zA-Z0-9_\.\+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-\.]+/', '[REDACTED_EMAIL]', $error);

        return mb_substr($error, 0, 500);
    }
}
