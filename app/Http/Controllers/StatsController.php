<?php

namespace App\Http\Controllers;

use App\Models\Hawb;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 货量统计。
 *
 * 对照原项目 app/Http/Controllers/StatsController.php，只有 hawbQty 是真实实现。
 *
 * 原项目的 index()（统计首页）与 mawbQty()（总单货量）只是各 echo 一行文字
 * （"quantity stats" / "mawb quantity stats"），没有逻辑也没有页面。
 * 这两块 2026-10-29 按需求暂时取消入口（路由一并移除，访问即 404），
 * 需要时再按真实口径补，不要退回占位页。
 */
class StatsController extends Controller
{
    public function hawbQty(Request $request)
    {
        $title = '分单货量统计';

        $filter = $request->all();

        $query = Hawb::orderBy('fltdate', 'desc')->orderBy('regtime', 'desc')->orderBy('created_at', 'desc');

        if (isset($filter['filter_fltstart']) && $filter['filter_fltstart'] != '') {
            $query->where('fltdate', '>=', $filter['filter_fltstart']);
        }
        if (isset($filter['filter_fltend']) && $filter['filter_fltend'] != '') {
            $query->where('fltdate', '<=', $filter['filter_fltend']);
        }

        // 星期筛选（复选，0=周一 … 6=周日）
        if (isset($filter['filter_weekday']) && is_array($filter['filter_weekday'])) {
            $query->whereIn(DB::raw('weekday(fltdate)'), $filter['filter_weekday']);
        }

        foreach ([
            'dest' => 'filter_dest',
            'fltno' => 'filter_fltno',
            'carrier' => 'filter_carrier',
            'forward' => 'filter_forward',
            'factory' => 'filter_factory',
        ] as $column => $key) {
            if (isset($filter[$key]) && $filter[$key] != '') {
                $query->where($column, 'like', '%'.$filter[$key].'%');
            }
        }

        $query_count = $query->count();

        $hawbs = $query->paginate(isset($filter['perpage']) && $filter['perpage'] !== ''
            ? (int) $filter['perpage']
            : 200);

        $total_count = $hawbs->count();
        $total_num = $hawbs->sum('num');
        $total_gw = $hawbs->sum('gw');
        $total_cw = $hawbs->sum('cw');
        $total_cbm = $hawbs->sum('cbm');

        return view(theme('stats.hawbqty'), compact(
            'hawbs',
            'title',
            'total_count',
            'total_num',
            'total_gw',
            'total_cw',
            'total_cbm',
            'query_count'
        ))->with($filter);
    }
}
