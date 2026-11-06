<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\Business;
use App\Models\Contact;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsViceCeoFixtures;
use Tests\TestCase;

class TaskManagementTest extends TestCase
{
    use BuildsViceCeoFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBusiness();
    }

    public function test_ceo_creates_task_from_dashboard_with_activity_history(): void
    {
        $this->actingAs($this->ceo)->post('/tasks', [
            'title' => 'Follow-up Fatihah Cake House',
            'priority' => 'high',
            'strategic_importance' => 4,
            'revenue_potential' => 4800,
            'deadline' => now()->addDay()->format('Y-m-d\TH:i'),
        ])->assertRedirect();

        $task = Task::firstOrFail();
        $this->assertSame($this->business->id, $task->business_id);
        $this->assertSame(TaskStatus::Pending, $task->status);
        $this->assertSame('dashboard', $task->source->value);
        $this->assertDatabaseHas('task_activities', ['task_id' => $task->id, 'action' => 'created', 'channel' => 'dashboard']);
    }

    public function test_duplicate_open_task_is_not_created_twice(): void
    {
        $payload = ['title' => 'Hubungi Derol', 'priority' => 'medium', 'strategic_importance' => 3];

        $this->actingAs($this->ceo)->post('/tasks', $payload);
        $this->actingAs($this->ceo)->post('/tasks', ['title' => '  hubungi   DEROL ', 'priority' => 'medium', 'strategic_importance' => 3])
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'already exists'));

        $this->assertSame(1, Task::count());
    }

    public function test_status_change_is_logged_and_visible(): void
    {
        $task = $this->task(['title' => 'Uji aplikasi CashCow']);

        $this->actingAs($this->ceo)->patch("/tasks/{$task->id}/status", ['status' => 'completed', 'completion_evidence' => 'Tested OK'])->assertRedirect();

        $task->refresh();
        $this->assertSame(TaskStatus::Completed, $task->status);
        $this->assertNotNull($task->completed_at);
        $this->assertSame('Tested OK', $task->completion_evidence);
        $this->assertDatabaseHas('task_activities', ['task_id' => $task->id, 'from_status' => 'pending', 'to_status' => 'completed']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'task.status_changed', 'subject_id' => $task->id]);
    }

    public function test_tenant_isolation_for_tasks(): void
    {
        $other = Business::factory()->create();
        $foreignTask = Task::factory()->create(['business_id' => $other->id, 'title' => 'Secret competitor task']);

        $this->actingAs($this->ceo)->get("/tasks/{$foreignTask->id}")->assertForbidden();
        $this->actingAs($this->ceo)->patch("/tasks/{$foreignTask->id}/status", ['status' => 'completed'])->assertForbidden();
        $this->actingAs($this->ceo)->get('/tasks?status=all')->assertDontSee('Secret competitor task');

        $this->assertSame(TaskStatus::Pending, $foreignTask->fresh()->status);
    }

    public function test_cannot_link_another_tenants_customer(): void
    {
        $foreignContact = Contact::factory()->create(['business_id' => Business::factory()->create()->id]);

        $this->actingAs($this->ceo)->post('/tasks', [
            'title' => 'Sneaky', 'priority' => 'low', 'strategic_importance' => 1, 'contact_id' => $foreignContact->id,
        ])->assertSessionHasErrors('contact_id');
    }

    public function test_dashboard_and_all_pages_render(): void
    {
        $contact = $this->contact(['name' => 'Fatihah', 'company' => 'Fatihah Cake House']);
        $task = $this->task(['title' => 'Follow-up Fatihah Cake House', 'contact_id' => $contact->id, 'deadline' => now()->subDay(), 'revenue_potential' => 4800]);

        $this->actingAs($this->ceo)->get('/dashboard')
            ->assertOk()
            ->assertSee("Today's Top 3 Priorities", false)
            ->assertSee('Follow-up Fatihah Cake House')
            ->assertSee('System Health');

        foreach (['/tasks', "/tasks/{$task->id}", '/tasks/create', "/tasks/{$task->id}/edit", '/decision-room', '/decision-room/new', '/communications', '/automations', '/contacts', '/contacts/create', '/memory', '/settings', '/audit'] as $url) {
            $this->actingAs($this->ceo)->get($url)->assertOk();
        }
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Log in');
    }

    public function test_registration_creates_business_ceo_and_disabled_automations(): void
    {
        $this->post('/register', [
            'business_name' => 'Kedai Baru', 'name' => 'Aminah', 'email' => 'aminah@example.com',
            'password' => 'secret-password-1', 'password_confirmation' => 'secret-password-1',
        ])->assertRedirect('/dashboard');

        $user = User::where('email', 'aminah@example.com')->firstOrFail();
        $this->assertTrue($user->isCeo());
        $this->assertSame('Kedai Baru', $user->business->name);
        $this->assertSame(4, $user->business->automations()->count());
        $this->assertSame(0, $user->business->automations()->where('enabled', true)->count());
    }
}
