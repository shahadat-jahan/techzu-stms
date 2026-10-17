<?php

namespace App\Providers;

use App\Models\Attachment;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use App\Policies\AttachmentPolicy;
use App\Policies\ReplyPolicy;
use App\Policies\TicketPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Explicit registration: auto-discovery would look for TicketReplyPolicy,
        // but the policy is intentionally named ReplyPolicy.
        Gate::policy(Ticket::class, TicketPolicy::class);
        Gate::policy(TicketReply::class, ReplyPolicy::class);
        Gate::policy(Attachment::class, AttachmentPolicy::class);

        Gate::define('manageTickets', fn (User $user): bool => $user->isAgent() || $user->isAdmin());
        Gate::define('manageSlaSettings', fn (User $user): bool => $user->isAdmin());
        Gate::define('manageImports', fn (User $user): bool => $user->isAdmin());
    }
}
