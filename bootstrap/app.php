<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // 原项目（Laravel 5.4）的 Http\Kernel 里没有 TrimStrings /
        // ConvertEmptyStringsToNull，空表单字段会原样以 '' 落库。
        // 这两个全局中间件会把 '' 变成 null，而业务表大量 NOT NULL 且无默认值
        // （remark / addr / title…），在 MySQL 8 严格模式下会直接报 1048。
        // 为了让行为与原站一致，这里移除这两个中间件。
        $middleware->remove([
            \Illuminate\Foundation\Http\Middleware\TrimStrings::class,
            \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
