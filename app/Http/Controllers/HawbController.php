<?php

namespace App\Http\Controllers;

use App\Models\Hawb;
use Illuminate\Http\Request;

/**
 * 分单管理：列表 / 输入 / 修改 / 删除 / 打印（新版与旧版）/ 打印页保存。
 *
 * 对照原项目 app/Http/Controllers/HawbController.php 与
 * app/Http/Requests/{Create,Update}HawbRequest.php。
 *
 * 与原实现的差异（均为 MySQL 8 / L13 适配）：
 *  1. 新建时补齐所有 NOT NULL 但表单未提交的列（shipper/consignee/package...），
 *     旧实现依赖 MySQL 非严格模式自动填 0 或空串；
 *  2. regtime 显式写 '0000-00-00 00:00:00'，与历史数据保持一致；
 *  3. 自定义 ismawb（总单号模七校验）规则迁到 AppServiceProvider 注册。
 */
class HawbController extends Controller
{
    /** 表单会提交、但库中为 NOT NULL 的文本列，缺省一律写空串 */
    protected const TEXT_COLUMNS = [
        'mawb', 'dest', 'desti', 'fltno', 'forward', 'seller', 'factory',
        'carrier', 'carriername', 'paymt', 'remark', 'depar', 'consignee',
        'notify', 'shipper', 'curr', 'nvd', 'ncv', 'package', 'rclass',
        'special', 'cgodescp', 'agentabbr',
    ];

    public function getFltdate($hawb)
    {
        return Hawb::where('hawb', $hawb)->first();
    }

    public function lists(Request $request)
    {
        $title = '分单列表';

        $search = $request->all();

        $query = Hawb::orderBy('fltdate', 'desc')
            ->orderBy('regtime', 'desc')
            ->orderBy('created_at', 'desc');

        foreach ([
            'hawb' => 'search_hawb',
            'mawb' => 'search_mawb',
            'dest' => 'search_dest',
            'fltno' => 'search_fltno',
            'forward' => 'search_forward',
            'factory' => 'search_factory',
            'paymt' => 'search_paymt',
            'carrier' => 'search_carrier',
            'consignee' => 'search_consignee',
            'seller' => 'search_seller',
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

        $hawbs = $query->paginate(isset($search['perpage']) && $search['perpage'] !== ''
            ? (int) $search['perpage']
            : 20);

        // 当前页合计：件数 / 实际重量 / 收费重量 / 体积
        $total_count = $hawbs->count();
        $total_num = $hawbs->sum('num');
        $total_gw = $hawbs->sum('gw');
        $total_cw = $hawbs->sum('cw');
        $total_cbm = $hawbs->sum('cbm');

        return view(theme('hawb.list'), compact(
            'hawbs', 'title', 'total_count', 'total_num', 'total_gw', 'total_cw', 'total_cbm'
        ))->with($search);
    }

    public function show($hawb)
    {
        $title = '分单修改';

        $hawb = Hawb::where('hawb', $hawb)->first();

        if (empty($hawb)) {
            return redirect(route('hawb_list'));
        }

        return view(theme('hawb.form'), compact('hawb', 'title'));
    }

    public function add()
    {
        $title = '分单输入';

        return view(theme('hawb.form'), compact('title'));
    }

    public function create(Request $request)
    {
        $this->validateHawb($request, true);

        Hawb::create($this->payload($request) + ['regtime' => '0000-00-00 00:00:00']);

        return redirect(route('hawb_list', ['addno' => $request->input('hawb')]));
    }

    public function update(Request $request, $hawb = null)
    {
        $hawb = $request->input('hawb', $hawb);

        $this->validateHawb($request, false);

        $query = Hawb::where('hawb', $hawb)->update($this->payload($request));

        if ($query == '1') {
            return redirect(route('hawb_view', ['hawb' => $hawb, 'update' => 'yes']));
        }

        return redirect(route('hawb_view', ['hawb' => $hawb]));
    }

    public function delete($hawb)
    {
        $res = Hawb::where('hawb', $hawb)->first();

        if ($res) {
            Hawb::where('hawb', $hawb)->delete();

            return redirect(route('hawb_list', ['del' => 'yes', 'delno' => $hawb]));
        }

        return redirect(route('hawb_list', ['del' => 'no', 'delno' => $hawb]));
    }

    public function hawbPrint($hawb)
    {
        $title = '分单打印';

        $hawb = Hawb::where('hawb', $hawb)->first();

        if (empty($hawb)) {
            return redirect(route('hawb_list'));
        }

        return view(theme('hawb.hawb'), compact('hawb', 'title'))
            ->with($this->printDefaults($hawb));
    }

    public function hawbPrint_old($hawb)
    {
        $title = '分单打印';

        $hawb = Hawb::where('hawb', $hawb)->first();

        if (empty($hawb)) {
            return redirect(route('hawb_list'));
        }

        return view(theme('hawb.hawb_old'), compact('hawb', 'title'))
            ->with($this->printDefaults($hawb));
    }

    public function hawbSavePrint(Request $request)
    {
        $hawb = $request->input('hawb');

        $request->validate([
            'shipper' => 'required',
            'consignee' => 'required',
            'notify' => 'required',
            'rclass' => 'required|in:M,N,Q',
            'cgodescp' => 'required',
        ]);

        // 打印页只允许更新已知列，代码类输入框（shippercode 等）不入库；
        // 未提交的列保持原值，避免把卖家、货源等冲成空串。
        $data = [];

        foreach (self::TEXT_COLUMNS as $column) {
            if ($request->has($column)) {
                $data[$column] = (string) $request->input($column, '');
            }
        }

        if ($request->has('hawb')) {
            $data['hawb'] = trim((string) $request->input('hawb'));
        }
        if ($request->has('opdate')) {
            $data['opdate'] = ((string) $request->input('opdate')) ?: date('Y-m-d');
        }
        if ($request->has('fltdate')) {
            $data['fltdate'] = ((string) $request->input('fltdate')) ?: '0000-00-00';
        }
        foreach (['num', 'gw', 'cw', 'arranged'] as $column) {
            if ($request->has($column)) {
                $data[$column] = (int) $request->input($column, 0);
            }
        }
        if ($request->has('cbm')) {
            $data['cbm'] = (float) $request->input('cbm', 0);
        }

        $query = Hawb::where('hawb', $hawb)->update($data);

        if ($query == '1') {
            return redirect(route('hawb_print', ['hawb' => $hawb, 'save' => 'yes']));
        }

        return redirect(route('hawb_print', ['hawb' => $hawb]));
    }

    // ------------------------------------------------------------------

    protected function validateHawb(Request $request, bool $isCreate): void
    {
        $rules = [
            'opdate' => 'required|date',
            'hawb' => $isCreate
                ? 'required|unique:hawbs,hawb|regex:/^[0-9A-Z-]{6,12}$/'
                : 'required',
            'mawb' => 'regex:/^\d{3}-\d{8}$/|ismawb',
            'dest' => 'required',
            'desti' => 'required',
            'fltno' => 'regex:/^[A-Z0-9]{2}\d{2,4}$/',
            'fltdate' => 'required|date',
            'forward' => 'required',
            'seller' => 'required',
            'factory' => 'required',
            'carrier' => 'min:2|max:4',
            'num' => 'required|numeric|min:1',
            'gw' => 'required|numeric|min:1',
            'cw' => 'required|numeric|min:1',
            'cbm' => 'required|numeric|min:0.001',
            'paymt' => 'required|in:CC,CP,PP,PC',
            'arranged' => 'required',
        ];

        $request->validate($rules, [
            'hawb.regex' => '分单号只能是 6~12 位数字、大写字母或连字符。',
            'mawb.regex' => '总单号格式应为 3 位数字 + 连字符 + 8 位数字，例如 018-93096776。',
            'mawb.ismawb' => '总单号末位校验码不正确。',
            'fltno.regex' => '航班号格式应为 2 位字母数字 + 2~4 位数字，例如 HO1380。',
        ]);
    }

    /**
     * 组装分单可落库字段。
     */
    protected function payload(Request $request): array
    {
        $string = fn (string $key) => (string) $request->input($key, '');

        $data = [];

        foreach (self::TEXT_COLUMNS as $column) {
            $data[$column] = $string($column);
        }

        $data['opdate'] = $string('opdate') ?: date('Y-m-d');
        $data['fltdate'] = $string('fltdate') ?: '0000-00-00';
        $data['hawb'] = trim($string('hawb'));
        $data['num'] = (int) $request->input('num', 0);
        $data['gw'] = (int) $request->input('gw', 0);
        $data['cw'] = (int) $request->input('cw', 0);
        $data['cbm'] = (float) $request->input('cbm', 0);
        $data['arranged'] = (int) $request->input('arranged', 0);

        return $data;
    }

    /**
     * 打印页的预置值：按付费方式决定币种与预付/到付标记。
     */
    protected function printDefaults(Hawb $hawb): array
    {
        $data = [
            'curr' => '',
            'wtp' => '',
            'wtc' => '',
            'otp' => '',
            'otc' => '',
        ];

        switch ($hawb->paymt) {
            case 'PP':
                $data = array_merge($data, ['curr' => 'CNY', 'wtp' => 'P', 'otp' => 'P']);
                break;
            case 'CP':
                $data = array_merge($data, ['curr' => 'USD', 'wtc' => 'C', 'otp' => 'P']);
                break;
            case 'CC':
                $data = array_merge($data, ['curr' => 'USD', 'wtc' => 'C', 'otc' => 'C']);
                break;
            case 'PC':
                $data = array_merge($data, ['curr' => 'CNY', 'wtp' => 'P', 'otc' => 'C']);
                break;
        }

        return array_merge($data, [
            'depar' => 'SHANGHAI',
            'notify' => 'SAME AS CONSIGNEE',
            'nvd' => 'NVD.',
            'ncv' => '',
            'agentabbr' => env('CONF_AGENT_CODE', 'CODE').'/SHA',
        ]);
    }
}
