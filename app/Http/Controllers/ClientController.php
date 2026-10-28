<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * 客户（货源 / 生产单位 / 承运人），对照原项目 app/Http/Controllers/ClientController.php
 */
class ClientController extends Controller
{
    public function getForward($forwardcode)
    {
        return Client::where('code', $forwardcode)->where('cata', '货源')->first();
    }

    public function getFactory($factorycode)
    {
        return Client::where('code', $factorycode)->where('cata', '生产单位')->first();
    }

    public function getCarrierName($carrier)
    {
        return Client::where('code', $carrier)->where('cata', '承运人')->first();
    }

    public function lists(Request $request)
    {
        $title = '客户列表';

        $search = $request->all();

        $query = Client::orderBy('code', 'asc');

        if (isset($search['search_code']) && $search['search_code'] != '') {
            $query->where('code', 'like', '%'.$search['search_code'].'%');
        }
        if (isset($search['search_name']) && $search['search_name'] != '') {
            $query->where('name', 'like', '%'.$search['search_name'].'%');
        }
        if (isset($search['search_cata']) && $search['search_cata'] != '') {
            $query->where('cata', $search['search_cata']);
        }

        $clients = $query->paginate(isset($search['perpage']) ? (int) $search['perpage'] : 20);
        $total_count = $clients->count('id');

        return view(theme('client.list'), compact('clients', 'title', 'total_count'))->with($search);
    }

    public function show($cata, $code)
    {
        $title = '修改客户';

        $client = Client::where('code', $code)->where('cata', $cata)->first();

        if (empty($code)) {
            return redirect(route('client_list'));
        }

        return view(theme('client.form'), compact('client', 'title'));
    }

    public function add()
    {
        $title = '添加客户';

        return view(theme('client.form'), compact('title'));
    }

    public function create(Request $request)
    {
        $request->validate([
            'code' => 'required',
            'name' => 'required',
            'cata' => 'required',
        ]);

        $exists = Client::where('cata', $request->input('cata'))
            ->where('code', $request->input('code'))
            ->first();

        if (! empty($exists)) {
            return $this->alertBack($request->input('cata').' 已存在 '.$request->input('code'));
        }

        Client::create($request->all());

        return redirect(route('client_list', [
            'cata' => $request->input('cata'),
            'addno' => $request->input('code'),
        ]));
    }

    public function update(Request $request)
    {
        $code = $request->input('code');

        $request->validate([
            'name' => 'required',
            'cata' => 'required',
        ]);

        $query = Client::where('code', $code)->where('cata', $request->input('cata'));
        $querycount = $query->count();
        $queryid = $query->first() != null ? $query->first()->id : 0;
        $requestid = $request->input('id');

        if ($querycount == '0' or $queryid == $requestid) {
            Client::where('id', $request->input('id'))
                ->update(Arr::except($request->all(), ['_token', '_method']));

            return redirect(route('client_view', [
                'cata' => $request->input('cata'),
                'code' => $code,
                'update' => 'yes',
            ]));
        }

        return $this->alertBack($request->input('cata').' 已存在 '.$request->input('code'));
    }

    public function delete($cata, $code)
    {
        $res = Client::where('code', $code)->where('cata', $cata)->first();

        if ($res) {
            Client::where('code', $code)->where('cata', $cata)->delete();

            return redirect(route('client_list', ['del' => 'yes', 'delno' => $code]));
        }

        return redirect(route('client_list', ['del' => 'no', 'delno' => $code]));
    }
}
