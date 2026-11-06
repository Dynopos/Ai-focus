<?php

namespace App\Services\Messaging;

final class SendResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly ?string $messageId = null,
        public readonly array $response = [],
        public readonly ?string $error = null,
        public readonly bool $retryable = false,
    ) {}

    public static function sent(?string $messageId, array $response = []): self
    {
        return new self(true, $messageId, $response);
    }

    public static function failed(string $error, array $response = [], bool $retryable = false): self
    {
        return new self(false, null, $response, $error, $retryable);
    }
}
