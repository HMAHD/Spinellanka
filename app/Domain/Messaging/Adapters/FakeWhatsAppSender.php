<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Adapters;

use App\Domain\Messaging\Contracts\WhatsAppSender;
use App\Domain\Messaging\DTOs\DeliveryResult;
use App\Domain\Messaging\DTOs\WhatsAppMessage;
use PHPUnit\Framework\Assert;

class FakeWhatsAppSender implements WhatsAppSender
{
    /**
     * @var list<WhatsAppMessage>
     */
    private array $sentMessages = [];

    /**
     * @var list<DeliveryResult>
     */
    private array $resultSequence = [];

    private ?DeliveryResult $defaultResult = null;

    public function __construct()
    {
        $this->defaultResult = DeliveryResult::accepted('fake_wam_'.uniqid());
    }

    public function send(WhatsAppMessage $message): DeliveryResult
    {
        $this->sentMessages[] = $message;

        if (! empty($this->resultSequence)) {
            return array_shift($this->resultSequence);
        }

        return $this->defaultResult ?? DeliveryResult::accepted('fake_wam_'.uniqid());
    }

    /**
     * @return list<WhatsAppMessage>
     */
    public function getSentMessages(): array
    {
        return $this->sentMessages;
    }

    public function alwaysSucceed(?string $messageId = null): self
    {
        $this->defaultResult = DeliveryResult::accepted($messageId ?? 'fake_wam_'.uniqid());

        return $this;
    }

    public function fakeNextResult(DeliveryResult $result): self
    {
        $this->resultSequence = [$result];

        return $this;
    }

    public function alwaysFail(string $errorCode, string $errorMessage, bool $isRetryable = false): self
    {
        $this->defaultResult = DeliveryResult::failed($errorCode, $errorMessage, $isRetryable);

        return $this;
    }

    public function alwaysTimeout(string $errorCode = 'FAKE_TIMEOUT', string $errorMessage = 'Fake socket timeout'): self
    {
        $this->defaultResult = DeliveryResult::unknown($errorCode, $errorMessage);

        return $this;
    }

    /**
     * @param  list<DeliveryResult>  $results
     */
    public function sequence(array $results): self
    {
        $this->resultSequence = $results;

        return $this;
    }

    public function reset(): void
    {
        $this->sentMessages = [];
        $this->resultSequence = [];
        $this->defaultResult = DeliveryResult::accepted('fake_wam_'.uniqid());
    }

    public function assertSentTo(string $phone, ?string $templateName = null): void
    {
        $matched = array_filter($this->sentMessages, function (WhatsAppMessage $msg) use ($phone, $templateName): bool {
            $phoneMatches = str_contains($msg->recipientPhone, $phone) || $msg->recipientPhone === $phone;
            if ($templateName !== null) {
                return $phoneMatches && $msg->templateName === $templateName;
            }

            return $phoneMatches;
        });

        Assert::assertNotEmpty($matched, "Failed asserting that WhatsApp message was sent to {$phone}".($templateName ? " with template {$templateName}" : ''));
    }

    public function assertNothingSent(): void
    {
        Assert::assertEmpty($this->sentMessages, 'Expected zero WhatsApp messages sent, but '.count($this->sentMessages).' were sent.');
    }
}
