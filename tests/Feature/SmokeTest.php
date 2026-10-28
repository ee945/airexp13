<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

/**
 * 冒烟测试：只读访问真实业务库，验证路由 / 中间件 / Blade / 门面别名全链路。
 *
 * phpunit.xml 默认把库指到 sqlite :memory:，而本项目所有页面都依赖真实业务表，
 * 所以这里在 setUp 里把默认连接切回 .env 里的 MySQL（与原站同一个库）。
 * 约束：本测试只读，不写任何数据。
 */
class SmokeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->useBusinessDatabase();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/hawb/list')->assertRedirect('/login');
    }

    public function test_login_page_renders(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('用户名')
            ->assertSee('登陆');
    }

    public function test_authenticated_pages_render(): void
    {
        $user = User::where('level', 1)->orderBy('id')->firstOrFail();
        $this->actingAs($user);

        // 首页会真实统计 hawbs / mawbs / jincang
        $this->get('/')->assertOk()->assertSee('系统概览');

        // 已迁移模块（只列能稳定返回 200 的入口）
        $urls = [
            '/jincang/list', '/jincang/add',
            '/hawb/list', '/hawb/add',
            '/mawb/list',
            '/address/list', '/port/list', '/client/list', '/seller/list', '/contact/list',
        ];

        // 未迁移模块的占位页
        $urls = array_merge($urls, [
            '/report/daily',
            '/user/list', '/track', '/stats', '/stats/hawb',
        ]);

        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }

        // 单证页面依赖真实单据，单独取一条记录验证
        $hawb = \App\Models\Hawb::orderBy('id')->first();
        $mawb = \App\Models\Mawb::orderBy('id')->first();

        if ($hawb !== null) {
            $this->get('/print/hawb/'.urlencode($hawb->hawb))->assertOk();
        }
        if ($mawb !== null) {
            $this->get('/mawb/print/'.urlencode($mawb->mawb))->assertOk();
            $this->get('/print/mawb/'.urlencode($mawb->mawb))->assertOk();
        }

        // 导航里的登录态（nick 或 name 至少要出现一个）
        $this->get('/')->assertSee($user->nick ?: $user->name);
    }

    public function test_legacy_password_hash_is_compatible(): void
    {
        $user = User::where('level', 1)->orderBy('id')->firstOrFail();
        $hasher = $this->app->make('hash');

        $this->assertTrue($hasher->isHashed($user->password), '密码应为已加密的哈希');
        $this->assertTrue($hasher->verifyConfiguration($user->password), '哈希参数需与 BCRYPT_ROUNDS 一致');
        $this->assertFalse($hasher->needsRehash($user->password), '不应触发登录时静默重算哈希');
        $this->assertFalse($hasher->check('definitely-not-the-password', $user->password));
    }
}
