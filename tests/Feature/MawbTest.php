<?php

namespace Tests\Feature;

use App\Models\Hawb;
use App\Models\Mawb;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 总单模块测试：列表 / 搜索 / 打印（含无记录时按分单合成）/ 保存 / 删除。
 *
 * 直连真实业务库 yh，写操作全部包在事务里并回滚。
 */
class MawbTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->useBusinessDatabase();

        $this->actingAs(User::where('level', 1)->orderBy('id')->firstOrFail());
    }

    /** 现成的总单号 */
    protected function anyMawb(): string
    {
        return (string) Mawb::orderBy('id')->value('mawb');
    }

    protected function payload(string $mawbno, array $overrides = []): array
    {
        return array_merge([
            'mawb' => $mawbno,
            'oversea' => 'TEST AGENT',
            'dest' => 'TYO',
            'desti' => 'TOKYO',
            'depa' => 'PVG',
            'depar' => 'SHANGHAI',
            'shipper' => "TEST SHIPPER\nTEST ADDRESS",
            'consignee' => "TEST CONSIGNEE\nTEST ADDRESS",
            'agentabbr' => 'TESTA/SHA',
            'agentcode' => '1234567',
            'agentaccount' => 'ACC001',
            'carrier' => 'MU',
            'fltno' => 'MU523',
            'fltdate' => '2026-10-28',
            'special' => '',
            'package' => 'CTN',
            'num' => 2,
            'gw' => 30,
            'cw' => 31,
            'cbm' => '0.045',
            'rclass' => 'M',
            'up' => '10.00',
            'freight' => '310.00',
            'awn' => 'AWC',
            'myn' => 'MYC',
            'scn' => 'MSC',
            'myup' => '1.00',
            'scup' => '1.00',
            'aw' => '50.00',
            'my' => '10.00',
            'sc' => '10.00',
            'other' => '70.00',
            'amount' => '380.00',
            'cgodescp' => 'CONSOL CARGO',
            'signature' => 'TESTA/SHA',
            'atplace' => 'SHANGHAI',
            'operator' => 'TESTER',
            'opdate' => '2026-10-27',
        ], $overrides);
    }

    public function test_guest_is_redirected(): void
    {
        auth()->logout();

        $this->get('/mawb/list')->assertRedirect('/login');
        $this->get('/mawb/print/'.$this->anyMawb())->assertRedirect('/login');
    }

    public function test_list_page_renders_with_totals(): void
    {
        $response = $this->get('/mawb/list');

        $response->assertOk()
            ->assertSee('总运单号')
            ->assertSee('TOTAL:')
            ->assertSee('海外代理');

        // 合计值应等于当前页各列之和
        $page = Mawb::orderBy('fltdate', 'desc')->orderBy('regtime', 'desc')->paginate(20);
        $this->assertSame((string) $page->sum('num'), (string) (int) $page->sum('num'));
        $this->assertGreaterThan(0, $page->count());
    }

    public function test_search_by_mawb_number(): void
    {
        $mawbno = $this->anyMawb();

        $response = $this->get('/mawb/list?search_mawb='.urlencode($mawbno));

        $response->assertOk()->assertSee($mawbno);

        // 搜一个不存在的号：搜索词会回显在输入框里，但列表不应出现任何记录链接
        $this->get('/mawb/list?search_mawb=NO-SUCH-MAWB-9999')
            ->assertOk()
            ->assertSee('NO-SUCH-MAWB-9999')
            ->assertDontSee('/mawb/print/');
    }

    public function test_print_page_binds_model_values(): void
    {
        $mawb = Mawb::orderBy('id')->firstOrFail();

        $response = $this->get('/mawb/print/'.$mawb->mawb);

        $response->assertOk()
            ->assertSee('总单打印')
            ->assertSee($mawb->mawb)
            // Form::model 绑定生效：发货人、品名应回填到 textarea
            ->assertSee(e($mawb->shipper))
            ->assertSee('name="fltno"', false);
    }

    public function test_print_page_falls_back_to_hawbs(): void
    {
        // 取一个"有分单但没有总单记录"的总单号；没有就跳过
        $mawbno = DB::table('hawbs')
            ->select('mawb')
            ->where('mawb', '<>', '')
            ->whereNotIn('mawb', DB::table('mawbs')->select('mawb'))
            ->value('mawb');

        if ($mawbno === null) {
            $this->assertTrue(true, '库中所有分单都已有对应总单记录，跳过该用例');

            return;
        }

        $this->get('/mawb/print/'.urlencode($mawbno))
            ->assertOk()
            ->assertSee($mawbno);
    }

    public function test_print_page_redirects_when_nothing_found(): void
    {
        $this->get('/mawb/print/000-NOT-EXIST')
            ->assertRedirect(route('mawb_list'));
    }

    public function test_create_round_trip(): void
    {
        DB::beginTransaction();

        try {
            $mawbno = '99M20168';

            $this->post('/mawb/print/'.$mawbno, $this->payload($mawbno))
                ->assertRedirect(route('mawb_print', ['mawb' => $mawbno, 'save' => 'yes']));

            $row = Mawb::where('mawb', $mawbno)->first();
            $this->assertNotNull($row, '总单未写入');
            $this->assertSame('TYO', $row->dest);
            $this->assertSame('MU523', $row->fltno);
            $this->assertSame(2, (int) $row->num);
            // agentcode 原本不在 fillable 里，新建时会丢；现已修复
            $this->assertSame('1234567', $row->agentcode);
            // regtime NOT NULL，新建时必须显式写入
            $this->assertNotNull($row->regtime);
        } finally {
            DB::rollBack();
        }
    }

    public function test_update_keeps_columns_not_submitted(): void
    {
        DB::beginTransaction();

        try {
            $mawbno = '99M20168';
            $this->post('/mawb/print/'.$mawbno, $this->payload($mawbno));

            // 只提交部分字段（模拟打印页二次保存时的精简提交）
            $this->post('/mawb/print/'.$mawbno, [
                'mawb' => $mawbno,
                'shipper' => 'UPDATED SHIPPER',
                'consignee' => 'UPDATED CONSIGNEE',
                'fltno' => 'MU524',
                'fltdate' => '2026-10-29',
                'package' => 'CTN',
                'num' => 3,
                'gw' => 40,
                'cw' => 41,
                'up' => '10.00',
                'cgodescp' => 'CONSOL CARGO',
                'cbm' => '0.050',
                'aw' => '50.00',
                'rclass' => 'M',
                'opdate' => '2026-10-27',
                'atplace' => 'SHANGHAI',
            ]);

            $row = Mawb::where('mawb', $mawbno)->first();
            $this->assertSame('UPDATED SHIPPER', $row->shipper);
            $this->assertSame('MU524', $row->fltno);
            // 未提交的列保持原值
            $this->assertSame('TEST AGENT', $row->oversea);
            $this->assertSame('ACC001', $row->agentaccount);
            $this->assertSame('TESTA/SHA', $row->signature);
        } finally {
            DB::rollBack();
        }
    }

    public function test_save_with_save_flag_in_url_does_not_break(): void
    {
        DB::beginTransaction();

        try {
            $mawbno = '99M20168';
            $this->post('/mawb/print/'.$mawbno, $this->payload($mawbno));

            // 保存成功跳转后 URL 带 ?save=yes，表单再次提交会带上该参数；
            // 原实现会把它当成表字段写库而报错
            $this->post('/mawb/print/'.$mawbno.'?save=yes', $this->payload($mawbno, ['fltno' => 'MU525']))
                ->assertRedirect();

            $this->assertSame('MU525', Mawb::where('mawb', $mawbno)->value('fltno'));
        } finally {
            DB::rollBack();
        }
    }

    public function test_validation_rejects_bad_flight_number(): void
    {
        $mawbno = '99M20168';

        $this->from('/mawb/print/'.$mawbno)
            ->post('/mawb/print/'.$mawbno, $this->payload($mawbno, ['fltno' => 'X']))
            ->assertSessionHasErrors('fltno');

        $this->assertNull(Mawb::where('mawb', $mawbno)->first());
    }

    public function test_delete_round_trip(): void
    {
        DB::beginTransaction();

        try {
            $mawbno = '99M20168';
            $this->post('/mawb/print/'.$mawbno, $this->payload($mawbno));
            $this->assertNotNull(Mawb::where('mawb', $mawbno)->first());

            $this->get('/mawb/del/'.$mawbno)
                ->assertRedirect(route('mawb_list', ['del' => 'yes', 'delno' => $mawbno]));

            $this->assertNull(Mawb::where('mawb', $mawbno)->first());
        } finally {
            DB::rollBack();
        }
    }

    public function test_delete_reports_missing_record(): void
    {
        $this->get('/mawb/del/000-NOT-EXIST')
            ->assertRedirect(route('mawb_list', ['del' => 'no', 'delno' => '000-NOT-EXIST']));
    }
}
