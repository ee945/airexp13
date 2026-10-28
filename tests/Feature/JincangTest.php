<?php

namespace Tests\Feature;

use App\Models\Jincang;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 进仓模块功能测试：列表 / 搜索 / 登记 / 修改 / 出仓切换 / 删除。
 *
 * 直连真实业务库 yh，写操作全部包在事务里并回滚。
 */
class JincangTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->useBusinessDatabase();

        $this->actingAs(User::where('level', 1)->orderBy('id')->firstOrFail());
    }

    public function test_list_renders_real_records(): void
    {
        $row = Jincang::orderBy('id', 'desc')->firstOrFail();

        $this->get('/jincang/list')
            ->assertOk()
            ->assertSee('进仓列表')
            ->assertSee($row->jcno);
    }

    public function test_list_search_by_jcno(): void
    {
        $row = Jincang::orderBy('id', 'desc')->firstOrFail();

        $html = $this->get('/jincang/list?search_jcno='.urlencode($row->jcno))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($row->jcno, $html);

        // 不存在的编号 → 列表为空，分页文案显示 0 条
        $this->get('/jincang/list?search_jcno=99NOSUCH9')
            ->assertOk()
            ->assertDontSee($row->jcno);
    }

    public function test_add_form_is_prefilled_with_next_jcno(): void
    {
        $last = Jincang::orderBy('id', 'desc')->firstOrFail();

        $this->get('/jincang/add')
            ->assertOk()
            ->assertSee('进仓登记')
            ->assertSee('上次：'.$last->jcno);
    }

    public function test_view_page_renders_and_is_readonly(): void
    {
        $row = Jincang::orderBy('id', 'desc')->firstOrFail();

        $this->get('/jincang/view/'.urlencode($row->jcno))
            ->assertOk()
            ->assertSee('进仓修改')
            ->assertSee('readonly="readonly"', false);
    }

    public function test_view_page_redirects_when_not_found(): void
    {
        $this->get('/jincang/view/99NOSUCH9')
            ->assertRedirect(route('jincang_list'));
    }

    public function test_create_round_trip(): void
    {
        DB::beginTransaction();

        try {
            $jcno = '99TST001';

            $this->post('/jincang/add', [
                'regdate' => '2026-10-27',
                'jcno' => $jcno,
                'dest' => 'TYO',
                'fltdate' => '',
                'client' => '测试托运人',
                'forward' => '测试货源',
                'factory' => '测试厂家',
                'carrier' => 'ANA',
                'delivery' => '交货要求文本',
                'cargodata' => '2 CTNS 30 KGS',
                'remark' => '备注文本',
            ])->assertRedirect(route('jincang_list', ['addno' => $jcno]));

            $row = Jincang::where('jcno', $jcno)->firstOrFail();

            // 空航班日期 → 0000-00-00（原库"未定"约定）
            $this->assertSame('0000-00-00', $row->fltdate);
            $this->assertSame(0, (int) $row->status);
            $this->assertSame('TYO', $row->dest);
            $this->assertSame('备注文本', $row->remark);
        } finally {
            DB::rollBack();
        }
    }

    public function test_create_rejects_duplicate_jcno(): void
    {
        DB::beginTransaction();

        try {
            $row = Jincang::orderBy('id', 'desc')->firstOrFail();

            $this->post('/jincang/add', [
                'regdate' => '2026-10-27',
                'jcno' => $row->jcno,
                'forward' => 'X',
            ])->assertSessionHasErrors('jcno');
        } finally {
            DB::rollBack();
        }
    }

    public function test_create_rejects_bad_format_and_missing_forward(): void
    {
        DB::beginTransaction();

        try {
            // 含小写字母与下划线 → 不符合 [0-9A-Z-]{6,12}
            $this->post('/jincang/add', [
                'regdate' => '2026-10-27',
                'jcno' => 'abc_12',
                'forward' => 'X',
            ])->assertSessionHasErrors('jcno');

            $this->post('/jincang/add', [
                'regdate' => '2026-10-27',
                'jcno' => '99TST002',
            ])->assertSessionHasErrors('forward');

            $this->assertDatabaseMissing('jincang', ['jcno' => '99TST002'], 'mysql');
        } finally {
            DB::rollBack();
        }
    }

    public function test_update_round_trip(): void
    {
        DB::beginTransaction();

        try {
            $jcno = '99TST003';

            Jincang::create([
                'regdate' => '2026-10-20',
                'jcno' => $jcno,
                'dest' => 'LAX',
                'fltdate' => '0000-00-00',
                'client' => '旧托运人',
                'forward' => '旧货源',
                'factory' => '',
                'carrier' => '',
                'delivery' => '',
                'cargodata' => '',
                'remark' => '',
                'status' => 0,
            ]);

            $this->post('/jincang/view/'.$jcno, [
                'regdate' => '2026-10-21',
                'jcno' => $jcno,
                'dest' => 'LAX',
                'fltdate' => '2026-10-28',
                'client' => '新托运人',
                'forward' => '新货源',
                'factory' => '新厂家',
                'carrier' => 'CA',
                'delivery' => '',
                'cargodata' => 'updated',
                'remark' => '',
            ])->assertRedirect(route('jincang_view', ['jcno' => $jcno, 'update' => 'yes']));

            $row = Jincang::where('jcno', $jcno)->firstOrFail();

            $this->assertSame('新托运人', $row->client);
            $this->assertSame('2026-10-28', $row->fltdate);
            $this->assertSame('updated', $row->cargodata);
        } finally {
            DB::rollBack();
        }
    }

    public function test_status_toggles_between_in_and_out(): void
    {
        DB::beginTransaction();

        try {
            $jcno = '99TST004';

            Jincang::create([
                'regdate' => '2026-10-20',
                'jcno' => $jcno,
                'dest' => 'LAX',
                'fltdate' => '0000-00-00',
                'client' => '',
                'forward' => 'F',
                'factory' => '',
                'carrier' => '',
                'delivery' => '',
                'cargodata' => '',
                'remark' => '',
                'status' => 0,
            ]);

            $this->get('/jincang/status/'.$jcno)
                ->assertRedirect(route('jincang_list', ['status' => 'out', 'jcno' => $jcno]));

            $this->assertSame(1, (int) Jincang::where('jcno', $jcno)->value('status'));

            $this->get('/jincang/status/'.$jcno)
                ->assertRedirect(route('jincang_list', ['status' => 'in', 'jcno' => $jcno]));

            $this->assertSame(0, (int) Jincang::where('jcno', $jcno)->value('status'));

            $this->get('/jincang/status/99NOSUCH9')
                ->assertRedirect(route('jincang_list', ['status' => 'no', 'jcno' => '99NOSUCH9']));
        } finally {
            DB::rollBack();
        }
    }

    public function test_delete_round_trip(): void
    {
        DB::beginTransaction();

        try {
            $jcno = '99TST005';

            Jincang::create([
                'regdate' => '2026-10-20',
                'jcno' => $jcno,
                'dest' => 'LAX',
                'fltdate' => '0000-00-00',
                'client' => '',
                'forward' => 'F',
                'factory' => '',
                'carrier' => '',
                'delivery' => '',
                'cargodata' => '',
                'remark' => '',
                'status' => 0,
            ]);

            $this->get('/jincang/del/'.$jcno)
                ->assertRedirect(route('jincang_list', ['del' => 'yes', 'delno' => $jcno]));

            $this->assertDatabaseMissing('jincang', ['jcno' => $jcno], 'mysql');

            $this->get('/jincang/del/99NOSUCH9')
                ->assertRedirect(route('jincang_list', ['del' => 'no', 'delno' => '99NOSUCH9']));
        } finally {
            DB::rollBack();
        }
    }

    public function test_update_fltdate_from_list_shortcut(): void
    {
        DB::beginTransaction();

        try {
            $jcno = '99TST006';

            Jincang::create([
                'regdate' => '2026-10-20',
                'jcno' => $jcno,
                'dest' => 'LAX',
                'fltdate' => '0000-00-00',
                'client' => '',
                'forward' => 'F',
                'factory' => '',
                'carrier' => '',
                'delivery' => '',
                'cargodata' => '',
                'remark' => '',
                'status' => 0,
            ]);

            $this->get('/update/fltdate/'.$jcno.'/2026-10-30')
                ->assertRedirect(route('jincang_list'));

            $this->assertSame('2026-10-30', Jincang::where('jcno', $jcno)->value('fltdate'));
        } finally {
            DB::rollBack();
        }
    }
}
