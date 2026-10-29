<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * 用户管理。
 *
 * 原项目 app/Http/Controllers/UserController.php 只有一个空的构造函数，
 * user/list、user/add、user/edit、user/del 四条路由指向不存在的方法，访问即 500。
 * 这里按 users 表的实际结构补上增删改查，规则与原站登录逻辑保持一致：
 *
 *  - 登录名是 users.name（不是 email），level = 1 才能登录（LoginController 已约束）
 *  - 只有管理员（level = 1）能进本模块
 *  - **lastlogin 列带 ON UPDATE CURRENT_TIMESTAMP**：MySQL 对任何 UPDATE 都会
 *    把它刷成当前时间，所以每次更新都必须显式写回原值，否则改个昵称就把
 *    "最后登录时间" 冲掉了
 *  - password 用 bcrypt（BCRYPT_ROUNDS=10），与库里既有的 $2y$ 哈希兼容
 */
class UserController extends Controller
{
    /**
     * 列表页搜索字段（都是模糊匹配）。
     */
    private const SEARCH_COLUMNS = ['name', 'nick', 'email', 'company'];

    public function lists(Request $request)
    {
        $title = '用户列表';
        $search = $request->all();

        $query = User::query();

        foreach (self::SEARCH_COLUMNS as $column) {
            $key = 'search_'.$column;
            if (isset($search[$key]) && $search[$key] !== '') {
                $query->where($column, 'like', '%'.$search[$key].'%');
            }
        }

        if (isset($search['search_level']) && $search['search_level'] !== '') {
            $query->where('level', (int) $search['search_level']);
        }

        $perpage = isset($search['perpage']) && (int) $search['perpage'] > 0
            ? min((int) $search['perpage'], 200)
            : 20;

        $users = $query->orderByDesc('level')
            ->orderBy('name')
            ->paginate($perpage)
            ->withQueryString();

        $total = User::count();
        $enabled = User::where('level', 1)->count();

        return view(theme('user.list'), compact('users', 'title', 'total', 'enabled'))
            ->with($search);
    }

    public function add()
    {
        $title = '添加用户';

        return view(theme('user.form'), compact('title'));
    }

    public function create(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9_.@-]+$/', 'unique:users,name'],
            'nick' => ['nullable', 'string', 'max:64'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'company' => ['nullable', 'string', 'max:64'],
            'level' => ['required', 'in:0,1'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'remark' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'name' => '登录名',
            'nick' => '昵称',
            'email' => '邮箱',
            'company' => '公司',
            'level' => '状态',
            'password' => '密码',
            'remark' => '备注',
        ]);

        User::create([
            'name' => $data['name'],
            'nick' => $data['nick'] ?? '',
            'email' => $data['email'],
            'company' => $data['company'] ?? '',
            'level' => (int) $data['level'],
            'password' => Hash::make($data['password']),
            'lastip' => '',
            // 新账号还没登录过：列是 NOT NULL DEFAULT CURRENT_TIMESTAMP，
            // 不显式写零日期就会显示成"创建时刻=最后登录"。
            'lastlogin' => '0000-00-00 00:00:00',
            'remark' => $data['remark'] ?? '',
        ]);

        return redirect()->route('user_list', ['addno' => $data['name']]);
    }

    public function show($name)
    {
        $user = User::where('name', $name)->first();

        if (! $user) {
            return redirect()->route('user_list', ['missing' => $name]);
        }

        $title = '修改用户';

        return view(theme('user.form'), compact('user', 'title'));
    }

    public function update(Request $request, $name)
    {
        $user = User::where('name', $name)->first();

        if (! $user) {
            return redirect()->route('user_list', ['missing' => $name]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9_.@-]+$/', 'unique:users,name,'.$user->id],
            'nick' => ['nullable', 'string', 'max:64'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'company' => ['nullable', 'string', 'max:64'],
            'level' => ['required', 'in:0,1'],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
            'remark' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'name' => '登录名',
            'nick' => '昵称',
            'email' => '邮箱',
            'company' => '公司',
            'level' => '状态',
            'password' => '密码',
            'remark' => '备注',
        ]);

        if ($this->blockedByGuard($user, (int) $data['level'], $data['name'])) {
            return redirect()->route('user_view', ['name' => $user->name]);
        }

        $attributes = [
            'name' => $data['name'],
            'nick' => $data['nick'] ?? '',
            'email' => $data['email'],
            'company' => $data['company'] ?? '',
            'level' => (int) $data['level'],
            'remark' => $data['remark'] ?? '',
            // 显式写回，压住列上的 ON UPDATE CURRENT_TIMESTAMP
            'lastlogin' => $this->rawLastlogin($user),
        ];

        if (! empty($data['password'])) {
            $attributes['password'] = Hash::make($data['password']);
        }

        User::where('id', $user->id)->update($attributes);

        return redirect()->route('user_view', ['name' => $data['name'], 'update' => 'yes']);
    }

    /**
     * 兼容原站表单的 POST user/edit（提交整表，按 name 定位）。
     */
    public function edit(Request $request)
    {
        $name = (string) $request->input('name', '');

        if ($name === '') {
            return redirect()->route('user_list');
        }

        return $this->update($request, $name);
    }

    public function status(Request $request, $name)
    {
        $user = User::where('name', $name)->first();

        if (! $user) {
            return redirect()->route('user_list', ['missing' => $name]);
        }

        $target = (int) $user->level === 1 ? 0 : 1;

        if ($this->blockedByGuard($user, $target, $user->name)) {
            return redirect()->route('user_list');
        }

        User::where('id', $user->id)->update([
            'level' => $target,
            'lastlogin' => $this->rawLastlogin($user),
        ]);

        return redirect()->route('user_list', [
            'status' => $target === 1 ? 'on' : 'off',
            'statusno' => $user->name,
        ]);
    }

    /**
     * 重置密码：生成随机临时密码，回列表页提示一次（库里只存哈希）。
     */
    public function reset($name)
    {
        $user = User::where('name', $name)->first();

        if (! $user) {
            return redirect()->route('user_list', ['missing' => $name]);
        }

        $plain = Str::password(10, symbols: false);

        User::where('id', $user->id)->update([
            'password' => Hash::make($plain),
            'lastlogin' => $this->rawLastlogin($user),
        ]);

        // 明文密码走 session 闪存，不放 URL（避免落到访问日志/浏览器历史）
        return redirect()->route('user_list', ['resetno' => $user->name])
            ->with('resetpwd', $plain);
    }

    /**
     * 兼容原站表单的 POST user/del。
     */
    public function del(Request $request)
    {
        $name = (string) $request->input('name', $request->input('delno', ''));

        if ($name === '') {
            return redirect()->route('user_list', ['del' => 'no']);
        }

        $user = User::where('name', $name)->first();

        if (! $user) {
            return redirect()->route('user_list', ['del' => 'no', 'delno' => $name]);
        }

        if ($this->blockedByGuard($user, 0, $name, delete: true)) {
            return redirect()->route('user_list');
        }

        User::where('id', $user->id)->delete();

        return redirect()->route('user_list', ['del' => 'yes', 'delno' => $name]);
    }

    /**
     * 取库里 lastlogin 的原始字符串（不走 datetime cast，零日期也不会被改写）。
     */
    private function rawLastlogin(User $user): string
    {
        $raw = $user->getRawOriginal('lastlogin');

        if (is_string($raw) && $raw !== '') {
            return $raw;
        }

        return '0000-00-00 00:00:00';
    }

    /**
     * 三条保护：不能停用自己、不能删除自己、不能把最后一个管理员停用或删掉。
     *
     * 命中时给出提示并返回 true，调用方直接跳回列表。
     */
    private function blockedByGuard(User $user, int $targetLevel, string $newName, bool $delete = false): bool
    {
        $isSelf = Auth::id() === $user->id;

        if ($delete && $isSelf) {
            return $this->deny('不能删除当前登录的账号。');
        }

        if (! $delete && $isSelf && $targetLevel !== 1) {
            return $this->deny('不能停用当前登录的账号。');
        }

        $isLastAdmin = (int) $user->level === 1 && User::where('level', 1)->count() <= 1;

        if ($isLastAdmin && ($delete || $targetLevel !== 1)) {
            return $this->deny('这是最后一个启用状态的管理员账号，停用或删除后将无法登录系统。');
        }

        // 改登录名时也要避免与自己的旧名混淆：库里唯一约束已覆盖，这里只补一句人话
        if ($newName !== $user->name && User::where('name', $newName)->where('id', '!=', $user->id)->exists()) {
            return $this->deny('登录名 '.$newName.' 已被占用。');
        }

        return false;
    }

    private function deny(string $message): bool
    {
        session()->flash('error', $message);

        return true;
    }
}
