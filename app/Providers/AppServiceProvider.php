<?php

namespace App\Providers;

use App\Models\Approval;
use App\Models\CommunicationLog;
use App\Models\Contact;
use App\Models\Memory;
use App\Models\Task;
use App\Models\User;
use App\Policies\ApprovalPolicy;
use App\Policies\CommunicationLogPolicy;
use App\Policies\ContactPolicy;
use App\Policies\MemoryPolicy;
use App\Policies\TaskPolicy;
use App\Services\Ai\AiClient;
use App\Services\Ai\AnthropicAiClient;
use App\Services\Ai\NullAiClient;
use App\Services\Messaging\LogWhatsAppGateway;
use App\Services\Messaging\MetaCloudWhatsAppGateway;
use App\Services\Messaging\WhatsAppGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AiClient::class, function () {
            $config = config('vice_ceo.ai');

            if ($config['provider'] === 'anthropic' && ! empty($config['api_key'])) {
                return new AnthropicAiClient($config['api_key'], $config['model'], $config['effort'], $config['timeout']);
            }

            return new NullAiClient;
        });

        $this->app->singleton(WhatsAppGateway::class, function () {
            $config = config('vice_ceo.whatsapp');

            return $config['driver'] === 'meta'
                ? new MetaCloudWhatsAppGateway((string) $config['access_token'], (string) $config['graph_version'])
                : new LogWhatsAppGateway;
        });
    }

    public function boot(): void
    {
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(Approval::class, ApprovalPolicy::class);
        Gate::policy(Contact::class, ContactPolicy::class);
        Gate::policy(Memory::class, MemoryPolicy::class);
        Gate::policy(CommunicationLog::class, CommunicationLogPolicy::class);

        Gate::define('manage-business', fn (User $user) => $user->isCeo() && $user->business_id !== null);

        RateLimiter::for('whatsapp-webhook', fn (Request $request) => Limit::perMinute(600)->by($request->ip()));
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(10)->by($request->ip().'|'.$request->input('email')));
    }
}
