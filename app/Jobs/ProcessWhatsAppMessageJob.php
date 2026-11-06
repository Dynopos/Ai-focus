<?php

namespace App\Jobs;

use App\Models\WebhookEvent;
use App\Services\WhatsApp\InboundMessageProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessWhatsAppMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $webhookEventId) {}

    public function handle(InboundMessageProcessor $processor): void
    {
        // Claim the event: processed exactly once even if the job is duplicated.
        $claimed = WebhookEvent::whereKey($this->webhookEventId)->where('status', 'received')->update(['status' => 'processing']);
        if ($claimed === 0) {
            return;
        }

        $event = WebhookEvent::findOrFail($this->webhookEventId);

        try {
            $status = $processor->process($event);
            $event->forceFill(['status' => $status, 'processed_at' => now()])->save();
        } catch (Throwable $e) {
            $event->forceFill(['status' => 'failed', 'error' => $e->getMessage(), 'processed_at' => now()])->save();

            throw $e;
        }
    }
}
