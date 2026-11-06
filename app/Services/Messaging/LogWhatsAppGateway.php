<?php

namespace App\Services\Messaging;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Development / test transport: nothing leaves the server. */
class LogWhatsAppGateway implements WhatsAppGateway
{
    /** @var list<array<string, mixed>> */
    public array $sent = [];

    public function name(): string
    {
        return 'log';
    }

    public function sendText(string $phoneNumberId, string $to, string $body): SendResult
    {
        return $this->record(['type' => 'text', 'to' => $to, 'body' => $body]);
    }

    public function sendTemplate(string $phoneNumberId, string $to, string $template, string $language, array $bodyParams = []): SendResult
    {
        return $this->record(['type' => 'template', 'to' => $to, 'template' => $template, 'language' => $language, 'params' => $bodyParams]);
    }

    public function sendButtons(string $phoneNumberId, string $to, string $body, array $buttons): SendResult
    {
        return $this->record(['type' => 'buttons', 'to' => $to, 'body' => $body, 'buttons' => $buttons]);
    }

    private function record(array $message): SendResult
    {
        $id = 'wamid.LOG.'.Str::upper(Str::random(16));
        $this->sent[] = $message + ['id' => $id];
        Log::info('[whatsapp:log] outbound message (not sent)', $message);

        return SendResult::sent($id, ['driver' => 'log']);
    }
}
