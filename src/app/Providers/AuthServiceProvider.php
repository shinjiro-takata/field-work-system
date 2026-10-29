<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        // ルートで can:admin-only と指定したときに、管理者かどうかを判定するルールです。
        Gate::define('admin-only', function (User $user) {
            // データベースの値が 0/1 や文字列でも正しく判定できるよう、真偽値に変換します。
            return (bool) $user->is_admin;
        });
    }
}
