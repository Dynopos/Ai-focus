<?php

namespace App\Services\Messaging;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/** Official Meta WhatsApp Business Cloud API (graph.facebook.com). */
class MetaCloudWhatsAppGateway implements WhatsAppGateway
{
    public function __construct(
        private readonly string $accessToken,
        private readonly string $graphVersion = 'v23.0',
    ) {}

    public function name(): string
    {
        return 'meta';
    }

    public function sendText(string $phoneNumberId, string $to, string $body): SendResult
    {
        return $this->post($phoneNumberId, [
            'to' => $to,
            'type' => 'text',
            'text' => ['preview_url' => false, 'body' => mb_substr($body, 0, 4096)],
        ]);
    }

    public function sendTemplate(string $phoneNumberId, string $to, string $template, string $language, array $bodyParams = []): SendResult
    {
        $payload = [
            'to' => $to,
            'type' => 'template',
            'template' => ['name' => $template, 'language' => ['code' => $language]],
        ];

        if ($bodyParams !== []) {
            $payload['template']['components'] = [[
                'type' => 'body',
                'parameters' => array_map(
                    // Template parameters cannot contain newlines/tabs or 4+ consecutive spaces.
                    fn (string $p) => ['type' => 'text', 'text' => mb_substr(preg_replace('/\s+/u', ' ', $p), 0, 1024)],
                    $bodyParams,
                ),
            ]];
        }

        return $this->post($phoneNumberId, $payload);
    }

    public function sendButtons(string $phoneNumberId, string $to, string $body, array $buttons): SendResult
    {
        $rows = [];
        foreach (array_slice($buttons, 0, 3, true) as $id => $title) {
            $rows[] = ['type' => 'reply', 'reply' => ['id' => mb_substr($id, 0, 256), 'title' => mb_substr($title, 0, 20)]];
        }

        return $this->post($phoneNumberId, [
            'to' => $to,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => ['text' => mb_substr($body, 0, 1024)],
                'action' => ['buttons' => $rows],
            ],
        ]);
    }

    private function post(string $phoneNumberId, array $payload): SendResult
    {
        if ($this->accessToken === '' || $phoneNumberId === '') {
            return SendResult::failed('WhatsApp is not configured (missing access token or phone number id).');
        }

        try {
            $response = Http::withToken($this->accessToken)
                ->acceptJson()
                ->timeout(20)
                ->post("https://graph.facebook.com/{$this->graphVersion}/{$phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                ] + $payload);
        } catch (ConnectionException $e) {
            return SendResult::failed('Connection error: '.$e->getMessage(), [], true);
        }

        return $this->toResult($response);
    }

    private function toResult(Response $response): SendResult
    {
        $json = $response->json() ?? [];

        if ($response->successful() && isset($json['messages'][0]['id'])) {
            return SendResult::sent($json['messages'][0]['id'], $json);
        }

        $error = $json['error']['message'] ?? ('HTTP '.$response->status());
        $code = $json['error']['code'] ?? null;
        // Rate limits (4, 80007, 130429, 131056) and 5xx are worth retrying.
        $retryable = $response->serverError() || $response->status() === 429 || in_array($code, [4, 80007, 130429, 131056], true);

        return SendResult::failed(($code ? "[{$code}] " : '').$error, $json, $retryable);
    }
}
