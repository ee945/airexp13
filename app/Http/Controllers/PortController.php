<?php

namespace App\Http\Controllers;

use App\Models\Port;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * 目的港（含 M / N / Q 价），对照原项目 app/Http/Controllers/PortController.php
 */
class PortController extends Controller
{
    public function getPort($dest)
    {
        return Port::where('code', $dest)->first();
    }

    public function lists(Request $request)
    {
        $title = '目的港列表';

        $search = $request->all();

        $query = Port::orderBy('code', 'asc');

        if (isset($search['search_code']) && $search['search_code'] != '') {
            $query->where('code', 'like', '%'.$search['search_code'].'%');
        }
        if (isset($search['search_name']) && $search['search_name'] != '') {
            $query->where('name', 'like', '%'.$search['search_name'].'%');
        }
        if (isset($search['search_zone']) && $search['search_zone'] != '') {
            $query->where('zone', 'like', '%'.$search['search_zone'].'%');
        }

        $ports = $query->paginate(isset($search['perpage']) ? (int) $search['perpage'] : 20);

        return view(theme('port.list'), compact('ports', 'title'))->with($search);
    }

    public function show($code)
    {
        $title = '修改目的港';

        $port = Port::where('code', $code)->first();

        if (empty($code)) {
            return redirect(route('port_list'));
        }

        return view(theme('port.form'), compact('port', 'title'));
    }

    public function add()
    {
        $title = '添加目的港';

        return view(theme('port.form'), compact('title'));
    }

    public function create(Request $request)
    {
        $request->validate([
            'code' => 'required|unique:ports,code',
            'name' => 'required',
            'zone' => 'required',
        ]);

        Port::create($request->all());

        return redirect(route('port_list', ['addno' => $request->input('code')]));
    }

    public function update(Request $request)
    {
        $code = $request->input('code');

        $request->validate([
            'name' => 'required',
            'zone' => 'required',
        ]);

        $query = Port::where('code', $code)
            ->update(Arr::except($request->all(), ['_token', '_method']));

        if ($query == '1') {
            return redirect(route('port_view', ['code' => $code, 'update' => 'yes']));
        }

        return redirect(route('port_view', ['code' => $code]));
    }

    public function delete($code)
    {
        $res = Port::where('code', $code)->first();

        if ($res) {
            Port::where('code', $code)->delete();

            return redirect(route('port_list', ['del' => 'yes', 'delno' => $code]));
        }

        return redirect(route('port_list', ['del' => 'no', 'delno' => $code]));
    }
}
