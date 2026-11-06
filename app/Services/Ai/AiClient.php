<?php

namespace App\Services\Ai;

interface AiClient
{
    public function available(): bool;

    /** Free-text completion. Returns null when the AI is unavailable or fails. */
    public function text(string $system, string $prompt, int $maxTokens = 2000): ?string;

    /**
     * JSON completion constrained to $schema (JSON Schema object).
     *
     * @return array<string, mixed>|null
     */
    public function json(string $system, string $prompt, array $schema, int $maxTokens = 2000): ?array;
}
