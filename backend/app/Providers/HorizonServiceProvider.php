<?php

namespace App\Providers;

use App\Models\Admin;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        if ($email = config('walk.ops.alert_email')) {
            Horizon::routeMailNotificationsTo($email);
        }
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        // Horizon shows job payloads (phone numbers in OTP jobs): super admins only, in every environment.
        Gate::define('viewHorizon', fn ($user = null) => auth('admin')->user() instanceof Admin && auth('admin')->user()->hasAbility('*'));
    }

    protected function authorization(): void
    {
        $this->gate();
        Horizon::auth(fn ($request) => Gate::check('viewHorizon', [$request->user()]));
    }
}
