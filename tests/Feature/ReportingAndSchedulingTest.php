<?php

namespace Tests\Feature;

use App\Mail\HtmlMessage;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\CommunicationLog;
use App\Services\Executive\AutomationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsViceCeoFixtures;
use Tests\TestCase;

class ReportingAndSchedulingTest extends TestCase
{
    use BuildsViceCeoFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBusiness();
        app(AutomationService::class)->ensureDefaults($this->business);
    }

    private function enable(string $key): Automation
    {
        $automation = $this->business->automations()->where('key', $key)->firstOrFail();
        $automation->update(['enabled' => true]);

        return $automation;
    }

    /** 08:30 Asia/Kuala_Lumpur = 00:30 UTC */
    private function klTime(string $time): Carbon
    {
        return Carbon::parse('2026-11-09 '.$time, 'Asia/Kuala_Lumpur');
    }

    public function test_morning_email_uses_live_task_data(): void
    {
        Mail::fake();
        $this->task(['title' => 'Follow-up Fatihah Cake House', 'priority' => 'high', 'deadline' => now()->subDay(), 'revenue_potential' => 4800]);
        $this->task(['title' => 'Cancelled thing', 'status' => 'cancelled']);

        $log = app(AutomationService::class)->run($this->enable(Automation::MORNING_EMAIL), 'test');

        $this->assertSame('sent', $log->fresh()->status);
        $this->assertSame('morning_briefing', $log->type);
        $this->assertStringContainsString('Follow-up Fatihah Cake House', $log->body);
        $this->assertStringContainsString('RM4,800.00', $log->body);
        $this->assertStringNotContainsString('Cancelled thing', $log->body);
        Mail::assertSent(HtmlMessage::class, fn ($m) => $m->hasTo($this->ceo->email) && str_contains($m->subjectLine, 'Executive Briefing'));
    }

    public function test_evening_report_reflects_whatsapp_updates(): void
    {
        Mail::fake();
        $task = $this->task(['title' => 'Uji aplikasi CashCow dan Vern POS']);

        $this->sendWhatsApp('done T-'.$task->id);
        $log = app(AutomationService::class)->run($this->enable(Automation::EVENING_EMAIL), 'test');

        $html = $log->fresh()->body;
        $this->assertStringContainsString('Laporan Petang', $html);
        $this->assertMatchesRegularExpression('/Tugasan selesai hari ini.*Uji aplikasi CashCow dan Vern POS.*Tugasan masih pending/s', $html);
    }

    public function test_no_fabricated_cashflow_without_data(): void
    {
        Mail::fake();
        $this->task(['title' => 'Plain task']);

        $log = app(AutomationService::class)->run($this->enable(Automation::MORNING_EMAIL), 'test');

        $this->assertStringContainsString('Tiada data cashflow yang disahkan', $log->body);
        $this->assertStringNotContainsString('RM', strip_tags(explode('6. Cashflow', $log->body)[1] ?? ''));
    }

    public function test_master_switch_off_sends_nothing(): void
    {
        config(['vice_ceo.automations_enabled' => false]);
        $this->enable(Automation::MORNING_EMAIL);

        $this->assertSame(0, app(AutomationService::class)->dispatchDue($this->klTime('08:31')));
        $this->assertSame(0, CommunicationLog::count());
    }

    public function test_scheduler_runs_once_per_day_in_business_timezone(): void
    {
        Mail::fake();
        config(['vice_ceo.automations_enabled' => true]);
        $this->enable(Automation::MORNING_EMAIL);
        $service = app(AutomationService::class);

        $this->assertSame(0, $service->dispatchDue($this->klTime('08:29')), 'Not before 08:30 KL');
        $this->assertSame(1, $service->dispatchDue($this->klTime('08:30')));
        $this->assertSame(0, $service->dispatchDue($this->klTime('08:31')), 'No duplicate the same day');
        $this->assertSame(0, $service->dispatchDue($this->klTime('10:00')), 'Outside catch-up window');

        $this->assertSame(1, AutomationRun::where('status', 'completed')->count());
        $this->assertSame(1, CommunicationLog::where('type', 'morning_briefing')->count());
        Mail::assertSentCount(1);
    }

    public function test_disabled_automations_do_not_run(): void
    {
        config(['vice_ceo.automations_enabled' => true]);

        $this->assertSame(0, app(AutomationService::class)->dispatchDue($this->klTime('09:00')));
    }

    public function test_quiet_hours_skip_scheduled_messages(): void
    {
        config(['vice_ceo.automations_enabled' => true]);
        $this->business->update(['quiet_hours_start' => '08:00', 'quiet_hours_end' => '10:00']);
        $this->enable(Automation::WA_PRIORITIES);

        $this->assertSame(0, app(AutomationService::class)->dispatchDue($this->klTime('09:00')));
        $this->assertDatabaseHas('automation_runs', ['automation_key' => 'wa_priorities', 'status' => 'skipped']);
        $this->assertSame([], $this->wa->sent);
    }

    public function test_whatsapp_priorities_and_progress_messages(): void
    {
        config(['vice_ceo.automations_enabled' => true]);
        $this->task(['title' => 'Follow-up Fatihah Cake House', 'priority' => 'critical']);
        $this->enable(Automation::WA_PRIORITIES);
        $this->enable(Automation::WA_PROGRESS);
        $service = app(AutomationService::class);

        $service->dispatchDue($this->klTime('09:00'));
        $this->assertStringContainsString('Follow-up Fatihah Cake House', $this->lastWhatsAppTo('60123456789'));

        $service->dispatchDue($this->klTime('14:00'));
        $this->assertStringContainsString('Semakan kemajuan', $this->lastWhatsAppTo('60123456789'));
    }

    public function test_ceo_can_configure_automations(): void
    {
        $automation = $this->business->automations()->where('key', Automation::MORNING_EMAIL)->firstOrFail();

        $this->actingAs($this->ceo)->put('/automations', [
            'automations' => [$automation->id => ['enabled' => '1', 'send_time' => '07:45', 'channel' => 'email', 'respect_quiet_hours' => '0']],
            'quiet_hours_start' => '22:00',
            'quiet_hours_end' => '07:00',
            'timezone' => 'Asia/Kuala_Lumpur',
        ])->assertSessionHasNoErrors();

        $automation->refresh();
        $this->assertTrue($automation->enabled);
        $this->assertSame('07:45', $automation->send_time);
        $this->assertTrue($this->business->fresh()->isQuietTime(Carbon::parse('2026-11-09 23:00', 'Asia/Kuala_Lumpur')));
        $this->assertFalse($this->business->fresh()->isQuietTime(Carbon::parse('2026-11-09 12:00', 'Asia/Kuala_Lumpur')));
    }
}
