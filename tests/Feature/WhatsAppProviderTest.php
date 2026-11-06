<?php

namespace Tests\Feature;

use App\Jobs\SendCommunicationJob;
use App\Models\CommunicationLog;
use App\Services\Messaging\CommunicationService;
use App\Services\Messaging\MetaCloudWhatsAppGateway;
use App\Services\Messaging\WhatsAppGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsViceCeoFixtures;
use Tests\TestCase;

class WhatsAppProviderTest extends TestCase
{
    use BuildsViceCeoFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBusiness();
        $this->app->instance(WhatsAppGateway::class, new MetaCloudWhatsAppGateway('test-token', 'v23.0'));
    }

    private function queueReply(string $key = 'k1'): CommunicationLog
    {
        return app(CommunicationService::class)->queue($this->business, 'whatsapp', [
            'type' => 'ceo_reply', 'recipient' => '60123456789', 'body' => 'Hello CEO', 'idempotency_key' => $key,
        ]);
    }

    public function test_meta_cloud_api_success(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.OK']]])]);

        $log = $this->queueReply();

        $this->assertSame('sent', $log->fresh()->status);
        $this->assertSame('wamid.OK', $log->fresh()->provider_message_id);
        Http::assertSent(fn ($r) => $r->url() === 'https://graph.facebook.com/v23.0/PNID-1/messages'
            && $r->hasHeader('Authorization', 'Bearer test-token')
            && $r['type'] === 'text' && $r['to'] === '60123456789');
    }

    public function test_server_error_is_released_for_retry_then_recorded_as_failed(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Service unavailable', 'code' => 2]], 503)]);

        $log = app(CommunicationService::class)->queue($this->business, 'whatsapp', [
            'type' => 'ceo_reply', 'recipient' => '60123456789', 'body' => 'Hello CEO', 'idempotency_key' => 'k503',
        ], dispatch: false);

        $job = (new SendCommunicationJob($log->id))->withFakeQueueInteractions();
        $job->handle(app(CommunicationService::class));

        $job->assertReleased(delay: 60); // transient error: retried with backoff
        $log->refresh();
        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('Service unavailable', $log->error);
        $this->actingAs($this->ceo)->get('/communications?status=failed')->assertSee('Service unavailable');
    }

    public function test_permanent_error_is_not_retried_and_can_be_retried_manually(): void
    {
        Http::fakeSequence('graph.facebook.com/*')
            ->push(['error' => ['message' => 'Invalid parameter', 'code' => 100]], 400)
            ->push(['messages' => [['id' => 'wamid.RETRY']]]);

        $log = $this->queueReply();
        $this->assertSame('failed', $log->fresh()->status);
        $this->assertSame(1, $log->fresh()->attempts);

        $this->actingAs($this->ceo)->post("/communications/{$log->id}/retry")->assertSessionHasNoErrors();

        $this->assertSame('sent', $log->fresh()->status);
        $this->assertSame('wamid.RETRY', $log->fresh()->provider_message_id);
    }

    public function test_idempotency_key_prevents_duplicate_sends(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.ONCE']]])]);

        $this->queueReply('same-key');
        $this->queueReply('same-key');

        Http::assertSentCount(1);
        $this->assertSame(1, CommunicationLog::count());
    }

    public function test_template_used_outside_service_window(): void
    {
        config(['vice_ceo.whatsapp.templates.ceo_update' => 'dyno_ceo_update']);
        $this->ceo->forceFill(['whatsapp_last_inbound_at' => now()->subDays(2)])->save();
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.T']]])]);

        app(CommunicationService::class)->queue($this->business, 'whatsapp', [
            'type' => 'wa_priorities', 'recipient' => '60123456789', 'body' => "Line 1\nLine 2", 'template' => 'ceo_update',
        ]);

        Http::assertSent(fn ($r) => $r['type'] === 'template'
            && $r['template']['name'] === 'dyno_ceo_update'
            && $r['template']['components'][0]['parameters'][0]['text'] === 'Line 1 Line 2');
    }

    public function test_dashboard_shows_failed_communications_in_system_health(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Bad', 'code' => 100]], 400)]);
        $this->queueReply();

        $this->actingAs($this->ceo)->get('/dashboard')->assertSee('1 failed messages');
    }
}
