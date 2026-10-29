<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 用户管理模块的权限闸门：只有 level = 1（管理员）可进入。
 *
 * 原系统的登录本身只放行 level = 1 的账号，但账号被降级后原会话仍然存在，
 * 所以这里再挡一道，避免"停用自己/被别人降级后还能改用户"。
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user && (int) $user->level === 1, 403, '只有管理员可以管理用户。');

        return $next($request);
    }
}
