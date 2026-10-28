<?php

namespace App\Http\Controllers;

use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * 地址库（分单发货人 / 收货人 / 通知人，总单发货人 / 收货人）
 *
 * 对照原项目 app/Http/Controllers/AddrController.php，逻辑一一对应。
 */
class AddrController extends Controller
{
    // ---------------------------------------------------------------
    // AJAX 联动：按代码取地址，供分单 / 总单表单自动填充
    // ---------------------------------------------------------------

    public function getHShipper($shippercode)
    {
        return Address::where('code', $shippercode)->where('cata', '分单发货人')->first();
    }

    public function getHConsignee($consigneecode)
    {
        return Address::where('code', $consigneecode)->where('cata', '分单收货人')->first();
    }

    public function getHNotify($notifycode)
    {
        return Address::where('code', $notifycode)->where('cata', '分单通知人')->first();
    }

    public function getMConsignee($oversea)
    {
        return Address::where('code', $oversea)->where('cata', '总单收货人')->first();
    }

    // ---------------------------------------------------------------
    // CRUD
    // ---------------------------------------------------------------

    public function lists(Request $request)
    {
        $title = '地址列表';

        $search = $request->all();

        $query = Address::orderBy('code', 'asc');

        if (isset($search['search_code']) && $search['search_code'] != '') {
            $query->where('code', 'like', '%'.$search['search_code'].'%');
        }
        if (isset($search['search_name']) && $search['search_name'] != '') {
            $query->where('name', 'like', '%'.$search['search_name'].'%');
        }
        if (isset($search['search_addr']) && $search['search_addr'] != '') {
            $query->where('addr', 'like', '%'.$search['search_addr'].'%');
        }
        if (isset($search['search_cata']) && $search['search_cata'] != '') {
            $query->where('cata', $search['search_cata']);
        }
        if (isset($search['search_remark']) && $search['search_remark'] != '') {
            $query->where('remark', 'like', '%'.$search['search_remark'].'%');
        }

        $addrs = $query->paginate(isset($search['perpage']) ? (int) $search['perpage'] : 20);

        return view(theme('address.list'), compact('addrs', 'title'))->with($search);
    }

    public function show($cata, $code)
    {
        $title = '修改地址';

        $addr = Address::where('code', $code)->where('cata', $cata)->first();

        if (empty($code)) {
            return redirect(route('address_list'));
        }

        return view(theme('address.form'), compact('addr', 'title'));
    }

    public function add()
    {
        $title = '添加地址';

        return view(theme('address.form'), compact('title'));
    }

    public function create(Request $request)
    {
        $request->validate([
            'code' => 'required',
            'name' => 'required',
            'addr' => 'required',
            'cata' => 'required',
        ]);

        $exists = Address::where('cata', $request->input('cata'))
            ->where('code', $request->input('code'))
            ->first();

        if (! empty($exists)) {
            return $this->alertBack($request->input('cata').' 已存在 '.$request->input('code'));
        }

        Address::create($request->all());

        return redirect(route('address_list', [
            'cata' => $request->input('cata'),
            'addno' => $request->input('code'),
        ]));
    }

    public function update(Request $request)
    {
        $code = $request->input('code');

        $request->validate([
            'name' => 'required',
            'addr' => 'required',
            'cata' => 'required',
        ]);

        $query = Address::where('code', $code)->where('cata', $request->input('cata'));
        $querycount = $query->count();
        $queryid = $query->first() != null ? $query->first()->id : 0;
        $requestid = $request->input('id');

        // 未找到冲突记录，或修改的就是当前记录 → 按 id 更新
        if ($querycount == '0' or $queryid == $requestid) {
            Address::where('id', $request->input('id'))
                ->update(Arr::except($request->all(), ['_token', '_method']));

            return redirect(route('address_view', [
                'cata' => $request->input('cata'),
                'code' => $code,
                'update' => 'yes',
            ]));
        }

        return $this->alertBack($request->input('cata').' 已存在 '.$request->input('code'));
    }

    public function delete($cata, $code)
    {
        $res = Address::where('code', $code)->where('cata', $cata)->first();

        if ($res) {
            Address::where('code', $code)->where('cata', $cata)->delete();

            return redirect(route('address_list', ['del' => 'yes', 'delno' => $code]));
        }

        return redirect(route('address_list', ['del' => 'no', 'delno' => $code]));
    }
}
