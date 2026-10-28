<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $table = 'users';

    /**
     * 原系统 users 表结构（非 Laravel 默认）：
     * id, name, password, email, nick, company, lastlogin, lastip, remark, level,
     * remember_token, created_at, updated_at
     *
     * 登录用户名是 name（不是 email），且只有 level = 1 的账号允许登录。
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'nick',
        'company',
        'remark',
        'level',
        'lastip',
        'lastlogin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'level' => 'integer',
            'lastlogin' => 'datetime',
        ];
    }

    /**
     * 是否允许登录（对应原 LoginController::credentials 里的 level = 1 约束）。
     */
    public function canLogin(): bool
    {
        return (int) $this->level === 1;
    }
}
