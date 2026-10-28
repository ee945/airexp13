<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * 企业通讯录（联系人），对照原项目 app/Http/Controllers/ContactController.php
 */
class ContactController extends Controller
{
    public function getContact($contactcode)
    {
        return Contact::where('code', $contactcode)->first();
    }

    public function lists(Request $request)
    {
        $title = '联系人列表';

        $search = $request->all();

        $query = Contact::where('status', '>', 0)
            ->orderBy('status', 'desc')
            ->orderBy('name', 'asc');

        if (isset($search['search_code']) && $search['search_code'] != '') {
            $query->where('code', 'like', '%'.$search['search_code'].'%');
        }
        if (isset($search['search_name']) && $search['search_name'] != '') {
            $query->where('name', 'like', '%'.$search['search_name'].'%');
        }
        if (isset($search['search_company']) && $search['search_company'] != '') {
            $query->where('company', 'like', '%'.$search['search_company'].'%');
        }
        if (isset($search['search_title']) && $search['search_title'] != '') {
            $query->where('title', 'like', '%'.$search['search_title'].'%');
        }
        if (isset($search['search_mobile']) && $search['search_mobile'] != '') {
            $query->where('mobile', 'like', '%'.$search['search_mobile'].'%');
        }

        $contacts = $query->paginate(isset($search['perpage']) ? (int) $search['perpage'] : 40);

        return view(theme('contact.list'), compact('contacts', 'title'))->with($search);
    }

    public function show($id)
    {
        $title = '修改联系人';

        $contact = Contact::find($id);

        return view(theme('contact.form'), compact('contact', 'title'));
    }

    public function add()
    {
        $title = '添加联系人';

        return view(theme('contact.form'), compact('title'));
    }

    public function create(Request $request)
    {
        $request->validate([
            'code' => 'unique:contacts,code',
            'name' => 'required',
            'company' => 'required',
            'mail' => 'email',
        ]);

        Contact::create($request->all());

        return redirect(route('contact_list', ['addname' => $request->input('name')]));
    }

    public function update($id, Request $request)
    {
        $contact = Contact::find($id);

        // 只有改了代码才需要校验唯一性
        if ($contact->code != $request->input('code')) {
            $request->validate([
                'code' => 'unique:contacts,code',
            ]);
        }

        $request->validate([
            'name' => 'required',
            'company' => 'required',
            'mail' => 'email',
            'status' => 'required|integer|min:1',
        ]);

        $query = Contact::where('id', $contact->id)
            ->update(Arr::except($request->all(), ['_token', '_method']));

        if ($query == '1') {
            return redirect(route('contact_view', ['id' => $id, 'update' => 'yes']));
        }

        return redirect(route('contact_view', ['id' => $id]));
    }

    public function delete($id)
    {
        $contact = Contact::find($id);

        if ($contact) {
            $contact->delete();

            return redirect(route('contact_list', ['del' => 'yes', 'delname' => $contact->name]));
        }

        return redirect(route('contact_list', ['del' => 'no', 'delno' => $id]));
    }
}
