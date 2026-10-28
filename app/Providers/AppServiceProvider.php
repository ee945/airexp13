<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Validator;
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
        // 原项目前端是 Bootstrap 3，分页链接沿用 bootstrap-3 版式
        Paginator::useBootstrapThree();

        // 总运单号模七校验（原项目 AppServiceProvider 里的自定义规则）
        Validator::extend('ismawb', function ($attribute, $value) {
            $mawb7 = substr((string) $value, 4, 7);
            $mawb8 = substr((string) $value, 11, 1);

            return ($mawb7 % 7) == $mawb8;
        });
    }
}
