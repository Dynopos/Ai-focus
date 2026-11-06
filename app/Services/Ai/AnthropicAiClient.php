<?php

namespace App\Services\Ai;

use Anthropic\Client;
use Anthropic\Core\Exceptions\AnthropicException;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\RateLimitException;
use Anthropic\Messages\Message;
use Illuminate\Support\Facades\Log;

/**
 * Claude via the official Anthropic PHP SDK. Every failure returns null so the
 * application keeps working on deterministic fallbacks.
 */
class AnthropicAiClient implements AiClient
{
    private ?Client $client = null;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly string $effort = 'low',
        private readonly int $timeout = 60,
    ) {}

    public function available(): bool
    {
        return $this->apiKey !== '';
    }

    public function text(string $system, string $prompt, int $maxTokens = 2000): ?string
    {
        $message = $this->send($system, $prompt, $maxTokens);

        return $message ? $this->firstText($message) : null;
    }

    public function json(string $system, string $prompt, array $schema, int $maxTokens = 2000): ?array
    {
        $message = $this->send($system, $prompt, $maxTokens, [
            'format' => ['type' => 'json_schema', 'schema' => $schema],
        ]);

        $text = $message ? $this->firstText($message) : null;
        if ($text === null) {
            return null;
        }

        $decoded = json_decode($text, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function send(string $system, string $prompt, int $maxTokens, array $outputConfig = []): ?Message
    {
        if (! $this->available()) {
            return null;
        }

        try {
            $message = $this->client()->messages->create(
                model: $this->model,
                maxTokens: $maxTokens,
                system: $system,
                messages: [['role' => 'user', 'content' => $prompt]],
                outputConfig: ['effort' => $this->effort] + $outputConfig,
            );
        } catch (RateLimitException $e) {
            Log::warning('Claude rate limited', ['message' => $e->getMessage()]);

            return null;
        } catch (APIStatusException $e) {
            Log::error('Claude API error', ['type' => $e->type?->value, 'message' => $e->getMessage()]);

            return null;
        } catch (APIConnectionException $e) {
            Log::warning('Claude connection error', ['message' => $e->getMessage()]);

            return null;
        } catch (AnthropicException $e) {
            Log::error('Claude client error', ['message' => $e->getMessage()]);

            return null;
        }

        if ($message->stopReason === 'refusal') {
            Log::notice('Claude declined a request', ['category' => $message->stopDetails?->category]);

            return null;
        }

        return $message;
    }

    private function firstText(Message $message): ?string
    {
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                return trim($block->text);
            }
        }

        return null;
    }

    private function client(): Client
    {
        return $this->client ??= new Client(apiKey: $this->apiKey, requestOptions: ['timeout' => $this->timeout]);
    }
}
