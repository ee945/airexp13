<?php

namespace Tests\Feature;

use App\Models\Hawb;
use App\Models\Mawb;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 单证打印测试：分单（新版 / 旧版）与总单套打页。
 *
 * 直连真实业务库 yh，不做任何写操作。打印页是绝对定位的 HTML，
 * 这里校验关键字段已渲染、坐标变量已算出（无 Undefined 警告导致的 500）。
 */
class PrintTest extends TestCase
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

        $hawb = (string) Hawb::orderBy('id')->value('hawb');
        $this->get('/print/hawb/'.urlencode($hawb))->assertRedirect('/login');
    }

    public function test_print_hawb_new_layout(): void
    {
        $hawb = Hawb::orderBy('id')->firstOrFail();

        $response = $this->get('/print/hawb/'.urlencode($hawb->hawb));

        $response->assertOk()
            ->assertSee('分单打印')
            ->assertSee($hawb->hawb)
            ->assertSee($hawb->mawb)
            // 付费方式：PP/PC 为预付，其余（CP/CC）为到付
            ->assertSee(in_array($hawb->paymt, ['CP', 'CC'], true) ? 'FREIGHT COLLECT' : 'FREIGHT PREPAID');

        $html = $response->getContent();
        // 坐标已算出（不是模板里的 {{ $xxx_x }} 原样输出）
        $this->assertStringNotContainsString('{{', $html);
        $this->assertMatchesRegularExpression('/#shipper\s*\{\s*position:absolute;left:[\d.\-]+px;top:[\d.\-]+px;/', $html);
        // 付费方式栏：&nbsp; 已还原为空格，页面不再出现实体文本
        $this->assertMatchesRegularExpression('/<div id="paymt"> +[PC] +[PC]<\/div>/', $html);
        $this->assertStringNotContainsString('&nbsp;', $html);
    }

    public function test_print_hawb_old_layout(): void
    {
        $hawb = Hawb::orderBy('id')->firstOrFail();

        $this->get('/print/hawb_old/'.urlencode($hawb->hawb))
            ->assertOk()
            ->assertSee('分单打印')
            ->assertSee($hawb->hawb);
    }

    public function test_print_hawb_redirects_when_missing(): void
    {
        $this->get('/print/hawb/000-NOT-EXIST')->assertRedirect(route('hawb_list'));
        $this->get('/print/hawb_old/000-NOT-EXIST')->assertRedirect(route('hawb_list'));
    }

    public function test_print_mawb_layout(): void
    {
        $mawb = Mawb::orderBy('id')->firstOrFail();

        $response = $this->get('/print/mawb/'.urlencode($mawb->mawb));

        $response->assertOk()
            ->assertSee('总单打印')
            ->assertSee($mawb->mawb)
            ->assertSee('FREIGHT PREPAID');

        $html = $response->getContent();
        $this->assertStringNotContainsString('{{', $html);
        // 体积栏：&nbsp; 已还原为空格，页面不再出现实体文本
        $this->assertMatchesRegularExpression('/<div id="cbm">[\d.\-]+ CBM<\/div>/', $html);
        $this->assertStringNotContainsString('&nbsp;', $html);
        // 总单号拆成前 3 位 + 后 8 位打印
        $this->assertStringContainsString(substr($mawb->mawb, 0, 3), $html);
        $this->assertStringContainsString(substr($mawb->mawb, -8), $html);
    }

    public function test_print_mawb_redirects_when_missing(): void
    {
        $this->get('/print/mawb/000-NOT-EXIST')->assertRedirect(route('mawb_list'));
    }

    public function test_zero_flight_date_does_not_print_1970(): void
    {
        DB::beginTransaction();

        try {
            $hawb = Hawb::orderBy('id')->firstOrFail();
            $hawb->fltdate = '0000-00-00';
            $hawb->save();

            $html = $this->get('/print/hawb/'.urlencode($hawb->hawb))->assertOk()->getContent();

            $this->assertStringNotContainsString('1970', $html);
        } finally {
            DB::rollBack();
        }
    }
}
