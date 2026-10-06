<?php

namespace App\Providers;

use App\Models\InternalUserInvitation;
use App\Models\User;
use App\Observers\InvitationObserver;
use App\Observers\UserObserver;
use Illuminate\Support\ServiceProvider;

class AuditServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        User::observe(UserObserver::class);
        InternalUserInvitation::observe(InvitationObserver::class);
    }
}
