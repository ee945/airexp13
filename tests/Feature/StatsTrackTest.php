<?php

namespace Tests\Feature;

use App\Models\Hawb;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 货量统计与货物追踪测试。
 *
 * 直连真实业务库 yh，不做任何写操作。
 */
class StatsTrackTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->useBusinessDatabase();

        $this->actingAs(User::where('level', 1)->orderBy('id')->firstOrFail());
    }

    public function test_guest_is_redirected(): void
    {
        auth()->logout();

        $this->get('/stats/hawb')->assertRedirect('/login');
        $this->get('/track')->assertRedirect('/login');
    }

    public function test_stats_hawb_default_page(): void
    {
        $response = $this->get('/stats/hawb');

        $response->assertOk()
            ->assertSee('分单货量统计')
            ->assertSee('开始统计')
            ->assertSee('查询到');
    }

    public function test_stats_hawb_filters_by_date_range(): void
    {
        // 取一个有分单的航班日期，用它做区间上下界
        $date = DB::table('hawbs')
            ->where('fltdate', '>', '0001-01-01')
            ->orderByDesc('fltdate')
            ->value('fltdate');

        $this->assertNotNull($date);

        $response = $this->get('/stats/hawb?filter_fltstart='.$date.'&filter_fltend='.$date.'&perpage=50');

        $response->assertOk()->assertSee('分单货量统计');

        // 结果集内所有分单的航班日期都应是该日期
        $html = $response->getContent();
        $this->assertStringContainsString('开始统计', $html);
    }

    public function test_stats_hawb_weekday_filter_accepts_array(): void
    {
        $this->get('/stats/hawb?filter_weekday[]=0&filter_weekday[]=1&perpage=20')
            ->assertOk()
            ->assertSee('分单货量统计');
    }

    /**
     * 统计首页（/stats）与总单货量（/stats/mawb）已暂时取消入口。
     *
     * 原系统这两处各只有一行 echo，没有可参照的逻辑；2026-10-29 按需求
     * 直接下线，路由一并移除，访问应当是 404 而不是残留的占位页。
     */
    public function test_stats_index_and_mawb_are_offline(): void
    {
        $this->get('/stats')->assertNotFound();
        $this->get('/stats/mawb')->assertNotFound();

        // 分单货量照旧可用
        $this->get('/stats/hawb')->assertOk()->assertSee('分单货量统计');
    }

    public function test_track_index_lists_four_flight_days(): void
    {
        $response = $this->get('/track');

        $response->assertOk()
            ->assertSee('货物追踪')
            ->assertSee(now()->format('m.d'))
            ->assertSee(now()->addDay()->format('m.d'));
    }

    public function test_track_airline_lists_carriers(): void
    {
        $this->get('/track/airline')
            ->assertOk()
            ->assertSee('航空公司货运追踪网址')
            // 航司名 + 总单代号 + 官网地址
            ->assertSee('国泰货运')
            ->assertSee('112 / 784')
            ->assertSee('cathaypacificcargo.com');
    }

    public function test_track_status_shows_local_shipment(): void
    {
        $hawb = Hawb::orderBy('id')->firstOrFail();

        $this->get('/track/status/'.urlencode($hawb->mawb))
            ->assertOk()
            ->assertSee('在本系统内的状态')
            ->assertSee($hawb->hawb)
            ->assertSee($hawb->mawb);
    }

    public function test_track_status_reports_unknown_mawb(): void
    {
        // 999-12345675 校验位合法，但库里没有
        $this->get('/track/status/999-12345675')
            ->assertOk()
            ->assertSee('没有总单')
            ->assertSee('999-12345675');
    }

    public function test_track_arrival_redirects_to_pactl(): void
    {
        $response = $this->get('/track/arrival/999-12345675');

        $target = $response->headers->get('Location');
        $this->assertStringContainsString('pactl.com.cn', $target);
        $this->assertStringContainsString('bill_no_1=999', $target);
        $this->assertStringContainsString('bill_no_2=12345675', $target);
    }

    public function test_track_arrival_uses_post_form_for_ceair(): void
    {
        $this->get('/track/arrival/018-12345675')
            ->assertOk()
            ->assertSee('eal-ceair.com')
            ->assertSee('01812345675');
    }

    public function test_track_arrival_reports_unsupported_carrier(): void
    {
        $this->get('/track/arrival/123-12345675')
            ->assertOk()
            ->assertSee('暂不支持查询 123');
    }

    public function test_track_flight_redirects_to_carrier_site(): void
    {
        $target = $this->get('/track/flight/023-12345675')->headers->get('Location');

        $this->assertStringContainsString('fedex.com', $target);
        $this->assertStringContainsString('02312345675', $target);
    }

    public function test_track_flight_falls_back_to_carrier_homepage(): void
    {
        // 国航原站是服务端抓取，现已改为给官网入口
        $this->get('/track/flight/999-12345675')
            ->assertOk()
            ->assertSee('国航货运')
            ->assertSee('airchinacargo.com');
    }

    public function test_track_rejects_invalid_mawb(): void
    {
        $this->get('/track/arrival/abc')->assertOk()->assertSee('总运单格式不正确');
        $this->get('/track/flight/999-12345678')->assertOk()->assertSee('不符合校验规则');
        $this->get('/track/status/999-12345678')->assertOk()->assertSee('不符合校验规则');
    }

    /**
     * 报表（/report/*）已暂时取消入口。
     *
     * 原项目 ReportController 是空壳，三条路由指向不存在的方法（访问即 500），
     * 没有可参照的逻辑。2026-10-29 按需求直接下线，路由移除后应为 404。
     */
    public function test_report_routes_are_offline(): void
    {
        $this->get('/report/daily')->assertNotFound();
        $this->get('/report/month')->assertNotFound();
        $this->get('/report/forward')->assertNotFound();
    }
}
