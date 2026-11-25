<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Adapters;

use App\Domain\Messaging\Contracts\TransactionalMailer;
use App\Domain\Messaging\DTOs\DeliveryResult;
use App\Domain\Messaging\DTOs\EmailMessage;
use PHPUnit\Framework\Assert;

class FakeTransactionalMailer implements TransactionalMailer
{
    /**
     * @var list<EmailMessage>
     */
    private array $sentMessages = [];

    /**
     * @var list<DeliveryResult>
     */
    private array $resultSequence = [];

    private ?DeliveryResult $defaultResult = null;

    public function __construct()
    {
        $this->defaultResult = DeliveryResult::accepted('fake_pm_'.uniqid());
    }

    public function send(EmailMessage $message): DeliveryResult
    {
        $this->sentMessages[] = $message;

        if (! empty($this->resultSequence)) {
            return array_shift($this->resultSequence);
        }

        return $this->defaultResult ?? DeliveryResult::accepted('fake_pm_'.uniqid());
    }

    /**
     * @return list<EmailMessage>
     */
    public function getSentMessages(): array
    {
        return $this->sentMessages;
    }

    public function alwaysSucceed(?string $messageId = null): self
    {
        $this->defaultResult = DeliveryResult::accepted($messageId ?? 'fake_pm_'.uniqid());

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

    public function alwaysTimeout(string $errorCode = 'FAKE_TIMEOUT', string $errorMessage = 'Fake email socket timeout'): self
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
        $this->defaultResult = DeliveryResult::accepted('fake_pm_'.uniqid());
    }

    public function assertSentTo(string $email, ?string $subject = null): void
    {
        $matched = array_filter($this->sentMessages, function (EmailMessage $msg) use ($email, $subject): bool {
            $emailMatches = strtolower(trim($msg->recipientEmail)) === strtolower(trim($email));
            if ($subject !== null) {
                return $emailMatches && str_contains($msg->subject, $subject);
            }

            return $emailMatches;
        });

        Assert::assertNotEmpty($matched, "Failed asserting that email was sent to {$email}".($subject ? " with subject {$subject}" : ''));
    }

    public function assertNothingSent(): void
    {
        Assert::assertEmpty($this->sentMessages, 'Expected zero emails sent, but '.count($this->sentMessages).' were sent.');
    }
}
