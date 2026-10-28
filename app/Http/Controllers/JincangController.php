<?php

namespace App\Http\Controllers;

use App\Models\Jincang;
use Illuminate\Http\Request;

/**
 * 进仓管理：列表 / 登记 / 修改 / 删除 / 出仓状态切换。
 *
 * 对照原项目 app/Http/Controllers/JincangController.php。
 *
 * 与原实现的差异（仅两处，均为 MySQL 8 适配，业务语义不变）：
 *  1. 空航班日期写入 '0000-00-00'（原库以该值表示"未定"），原实现由
 *     非严格模式的 MySQL 自动转换，MySQL 8 下必须显式赋值；
 *  2. 新建时显式写入 status = 0，因为该列 NOT NULL 且无默认值。
 */
class JincangController extends Controller
{
    public function lists(Request $request)
    {
        $title = '进仓列表';

        $search = $request->all();

        $query = Jincang::orderBy('status', 'asc')->orderBy('jcno', 'desc');

        // 模糊匹配字段
        foreach ([
            'jcno' => 'search_jcno',
            'dest' => 'search_dest',
            'client' => 'search_client',
            'forward' => 'search_forward',
            'factory' => 'search_factory',
            'carrier' => 'search_carrier',
            'delivery' => 'search_delivery',
            'cargodata' => 'search_cargodata',
            'remark' => 'search_remark',
        ] as $column => $key) {
            if (isset($search[$key]) && $search[$key] != '') {
                $query->where($column, 'like', '%'.$search[$key].'%');
            }
        }

        // 区间匹配字段
        foreach ([
            'fltdate' => 'search_flt',
            'regdate' => 'search_reg',
        ] as $column => $prefix) {
            if (isset($search[$prefix.'start']) && $search[$prefix.'start'] != '') {
                $query->where($column, '>=', $search[$prefix.'start']);
            }
            if (isset($search[$prefix.'end']) && $search[$prefix.'end'] != '') {
                $query->where($column, '<=', $search[$prefix.'end']);
            }
        }

        $jincangs = $query->paginate(isset($search['perpage']) && $search['perpage'] !== ''
            ? (int) $search['perpage']
            : 20);

        return view(theme('jincang.list'), compact('jincangs', 'title'))->with($search);
    }

    public function show($jcno)
    {
        $title = '进仓修改';

        $jincang = Jincang::where('jcno', $jcno)->first();

        if (empty($jcno) || ! $jincang) {
            return redirect(route('jincang_list'));
        }

        return view(theme('jincang.form'), compact('jincang', 'title'));
    }

    public function add()
    {
        $title = '进仓登记';

        $last = Jincang::orderBy('id', 'desc')->first();
        $last_jcno = $last?->jcno;

        return view(theme('jincang.form'), compact('title', 'last_jcno'));
    }

    public function create(Request $request)
    {
        $request->validate([
            'regdate' => 'required|date',
            'jcno' => 'required|unique:jincang,jcno|regex:/^[0-9A-Z-]{6,12}$/',
            'forward' => 'required',
        ], [
            'jcno.regex' => '进仓编号只能是 6~12 位数字、大写字母或连字符。',
        ]);

        Jincang::create($this->payload($request) + ['status' => 0]);

        return redirect(route('jincang_list', ['addno' => $request->input('jcno')]));
    }

    public function update(Request $request, $jcno = null)
    {
        $jcno = $request->input('jcno', $jcno);

        $request->validate([
            'regdate' => 'required|date',
            'forward' => 'required',
        ]);

        $query = Jincang::where('jcno', $jcno)->update($this->payload($request));

        if ($query == '1') {
            return redirect(route('jincang_view', ['jcno' => $jcno, 'update' => 'yes']));
        }

        return redirect(route('jincang_view', ['jcno' => $jcno]));
    }

    public function delete($jcno)
    {
        $res = Jincang::where('jcno', $jcno)->first();

        if ($res) {
            Jincang::where('jcno', $jcno)->delete();

            return redirect(route('jincang_list', ['del' => 'yes', 'delno' => $jcno]));
        }

        return redirect(route('jincang_list', ['del' => 'no', 'delno' => $jcno]));
    }

    public function status($jcno)
    {
        $res = Jincang::where('jcno', $jcno)->first();

        if ($res) {
            if ($res->status == 1) {
                $res->status = 0;
                $res->save();
                $status = 'in';
            } else {
                $res->status = 1;
                $res->save();
                $status = 'out';
            }
        } else {
            $status = 'no';
        }

        return redirect(route('jincang_list', ['status' => $status, 'jcno' => $jcno]));
    }

    /**
     * 列表页点击"预配日期"，按分单的实际航班日期回填进仓单。
     */
    public function updateFltdate($hawb, $fltdate)
    {
        Jincang::where('jcno', $hawb)->update(['fltdate' => $fltdate]);

        return redirect(route('jincang_list'));
    }

    // ------------------------------------------------------------------

    /**
     * 组装可落库字段。这几列都是 NOT NULL 且无默认值，空值一律写空串；
     * 日期列空值写 '0000-00-00'（原库约定，"未定"）。
     */
    protected function payload(Request $request): array
    {
        $string = fn (string $key) => (string) $request->input($key, '');

        return [
            'regdate' => $string('regdate') ?: date('Y-m-d'),
            'jcno' => trim($string('jcno')),
            'dest' => $string('dest'),
            'fltdate' => $string('fltdate') ?: '0000-00-00',
            'client' => $string('client'),
            'forward' => $string('forward'),
            'factory' => $string('factory'),
            'carrier' => $string('carrier'),
            'delivery' => $string('delivery'),
            'cargodata' => $string('cargodata'),
            'remark' => $string('remark'),
        ];
    }
}
