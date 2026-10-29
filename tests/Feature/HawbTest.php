<?php

namespace Tests\Feature;

use App\Models\Hawb;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 分单模块功能测试：列表 / 搜索 / 输入 / 修改 / 删除 / 打印页 / 打印页保存。
 *
 * 直连真实业务库 yh，写操作全部包在事务里并回滚。
 */
class HawbTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->useBusinessDatabase();

        $this->actingAs(User::where('level', 1)->orderBy('id')->firstOrFail());
    }

    /** 造一条最小可用的分单，返回分单号 */
    protected function makeHawb(string $hawb): Hawb
    {
        return Hawb::create([
            'opdate' => '2026-10-20',
            'hawb' => $hawb,
            'mawb' => '',
            'dest' => 'TYO',
            'desti' => 'TOKYO',
            'fltno' => 'MU523',
            'fltdate' => '2026-10-28',
            'forward' => '测试货源',
            'seller' => 'TESTA',
            'factory' => '测试厂家',
            'carrier' => 'MU',
            'carriername' => '',
            'num' => 2,
            'gw' => 30,
            'cw' => 31,
            'cbm' => 0.045,
            'paymt' => 'PP',
            'arranged' => 0,
            'remark' => '',
            'depar' => '',
            'consignee' => '',
            'notify' => '',
            'shipper' => '',
            'curr' => 'CNY',
            'nvd' => '',
            'ncv' => '',
            'package' => 'CTN',
            'rclass' => 'Q',
            'special' => '',
            'cgodescp' => '',
            'agentabbr' => '',
            'regtime' => '0000-00-00 00:00:00',
        ]);
    }

    public function test_list_renders_real_records_with_totals(): void
    {
        $row = Hawb::orderBy('id', 'desc')->firstOrFail();

        $this->get('/hawb/list')
            ->assertOk()
            ->assertSee('分单列表')
            ->assertSee($row->hawb)
            ->assertSee('TOTAL');
    }

    public function test_list_search_by_hawb(): void
    {
        $row = Hawb::orderBy('id', 'desc')->firstOrFail();

        $this->get('/hawb/list?search_hawb='.urlencode($row->hawb))
            ->assertOk()
            ->assertSee($row->hawb);
    }

    public function test_add_form_renders(): void
    {
        $this->get('/hawb/add')
            ->assertOk()
            ->assertSee('分单输入')
            ->assertSee('分单号');
    }

    public function test_view_form_renders_readonly(): void
    {
        $row = Hawb::orderBy('id', 'desc')->firstOrFail();

        $this->get('/hawb/view/'.urlencode($row->hawb))
            ->assertOk()
            ->assertSee('分单修改')
            ->assertSee('readonly="readonly"', false);
    }

    public function test_view_redirects_when_not_found(): void
    {
        $this->get('/hawb/view/99NOTFOUND')->assertRedirect(route('hawb_list'));
    }

    public function test_print_pages_render(): void
    {
        $row = Hawb::orderBy('id', 'desc')->firstOrFail();

        $this->get('/hawb/print/'.urlencode($row->hawb))
            ->assertOk()
            ->assertSee('AIR WAYBILL');

        $this->get('/hawb/printold/'.urlencode($row->hawb))
            ->assertOk()
            ->assertSee('HAWB');
    }

    public function test_create_round_trip(): void
    {
        DB::beginTransaction();

        try {
            $hawb = '99HAWB01';

            $this->post('/hawb/add', [
                'opdate' => '2026-10-27',
                'hawb' => $hawb,
                'mawb' => '018-93096776',
                'dest' => 'TYO',
                'desti' => 'TOKYO',
                'fltno' => 'MU523',
                'fltdate' => '2026-10-29',
                'forward' => '测试货源',
                'seller' => 'TESTA',
                'factory' => '测试厂家',
                'carrier' => 'MU',
                'num' => '3',
                'gw' => '45',
                'cw' => '46',
                'cbm' => '0.120',
                'paymt' => 'PP',
                'arranged' => '0',
                'remark' => '测试备注',
            ])->assertRedirect(route('hawb_list', ['addno' => $hawb]));

            $row = Hawb::where('hawb', $hawb)->firstOrFail();

            $this->assertSame(3, (int) $row->num);
            $this->assertSame(45, (int) $row->gw);
            $this->assertSame('测试备注', $row->remark);
            $this->assertSame('018-93096776', $row->mawb);
            // 表单未提交的 NOT NULL 列应补空串而不是报错
            $this->assertSame('', $row->shipper);
            $this->assertSame('0000-00-00 00:00:00', (string) $row->regtime);
        } finally {
            DB::rollBack();
        }
    }

    public function test_create_rejects_duplicate_hawb(): void
    {
        DB::beginTransaction();

        try {
            $row = Hawb::orderBy('id', 'desc')->firstOrFail();

            $this->post('/hawb/add', [
                'opdate' => '2026-10-27',
                'hawb' => $row->hawb,
                'dest' => 'TYO',
                'desti' => 'TOKYO',
                'fltdate' => '2026-10-29',
                'forward' => 'F',
                'seller' => 'S',
                'factory' => 'C',
                'num' => '1',
                'gw' => '1',
                'cw' => '1',
                'cbm' => '0.01',
                'paymt' => 'PP',
                'arranged' => '0',
            ])->assertSessionHasErrors('hawb');
        } finally {
            DB::rollBack();
        }
    }

    public function test_create_rejects_bad_hawb_format(): void
    {
        DB::beginTransaction();

        try {
            $this->post('/hawb/add', [
                'opdate' => '2026-10-27',
                'hawb' => 'abc',
                'dest' => 'TYO',
                'desti' => 'TOKYO',
                'fltdate' => '2026-10-29',
                'forward' => 'F',
                'seller' => 'S',
                'factory' => 'C',
                'num' => '1',
                'gw' => '1',
                'cw' => '1',
                'cbm' => '0.01',
                'paymt' => 'PP',
                'arranged' => '0',
            ])->assertSessionHasErrors('hawb');

            $this->assertDatabaseMissing('hawbs', ['hawb' => 'abc'], 'mysql');
        } finally {
            DB::rollBack();
        }
    }

    public function test_create_rejects_mawb_with_wrong_check_digit(): void
    {
        DB::beginTransaction();

        try {
            // 018-93096776 校验位正确；改成 ...5 应被 ismawb 拦下
            $this->post('/hawb/add', [
                'opdate' => '2026-10-27',
                'hawb' => '99HAWB02',
                'mawb' => '018-93096775',
                'dest' => 'TYO',
                'desti' => 'TOKYO',
                'fltdate' => '2026-10-29',
                'forward' => 'F',
                'seller' => 'S',
                'factory' => 'C',
                'num' => '1',
                'gw' => '1',
                'cw' => '1',
                'cbm' => '0.01',
                'paymt' => 'PP',
                'arranged' => '0',
            ])->assertSessionHasErrors('mawb');

            $this->assertDatabaseMissing('hawbs', ['hawb' => '99HAWB02'], 'mysql');
        } finally {
            DB::rollBack();
        }
    }

    public function test_update_round_trip(): void
    {
        DB::beginTransaction();

        try {
            $hawb = '99HAWB03';
            $this->makeHawb($hawb);

            $this->post('/hawb/view/'.$hawb, [
                'opdate' => '2026-10-21',
                'hawb' => $hawb,
                'mawb' => '',
                'dest' => 'KIX',
                'desti' => 'KANSAI',
                'fltno' => 'HO1380',
                'fltdate' => '2026-10-30',
                'forward' => '货源改',
                'seller' => 'TESTA',
                'factory' => '厂家改',
                'carrier' => 'HO',
                'num' => '5',
                'gw' => '60',
                'cw' => '62',
                'cbm' => '0.300',
                'paymt' => 'CC',
                'arranged' => '1',
                'remark' => '改后备注',
            ])->assertRedirect(route('hawb_view', ['hawb' => $hawb, 'update' => 'yes']));

            $row = Hawb::where('hawb', $hawb)->firstOrFail();

            $this->assertSame('KIX', $row->dest);
            $this->assertSame(5, (int) $row->num);
            $this->assertSame(1, (int) $row->arranged);
            $this->assertSame('改后备注', $row->remark);
        } finally {
            DB::rollBack();
        }
    }

    public function test_delete_round_trip(): void
    {
        DB::beginTransaction();

        try {
            $hawb = '99HAWB04';
            $this->makeHawb($hawb);

            $this->get('/hawb/del/'.$hawb)
                ->assertRedirect(route('hawb_list', ['del' => 'yes', 'delno' => $hawb]));

            $this->assertDatabaseMissing('hawbs', ['hawb' => $hawb], 'mysql');

            $this->get('/hawb/del/99NOTFOUND')
                ->assertRedirect(route('hawb_list', ['del' => 'no', 'delno' => '99NOTFOUND']));
        } finally {
            DB::rollBack();
        }
    }

    public function test_print_save_requires_mandatory_fields(): void
    {
        DB::beginTransaction();

        try {
            $hawb = '99HAWB05';
            $this->makeHawb($hawb);

            $this->post('/hawb/print/'.$hawb, [
                'hawb' => $hawb,
            ])->assertSessionHasErrors(['shipper', 'consignee', 'notify', 'rclass', 'cgodescp']);
        } finally {
            DB::rollBack();
        }
    }

    public function test_print_save_updates_fields_without_wiping_others(): void
    {
        DB::beginTransaction();

        try {
            $hawb = '99HAWB06';
            $this->makeHawb($hawb);

            $this->post('/hawb/print/'.$hawb, [
                'hawb' => $hawb,
                'shipper' => "SHIPPER LINE1\nSHIPPER LINE2",
                'consignee' => 'CONSIGNEE CO',
                'notify' => 'SAME AS CONSIGNEE',
                'rclass' => 'M',
                'cgodescp' => 'SPARE PARTS',
                'package' => 'CTN',
                'curr' => 'CNY',
                'num' => '2',
                'gw' => '30',
                'cw' => '31',
                'cbm' => '0.045',
                'opdate' => '2026-10-20',
                'fltdate' => '2026-10-28',
            ])->assertRedirect(route('hawb_print', ['hawb' => $hawb, 'save' => 'yes']));

            $row = Hawb::where('hawb', $hawb)->firstOrFail();

            $this->assertSame('M', $row->rclass);
            $this->assertSame('SPARE PARTS', $row->cgodescp);
            $this->assertStringContainsString('SHIPPER LINE1', $row->shipper);
            // 打印页没提交的字段必须保持原值
            $this->assertSame('测试货源', $row->forward);
            $this->assertSame('PP', $row->paymt);
        } finally {
            DB::rollBack();
        }
    }
}
