<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 用户管理模块。
 *
 * 注意：测试直接跑在真实业务库 yh 上（见 Tests\TestCase::useBusinessDatabase），
 * 所有写操作都用 ZZTEST 前缀的临时账号，tearDown 里无条件清理，
 * 绝不碰真正在用的 5 个账号。
 */
class UserTest extends TestCase
{
    /** @var string[] 本测试创建的账号，tearDown 统一删除 */
    private array $created = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->useBusinessDatabase();
    }

    protected function tearDown(): void
    {
        if ($this->created !== []) {
            User::whereIn('name', $this->created)->delete();
        }

        parent::tearDown();
    }

    private function admin(): User
    {
        return User::where('level', 1)->orderBy('id')->firstOrFail();
    }

    /**
     * 造一个临时账号，登记到清理清单。
     */
    private function makeUser(array $overrides = []): User
    {
        $name = $overrides['name'] ?? 'ZZTEST'.bin2hex(random_bytes(4));

        $user = User::create(array_merge([
            'name' => $name,
            'nick' => '测试',
            'email' => $name.'@example.test',
            'company' => 'TEST',
            'level' => 1,
            'password' => Hash::make('secret123'),
            'lastip' => '127.0.0.1',
            'lastlogin' => '2020-01-02 03:04:05',
            'remark' => '自动化测试临时账号',
        ], $overrides));

        $this->created[] = $user->name;

        return $user;
    }

    public function test_guest_cannot_reach_user_module(): void
    {
        $this->get('/user/list')->assertRedirect('/login');
        $this->post('/user/add', [])->assertRedirect('/login');
    }

    public function test_non_admin_gets_403(): void
    {
        $disabled = $this->makeUser(['level' => 0]);

        $this->actingAs($disabled)->get('/user/list')->assertForbidden();
        $this->actingAs($disabled)->get('/user/add')->assertForbidden();
    }

    public function test_list_shows_existing_accounts(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/user/list')
            ->assertOk()
            ->assertSee('用户列表')
            ->assertSee($admin->name)
            ->assertSee('共')
            ->assertSee('个账号');
    }

    public function test_list_can_search_and_paginate(): void
    {
        $admin = $this->admin();
        $target = $this->makeUser(['nick' => 'ZZFINDME'.bin2hex(random_bytes(3))]);

        $this->actingAs($admin)->get('/user/list?search_nick='.urlencode($target->nick))
            ->assertOk()
            ->assertSee($target->name)
            ->assertDontSee('NeooEuRok');

        // 只筛停用账号时，启用的账号不应出现（用管理员邮箱做断言，
        // 登录名是单字符，拿名字断言会误命中整页）
        $this->actingAs($admin)->get('/user/list?search_level=0')
            ->assertOk()
            ->assertDontSee($admin->email);
    }

    public function test_add_user_persists_and_can_login(): void
    {
        $admin = $this->admin();
        $name = 'ZZTEST'.bin2hex(random_bytes(4));

        $this->actingAs($admin)->post('/user/add', [
            'name' => $name,
            'nick' => '新同事',
            'email' => $name.'@example.test',
            'company' => 'TEST',
            'level' => '1',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'remark' => '临时',
        ])->assertRedirect(route('user_list', ['addno' => $name]));

        $this->created[] = $name;

        $row = DB::table('users')->where('name', $name)->first();

        $this->assertNotNull($row, '账号应已写入 users 表');
        $this->assertSame('新同事', $row->nick);
        $this->assertSame(1, (int) $row->level);
        $this->assertTrue(Hash::check('secret123', $row->password), '密码应是可校验的 bcrypt 哈希');
        $this->assertStringStartsWith('$2y$', $row->password);
        $this->assertSame('0000-00-00 00:00:00', $row->lastlogin, '新账号不应把创建时间当成最后登录时间');
    }

    public function test_view_page_renders_and_unknown_name_redirects(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/user/view/'.urlencode($admin->name))
            ->assertOk()
            ->assertSee('修改用户')
            ->assertSee('留空则不修改')
            ->assertSee('系统维护，不可编辑');

        $this->actingAs($admin)->get('/user/view/ZZTEST-NOT-EXIST')
            ->assertRedirect(route('user_list', ['missing' => 'ZZTEST-NOT-EXIST']));
    }

    public function test_add_user_rejects_duplicate_and_short_password(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/user/add', [
            'name' => $admin->name,
            'email' => 'dup@example.test',
            'level' => '1',
            'password' => '123',
            'password_confirmation' => '123',
        ])->assertSessionHasErrors(['name', 'password']);

        $this->assertSame(1, User::where('name', $admin->name)->count());
    }

    public function test_update_keeps_lastlogin_and_lastip(): void
    {
        $admin = $this->admin();
        $target = $this->makeUser(['level' => 0]);

        $before = DB::table('users')->where('id', $target->id)->first();

        $this->actingAs($admin)->post('/user/view/'.urlencode($target->name), [
            'name' => $target->name,
            'nick' => '改过的昵称',
            'email' => $target->email,
            'company' => 'NEWCO',
            'level' => '0',
            'password' => '',
            'password_confirmation' => '',
            'remark' => '备注也改了',
        ])->assertRedirect(route('user_view', ['name' => $target->name, 'update' => 'yes']));

        $after = DB::table('users')->where('id', $target->id)->first();

        $this->assertSame('改过的昵称', $after->nick);
        $this->assertSame('NEWCO', $after->company);

        // lastlogin 列带 ON UPDATE CURRENT_TIMESTAMP，不显式写回就会被刷成 now()
        $this->assertSame(
            $before->lastlogin,
            $after->lastlogin,
            'lastlogin 必须原样保留，不能被 UPDATE 刷新'
        );
        $this->assertSame($before->lastip, $after->lastip);
        $this->assertSame($before->password, $after->password, '没填新密码时不应改动哈希');
    }

    public function test_update_can_change_password(): void
    {
        $admin = $this->admin();
        $target = $this->makeUser();

        $this->actingAs($admin)->post('/user/view/'.urlencode($target->name), [
            'name' => $target->name,
            'nick' => $target->nick,
            'email' => $target->email,
            'company' => $target->company,
            'level' => '1',
            'password' => 'brandnew1',
            'password_confirmation' => 'brandnew1',
            'remark' => '',
        ]);

        $row = DB::table('users')->where('id', $target->id)->first();

        $this->assertTrue(Hash::check('brandnew1', $row->password), '新密码应生效');
        $this->assertFalse(Hash::check('secret123', $row->password), '旧密码应失效');
    }

    public function test_update_can_rename_login_name(): void
    {
        $admin = $this->admin();
        $target = $this->makeUser();
        $newName = 'ZZTEST'.bin2hex(random_bytes(4));

        $this->actingAs($admin)->post('/user/view/'.urlencode($target->name), [
            'name' => $newName,
            'nick' => $target->nick,
            'email' => $target->email,
            'company' => $target->company,
            'level' => '1',
            'password' => '',
            'password_confirmation' => '',
            'remark' => '',
        ])->assertRedirect(route('user_view', ['name' => $newName, 'update' => 'yes']));

        $this->created[] = $newName;

        $this->assertNull(User::where('name', $target->name)->first(), '旧登录名应已不存在');
        $this->assertNotNull(User::where('name', $newName)->first(), '新登录名应生效');
    }

    public function test_status_toggle(): void
    {
        $admin = $this->admin();
        $target = $this->makeUser(['level' => 1]);

        $this->actingAs($admin)->get('/user/status/'.urlencode($target->name))
            ->assertRedirect(route('user_list', ['status' => 'off', 'statusno' => $target->name]));

        $this->assertSame(0, (int) User::where('name', $target->name)->value('level'));

        $this->actingAs($admin)->get('/user/status/'.urlencode($target->name))
            ->assertRedirect(route('user_list', ['status' => 'on', 'statusno' => $target->name]));

        $this->assertSame(1, (int) User::where('name', $target->name)->value('level'));
    }

    public function test_cannot_disable_or_delete_self(): void
    {
        $self = $this->makeUser(['level' => 1]);

        $this->actingAs($self)->get('/user/status/'.urlencode($self->name))
            ->assertRedirect(route('user_list'));

        $this->assertSame(1, (int) User::where('name', $self->name)->value('level'), '不能停用自己');
        $this->assertNotNull(User::where('name', $self->name)->first(), '不能删除自己');

        $this->actingAs($self)->post('/user/del', ['name' => $self->name]);
        $this->assertNotNull(User::where('name', $self->name)->first(), '不能删除自己');
    }

    public function test_reset_password_generates_working_password(): void
    {
        $admin = $this->admin();
        $target = $this->makeUser();

        $response = $this->actingAs($admin)->post('/user/reset/'.urlencode($target->name));

        $response->assertRedirect(route('user_list', ['resetno' => $target->name]));

        // 明文密码只走 session 闪存，不进 URL
        $response->assertSessionHas('resetpwd');
        $this->assertStringNotContainsString('resetpwd=', (string) $response->headers->get('Location'));

        $plain = session('resetpwd');
        $this->assertIsString($plain);
        $this->assertGreaterThanOrEqual(8, strlen($plain));

        $row = DB::table('users')->where('id', $target->id)->first();

        $this->assertTrue(Hash::check($plain, $row->password), '提示里的新密码必须能校验通过');
        $this->assertFalse(Hash::check('secret123', $row->password), '旧密码应失效');
    }

    public function test_delete_removes_account(): void
    {
        $admin = $this->admin();
        $target = $this->makeUser(['level' => 0]);

        $this->actingAs($admin)->post('/user/del', ['name' => $target->name])
            ->assertRedirect(route('user_list', ['del' => 'yes', 'delno' => $target->name]));

        $this->assertNull(User::where('name', $target->name)->first());
    }

    public function test_legacy_post_edit_and_del_routes_still_work(): void
    {
        $admin = $this->admin();
        $target = $this->makeUser(['level' => 0]);

        // 原站是 POST user/edit 提交整表，字段名与表单一致
        $this->actingAs($admin)->post('/user/edit', [
            'name' => $target->name,
            'nick' => '老接口改的',
            'email' => $target->email,
            'company' => $target->company,
            'level' => '0',
            'password' => '',
            'password_confirmation' => '',
            'remark' => '',
        ])->assertRedirect();

        $this->assertSame('老接口改的', User::where('id', $target->id)->value('nick'));

        $this->actingAs($admin)->post('/user/del', ['name' => $target->name]);
        $this->assertNull(User::where('name', $target->name)->first());
    }

    public function test_passwords_are_never_rendered(): void
    {
        $admin = $this->admin();
        $target = $this->makeUser(['remark' => 'ZZSECRET']);

        $html = $this->actingAs($admin)->get('/user/list')->getContent();

        $hash = (string) DB::table('users')->where('id', $target->id)->value('password');

        $this->assertStringNotContainsString($hash, $html);
        $this->assertStringNotContainsString('$2y$', $html);
        $this->assertStringNotContainsString('remember_token', $html);
    }
}
