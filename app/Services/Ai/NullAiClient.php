<?php

namespace App\Services\Ai;

/** Used when no AI provider is configured; callers fall back to deterministic logic. */
class NullAiClient implements AiClient
{
    public function available(): bool
    {
        return false;
    }

    public function text(string $system, string $prompt, int $maxTokens = 2000): ?string
    {
        return null;
    }

    public function json(string $system, string $prompt, array $schema, int $maxTokens = 2000): ?array
    {
        return null;
    }
}
