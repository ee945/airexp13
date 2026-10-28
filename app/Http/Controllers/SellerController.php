<?php

namespace App\Http\Controllers;

use App\Models\Seller;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * 销售分配（按货源分配销售人），对照原项目 app/Http/Controllers/SellerController.php
 */
class SellerController extends Controller
{
    public function getSeller($forward)
    {
        return Seller::where('forward', $forward)->first();
    }

    public function lists(Request $request)
    {
        $title = '销售列表';

        $search = $request->all();

        $query = Seller::orderBy('forward', 'asc');

        if (isset($search['search_forward']) && $search['search_forward'] != '') {
            $query->where('forward', 'like', '%'.$search['search_forward'].'%');
        }
        if (isset($search['search_seller']) && $search['search_seller'] != '') {
            $query->where('seller', 'like', '%'.$search['search_seller'].'%');
        }
        if (isset($search['search_remark']) && $search['search_remark'] != '') {
            $query->where('remark', 'like', '%'.$search['search_remark'].'%');
        }

        $sellers = $query->paginate(isset($search['perpage']) ? (int) $search['perpage'] : 20);

        return view(theme('seller.list'), compact('sellers', 'title'))->with($search);
    }

    public function show($forward)
    {
        $title = '修改销售分配';

        $seller = Seller::where('forward', $forward)->first();

        if (empty($forward)) {
            return redirect(route('seller_list'));
        }

        return view(theme('seller.form'), compact('seller', 'title'));
    }

    public function add()
    {
        $title = '添加销售分配';

        return view(theme('seller.form'), compact('title'));
    }

    public function create(Request $request)
    {
        $request->validate([
            'forward' => 'required|unique:sellers,forward',
            'seller' => 'required',
        ]);

        Seller::create($request->all());

        return redirect(route('seller_list', ['addno' => $request->input('forward')]));
    }

    public function update(Request $request)
    {
        $forward = $request->input('forward');

        $request->validate([
            'seller' => 'required',
        ]);

        $query = Seller::where('forward', $forward)
            ->update(Arr::except($request->all(), ['_token', '_method']));

        if ($query == '1') {
            return redirect(route('seller_view', ['forward' => $forward, 'update' => 'yes']));
        }

        return redirect(route('seller_view', ['forward' => $forward]));
    }

    public function delete($forward)
    {
        $res = Seller::where('forward', $forward)->first();

        if ($res) {
            Seller::where('forward', $forward)->delete();

            return redirect(route('seller_list', ['del' => 'yes', 'delno' => $forward]));
        }

        return redirect(route('seller_list', ['del' => 'no', 'delno' => $forward]));
    }
}
