<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * 登录页。
     */
    public function showLoginForm()
    {
        return view(theme('auth.login'));
    }

    /**
     * 处理登录。
     *
     * 与原系统行为一致：
     *  - 用户名是 users.name（不是 email）
     *  - 只有 level = 1 的账号可登录
     *  - 登录成功写入 lastip（lastlogin 由表定义的 ON UPDATE CURRENT_TIMESTAMP 自动刷新）
     */
    public function login(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $credentials = [
            'name' => $data['name'],
            'password' => $data['password'],
            'level' => 1,
        ];

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'name' => '用户名或密码不正确，或该账号未启用。',
            ]);
        }

        $request->session()->regenerate();

        $user = Auth::user();
        $user->lastip = $request->ip();
        $user->saveQuietly();

        return redirect()->intended(route('home'));
    }

    /**
     * 退出登录。
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
