<?php

namespace App\Http\Controllers;

use App\Models\Hawb;
use App\Models\Mawb;
use Illuminate\Http\Request;

/**
 * 总单管理：列表 / 打印（可编辑保存）/ 删除。
 *
 * 对照原项目 app/Http/Controllers/MawbController.php。
 *
 * 与原实现的差异：
 *  1. 补上原站缺失的 delete()：原 routes/web.php 有 mawb/del 路由指向
 *     MawbController@delete，但控制器里没有该方法，点击删除即 500。
 *  2. 找不到总单记录时，原实现用 GROUP BY 只取 mawb 一列再带出 dest/fltno 等
 *     非聚合列，MySQL 8 的 ONLY_FULL_GROUP_BY 会直接报错；改为先取该总单下的
 *     分单集合再合成临时总单对象，语义一致且不依赖 sql_mode。
 *  3. 新建时显式写入 regtime（该列 NOT NULL 且无默认值，旧库靠非严格模式补零）。
 */
class MawbController extends Controller
{
    public function lists(Request $request)
    {
        $title = '总单列表';

        $search = $request->all();

        $query = Mawb::orderBy('fltdate', 'desc')->orderBy('regtime', 'desc');

        foreach ([
            'mawb' => 'search_mawb',
            'dest' => 'search_dest',
            'consignee' => 'search_consignee',
            'fltno' => 'search_fltno',
            'carrier' => 'search_carrier',
            'oversea' => 'search_oversea',
        ] as $column => $key) {
            if (isset($search[$key]) && $search[$key] != '') {
                $query->where($column, 'like', '%'.$search[$key].'%');
            }
        }

        if (isset($search['search_fltstart']) && $search['search_fltstart'] != '') {
            $query->where('fltdate', '>=', $search['search_fltstart']);
        }
        if (isset($search['search_fltend']) && $search['search_fltend'] != '') {
            $query->where('fltdate', '<=', $search['search_fltend']);
        }

        $mawbs = $query->paginate(isset($search['perpage']) && $search['perpage'] !== ''
            ? (int) $search['perpage']
            : 20);

        $total_num = $mawbs->sum('num');
        $total_gw = $mawbs->sum('gw');
        $total_cw = $mawbs->sum('cw');
        $total_cbm = $mawbs->sum('cbm');

        return view(theme('mawb.list'), compact('mawbs', 'title', 'total_num', 'total_gw', 'total_cw', 'total_cbm'))
            ->with($search);
    }

    /**
     * 总单打印 / 编辑。库里没有该总单时，用其下分单数据合成一张待补录的空单。
     */
    public function mawbPrint($mawbno)
    {
        $title = '总单打印';

        $mawb = Mawb::where('mawb', $mawbno)->first();

        if ($mawb === null) {
            $mawb = $this->draftFromHawbs($mawbno);

            // 该总单号下也没有分单，退回列表
            if ($mawb === null) {
                return redirect()->route('mawb_list');
            }
        }

        return view(theme('mawb.mawb'), compact('mawb', 'title'));
    }

    public function mawbSavePrint(Request $request)
    {
        $request->validate([
            'mawb' => 'required',
            'shipper' => 'required',
            'consignee' => 'required',
            'fltno' => ['required', 'regex:/^[A-Z0-9]{2}\d{2,4}$/'],
            'fltdate' => 'required|date',
            'package' => 'required',
            'num' => 'required|integer',
            'gw' => 'required|integer',
            'cw' => 'required|integer',
            'up' => 'required|numeric',
            'cgodescp' => 'required',
            'cbm' => 'required|numeric',
            'awn' => 'alpha',
            'myn' => 'alpha',
            'scn' => 'alpha',
            'aw' => 'required|numeric',
            'myup' => 'numeric',
            'scup' => 'numeric',
            'my' => 'numeric',
            'sc' => 'numeric',
            'rclass' => 'required|in:M,N,Q',
            'opdate' => 'required|date',
            'atplace' => 'required',
        ], [], [
            'mawb' => '总单号',
            'shipper' => '发货人',
            'consignee' => '收货人',
            'fltno' => '航班号',
            'fltdate' => '航班日期',
            'package' => '件数单位',
            'num' => '件数',
            'gw' => '毛重',
            'cw' => '收费重量',
            'up' => '运价',
            'cgodescp' => '品名',
            'cbm' => '体积',
            'aw' => '制单费',
            'rclass' => '运价类别',
            'opdate' => '制单日期',
            'atplace' => '制单地点',
        ]);

        $mawbno = $request->input('mawb');

        // 只取 mawbs 表真实存在的列：原实现用 except(['_token','curr'])，
        // 保存成功跳转后 URL 带 ?save=yes，再次保存会把 save 当列名写库而报错。
        $data = $request->only([
            'mawb', 'oversea', 'dest', 'desti', 'depa', 'depar', 'shipper', 'consignee',
            'agentabbr', 'agentcode', 'agentaccount', 'carrier', 'fltno', 'fltdate',
            'special', 'package', 'num', 'gw', 'cw', 'cbm', 'rclass', 'up', 'freight',
            'awn', 'myn', 'scn', 'myup', 'scup', 'aw', 'my', 'sc', 'other', 'amount',
            'cgodescp', 'signature', 'atplace', 'operator', 'opdate',
        ]);

        $mawb = Mawb::where('mawb', $mawbno)->first();

        if ($mawb === null) {
            // 新建：regtime 不能为空
            $data['regtime'] = now();
            Mawb::create($data);
        } else {
            Mawb::where('mawb', $mawbno)->update($data);
        }

        return redirect()->route('mawb_print', ['mawb' => $mawbno, 'save' => 'yes']);
    }

    public function delete($mawbno)
    {
        $deleted = Mawb::where('mawb', $mawbno)->delete();

        return redirect()->route('mawb_list', [
            'del' => $deleted ? 'yes' : 'no',
            'delno' => $mawbno,
        ]);
    }

    /**
     * 用该总单号下的分单合成一张待补录的总单（不落库）。
     */
    protected function draftFromHawbs(string $mawbno): ?Mawb
    {
        $hawbs = Hawb::where('mawb', $mawbno)->orderBy('hawb')->get();

        if ($hawbs->isEmpty()) {
            return null;
        }

        $first = $hawbs->first();

        $mawb = new Mawb();
        $mawb->mawb = $mawbno;
        $mawb->num = (int) $hawbs->sum('num');
        $mawb->gw = (int) $hawbs->sum('gw');
        $mawb->cw = (int) $hawbs->sum('cw');
        $mawb->cbm = round((float) $hawbs->sum('cbm'), 3);

        foreach (['dest', 'desti', 'fltno', 'fltdate', 'opdate', 'depar'] as $column) {
            $mawb->{$column} = $first->{$column};
        }

        // 预设值（与原实现一致）
        $mawb->shipper = str_replace('\n', "\n", env('CONF_AGENT_ADDR', "COMPANY NAME\nADDRESS"));
        $mawb->carrier = substr((string) $first->fltno, 0, 2);
        $mawb->cgodescp = 'CONSOL CARGO';
        $mawb->atplace = 'SHANGHAI';
        $mawb->signature = env('CONF_AGENT_CODE', 'CODE').'/SHA';
        $mawb->operator = '';
        $mawb->aw = 50;
        $mawb->oversea = '';
        $mawb->agentabbr = '';
        $mawb->agentcode = '';
        $mawb->agentaccount = '';
        $mawb->consignee = '';
        $mawb->special = '';
        $mawb->package = '';
        $mawb->rclass = '';
        $mawb->up = 0;
        $mawb->freight = 0;
        $mawb->awn = '';
        $mawb->myn = '';
        $mawb->scn = '';
        $mawb->myup = 0;
        $mawb->scup = 0;
        $mawb->my = 0;
        $mawb->sc = 0;
        $mawb->other = 0;
        $mawb->amount = 0;
        $mawb->depa = '';

        // 999 开头的运单号需带出制单费/油/战险代码
        if (substr($mawbno, 0, 3) === '999') {
            $mawb->awn = 'AWC';
            $mawb->myn = 'MYC';
            $mawb->scn = 'MSC';
        }

        return $mawb;
    }
}
