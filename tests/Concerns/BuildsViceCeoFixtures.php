<?php

namespace Tests\Concerns;

use App\Models\Business;
use App\Models\Contact;
use App\Models\Task;
use App\Models\User;
use App\Services\Messaging\LogWhatsAppGateway;
use App\Services\Messaging\WhatsAppGateway;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

trait BuildsViceCeoFixtures
{
    protected Business $business;

    protected User $ceo;

    protected LogWhatsAppGateway $wa;

    protected function setUpBusiness(): void
    {
        $this->wa = new LogWhatsAppGateway;
        $this->app->instance(WhatsAppGateway::class, $this->wa);

        $this->business = Business::factory()->create(['name' => 'Dynopos', 'whatsapp_phone_number_id' => 'PNID-1']);
        $this->ceo = User::factory()->withVerifiedPhone('60123456789')->create([
            'business_id' => $this->business->id,
            'whatsapp_last_inbound_at' => now(),
        ]);
    }

    protected function contact(array $attrs = []): Contact
    {
        return Contact::factory()->create(['business_id' => $this->business->id] + $attrs);
    }

    protected function task(array $attrs = []): Task
    {
        return Task::factory()->create(['business_id' => $this->business->id] + $attrs);
    }

    /** POST a signed Meta webhook containing one text message. */
    protected function sendWhatsApp(string $text, string $from = '60123456789', array $extra = [], string $phoneNumberId = 'PNID-1', ?string $messageId = null): TestResponse
    {
        $message = array_replace_recursive([
            'from' => $from,
            'id' => $messageId ?? 'wamid.'.Str::random(20),
            'timestamp' => (string) now()->timestamp,
            'type' => 'text',
            'text' => ['body' => $text],
        ], $extra);

        return $this->postSignedWebhook($this->webhookPayload($phoneNumberId, ['messages' => [$message]]));
    }

    protected function webhookPayload(string $phoneNumberId, array $value): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'WABA',
                'changes' => [[
                    'field' => 'messages',
                    'value' => ['messaging_product' => 'whatsapp', 'metadata' => ['display_phone_number' => '60300000000', 'phone_number_id' => $phoneNumberId]] + $value,
                ]],
            ]],
        ];
    }

    protected function postSignedWebhook(array $payload, ?string $secret = 'test-app-secret'): TestResponse
    {
        $body = json_encode($payload);
        $signature = 'sha256='.hash_hmac('sha256', $body, (string) $secret);

        return $this->call('POST', '/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
        ], $body);
    }

    /** Last WhatsApp text the mock transport "sent" to a number. */
    protected function lastWhatsAppTo(string $to): ?string
    {
        $messages = array_values(array_filter($this->wa->sent, fn ($m) => $m['to'] === $to));
        $last = end($messages);

        return $last ? ($last['body'] ?? json_encode($last['params'] ?? [])) : null;
    }
}
