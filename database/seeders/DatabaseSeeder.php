<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\User;
use App\Services\Executive\AutomationService;
use App\Services\Executive\TaskService;
use App\Support\Actor;
use Illuminate\Database\Seeder;

/**
 * Demo data that mirrors the example workflow (Fatihah Cake House, Derol Pasir Puteh,
 * CashCow / Vern POS testing). Login: ceo@dynofocus.test / password
 */
class DatabaseSeeder extends Seeder
{
    public function run(AutomationService $automations, TaskService $tasks): void
    {
        $business = Business::create([
            'name' => 'Dynopos Sdn Bhd',
            'timezone' => 'Asia/Kuala_Lumpur',
            'profile' => 'POS and business software for Malaysian SMEs (bakeries, F&B, retail).',
            'goals' => 'Close 10 new POS customers this month. Keep monthly cashflow positive.',
        ]);
        $automations->ensureDefaults($business);

        $ceo = new User(['name' => 'CEO', 'email' => 'ceo@dynofocus.test', 'password' => 'password']);
        $ceo->business_id = $business->id;
        $ceo->role = User::ROLE_CEO;
        $ceo->save();

        $actor = Actor::user($ceo);
        $project = $business->projects()->create(['name' => 'POS sales pipeline']);

        $fatihah = $business->contacts()->create(['name' => 'Fatihah', 'company' => 'Fatihah Cake House', 'phone' => '0123456701', 'whatsapp_opt_in_at' => now(), 'notes' => 'Interested in POS for 2 outlets.']);
        $derol = $business->contacts()->create(['name' => 'Derol', 'company' => 'Derol Pasir Puteh', 'phone' => '0123456702', 'whatsapp_opt_in_at' => now()]);

        // Demo only: pretend both customers messaged us today, so the 24-hour WhatsApp window is open.
        $fatihah->forceFill(['whatsapp_last_inbound_at' => now()])->save();
        $derol->forceFill(['whatsapp_last_inbound_at' => now()])->save();

        $tasks->create($business, ['title' => 'Follow-up Fatihah Cake House', 'contact_id' => $fatihah->id, 'project_id' => $project->id, 'priority' => 'high', 'deadline' => now()->subDay(), 'revenue_potential' => 4800, 'strategic_importance' => 4], $actor);
        $tasks->create($business, ['title' => 'Hubungi Derol Pasir Puteh', 'contact_id' => $derol->id, 'project_id' => $project->id, 'priority' => 'high', 'deadline' => now()->addHours(6), 'revenue_potential' => 3200, 'strategic_importance' => 4], $actor);
        $tasks->create($business, ['title' => 'Uji aplikasi CashCow dan Vern POS', 'priority' => 'medium', 'deadline' => now()->addDays(2), 'strategic_importance' => 3], $actor);

        $business->memories()->create(['category' => 'goal', 'title' => 'Monthly target', 'content' => 'Close 10 new POS customers this month.', 'is_verified' => true, 'source' => 'ceo', 'created_by' => $ceo->id]);
    }
}
