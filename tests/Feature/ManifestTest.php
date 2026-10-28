<?php

namespace Tests\Feature;

use App\Models\Hawb;
use App\Models\Mawb;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use ZipArchive;

/**
 * 清单模块测试：预览 / 导出 Excel / 空数据兜底。
 *
 * 直连真实业务库 yh，不做任何写操作。
 */
class ManifestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->useBusinessDatabase();

        $this->actingAs(User::where('level', 1)->orderBy('id')->firstOrFail());
    }

    /** 找一个含两条以上分单的总单号 */
    protected function mawbWithHawbs(): string
    {
        $row = DB::table('hawbs')
            ->select('mawb', DB::raw('COUNT(*) AS c'))
            ->where('mawb', '<>', '')
            ->groupBy('mawb')
            ->having('c', '>=', 2)
            ->orderByDesc('c')
            ->first();

        $this->assertNotNull($row, '库中没有含多条分单的总单');

        return (string) $row->mawb;
    }

    public function test_guest_is_redirected(): void
    {
        auth()->logout();

        $this->get('/manifest/view/'.$this->mawbWithHawbs())->assertRedirect('/login');
        $this->get('/manifest/export/'.$this->mawbWithHawbs())->assertRedirect('/login');
    }

    public function test_manifest_page_renders(): void
    {
        $mawbno = $this->mawbWithHawbs();
        $hawbs = Hawb::where('mawb', $mawbno)->orderBy('hawb')->get();

        $response = $this->get('/manifest/view/'.$mawbno);

        $response->assertOk()
            ->assertSee('HOUSE CARGO MANIFEST')
            ->assertSee($mawbno)
            ->assertSee('TOTAL:')
            ->assertSee($hawbs->first()->hawb)
            // 合计件数 / 重量
            ->assertSee((string) (int) $hawbs->sum('num'))
            ->assertSee((string) (int) $hawbs->sum('gw'));
    }

    public function test_manifest_page_redirects_for_unknown_mawb(): void
    {
        $this->get('/manifest/view/000-NOT-EXIST')->assertRedirect(route('mawb_list'));
    }

    public function test_search_endpoint_redirects_to_manifest(): void
    {
        $mawbno = $this->mawbWithHawbs();

        $this->get('/manifest?mawb='.urlencode($mawbno))
            ->assertRedirect(route('manifest', ['mawb' => $mawbno]));

        // 不带参数时回总单列表（原实现此路由指向不存在的方法，会 500）
        $this->get('/manifest')->assertRedirect(route('mawb_list'));
    }

    public function test_export_returns_valid_xlsx(): void
    {
        $mawbno = $this->mawbWithHawbs();
        $hawbs = Hawb::where('mawb', $mawbno)->orderBy('hawb')->get();

        $response = $this->get('/manifest/export/'.$mawbno);

        $response->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml.sheet',
            (string) $response->headers->get('content-type')
        );

        $binary = $response->streamedContent() ?: $response->getFile()->getContent();
        $this->assertStringStartsWith('PK', $binary, '导出内容不是 zip/xlsx');

        // 落到临时文件后用 ZipArchive 校验内部结构
        $path = tempnam(sys_get_temp_dir(), 'manifest').'.xlsx';
        file_put_contents($path, $binary);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path) === true, 'xlsx 无法打开');

        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $workbook = $zip->getFromName('xl/workbook.xml');
        $styles = $zip->getFromName('xl/styles.xml');
        $zip->close();
        @unlink($path);

        $this->assertNotFalse($sheet);
        $this->assertNotFalse($workbook);
        $this->assertNotFalse($styles);

        // 三个必需部件都能被 XML 解析
        foreach (['sheet' => $sheet, 'workbook' => $workbook, 'styles' => $styles] as $name => $xml) {
            $doc = @simplexml_load_string($xml);
            $this->assertNotFalse($doc, $name.' 不是合法 XML');
        }

        // 工作表名、合并标题、分单号、合计都在
        $this->assertStringContainsString('清单', $workbook);
        $this->assertStringContainsString('A1:I1', $sheet);
        $this->assertStringContainsString(env('CONF_HAWB_PREFIX').$hawbs->first()->hawb, $sheet);
        $this->assertStringContainsString('TOTAL:', $sheet);
        $this->assertStringContainsString('HOUSE CARGO MANIFEST', $sheet);
    }

    public function test_export_redirects_for_unknown_mawb(): void
    {
        $this->get('/manifest/export/000-NOT-EXIST')->assertRedirect(route('mawb_list'));
    }

    public function test_export_for_mawb_without_hawbs_redirects(): void
    {
        // 造一个只有总单记录、没有分单的号：用现有数据里"没有分单的总单"
        $mawbno = DB::table('mawbs')
            ->select('mawb')
            ->whereNotIn('mawb', DB::table('hawbs')->select('mawb'))
            ->where('mawb', '<>', '')
            ->value('mawb');

        if ($mawbno === null) {
            $this->assertTrue(true, '库中每个总单都有分单，跳过该用例');

            return;
        }

        $this->get('/manifest/export/'.urlencode($mawbno))->assertRedirect(route('mawb_list'));
        $this->get('/manifest/view/'.urlencode($mawbno))->assertRedirect(route('mawb_list'));
    }
}
