<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Port;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 数据维护模块（地址 / 目的港 / 客户 / 销售 / 通讯录）功能测试。
 *
 * 直连真实业务库 yh，读取真实数据；写操作全部包在事务里，测试结束回滚，
 * 不会留下任何痕迹。
 */
class DataMaintenanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->useBusinessDatabase();

        $this->actingAs(User::where('level', 1)->orderBy('id')->firstOrFail());
    }

    // -----------------------------------------------------------------
    // 列表页
    // -----------------------------------------------------------------

    public function test_list_pages_render_with_real_data(): void
    {
        $cases = [
            '/address/list' => ['地址列表', '分类'],
            '/port/list' => ['目的港列表', '国家地区'],
            '/client/list' => ['客户列表', '货源'],
            '/seller/list' => ['销售列表', '销售人'],
            '/contact/list' => ['联系人列表', '公司'],
        ];

        foreach ($cases as $url => [$title, $keyword]) {
            $this->get($url)
                ->assertOk()
                ->assertSee($title)
                ->assertSee($keyword);
        }
    }

    public function test_list_pages_show_actual_records(): void
    {
        $port = Port::orderBy('code')->firstOrFail();
        $address = Address::orderBy('code')->firstOrFail();
        $contact = Contact::orderBy('id')->firstOrFail();

        $this->get('/port/list')->assertOk()->assertSee($port->code);
        $this->get('/address/list')->assertOk()->assertSee($address->code);
        $this->get('/contact/list')->assertOk()->assertSee($contact->name);
    }

    public function test_list_search_filters_by_code(): void
    {
        $port = Port::orderBy('code')->firstOrFail();

        $this->get('/port/list?search_code='.urlencode($port->code))
            ->assertOk()
            ->assertSee($port->code);

        // 用一个不可能存在的代码，应查不到任何记录
        $this->get('/port/list?search_code=__NO_SUCH_PORT__')
            ->assertOk()
            ->assertDontSee($port->code.'</a>', false);
    }

    public function test_list_pagination_uses_bootstrap_markup(): void
    {
        // 目的港总量 50+，perpage 设成 5 应出现分页组件
        $html = $this->get('/port/list?perpage=5')->assertOk()->getContent();

        $this->assertStringContainsString('pagination', $html);
    }

    // -----------------------------------------------------------------
    // 表单页
    // -----------------------------------------------------------------

    public function test_form_pages_render(): void
    {
        DB::beginTransaction();

        try {
            // sellers 表目前为空，造一条用于验证"修改销售分配"页面
            $seller = Seller::orderBy('forward')->first()
                ?? Seller::create(['forward' => '__TSTSELLER__', 'seller' => 'TEST', 'remark' => '']);

            $port = Port::orderBy('code')->firstOrFail();
            $address = Address::orderBy('code')->firstOrFail();
            $client = Client::orderBy('code')->firstOrFail();
            $contact = Contact::orderBy('id')->firstOrFail();

            $pages = [
                '/port/add' => '添加目的港',
                '/port/view/'.$port->code => '修改目的港',
                '/address/add' => '添加地址',
                '/address/view/'.rawurlencode($address->cata).'/'.rawurlencode($address->code) => '修改地址',
                '/client/add' => '添加客户',
                '/client/view/'.rawurlencode($client->cata).'/'.rawurlencode($client->code) => '修改客户',
                '/seller/add' => '添加销售分配',
                '/seller/view/'.rawurlencode($seller->forward) => '修改销售分配',
                '/contact/add' => '添加联系人',
                '/contact/view/'.$contact->id => '修改联系人',
            ];

            foreach ($pages as $url => $title) {
                $this->get($url)->assertOk()->assertSee($title);
            }
        } finally {
            DB::rollBack();
        }
    }

    public function test_edit_form_is_prefilled(): void
    {
        $port = Port::orderBy('code')->firstOrFail();

        $this->get('/port/view/'.$port->code)
            ->assertOk()
            ->assertSee('value="'.e($port->code).'"', false)
            ->assertSee('value="'.e($port->name).'"', false);
    }

    // -----------------------------------------------------------------
    // 写操作（事务内，结束回滚）
    // -----------------------------------------------------------------

    public function test_port_crud_round_trip(): void
    {
        DB::beginTransaction();

        try {
            $code = '__TSTPORT__';

            $this->post('/port/add', [
                'code' => $code,
                'name' => 'TEST PORT',
                'zone' => 'TEST ZONE',
                'm' => '10',
                'n' => '20',
                'q' => '30',
            ])->assertRedirect(route('port_list', ['addno' => $code]));

            $this->assertDatabaseHas('ports', ['code' => $code, 'name' => 'TEST PORT'], 'mysql');

            $this->post('/port/view/'.$code, [
                'code' => $code,
                'name' => 'TEST PORT EDITED',
                'zone' => 'TEST ZONE',
                'm' => '11',
                'n' => '21',
                'q' => '31',
            ])->assertRedirect();

            $this->assertDatabaseHas('ports', ['code' => $code, 'name' => 'TEST PORT EDITED'], 'mysql');

            $this->get('/port/del/'.$code)
                ->assertRedirect(route('port_list', ['del' => 'yes', 'delno' => $code]));

            $this->assertDatabaseMissing('ports', ['code' => $code], 'mysql');
        } finally {
            DB::rollBack();
        }
    }

    public function test_port_create_rejects_duplicate_code(): void
    {
        DB::beginTransaction();

        try {
            $port = Port::orderBy('code')->firstOrFail();

            $this->post('/port/add', [
                'code' => $port->code,
                'name' => 'DUP',
                'zone' => 'DUP',
            ])->assertSessionHasErrors('code');

            $this->assertDatabaseHas('ports', ['code' => $port->code, 'name' => $port->name], 'mysql');
        } finally {
            DB::rollBack();
        }
    }

    public function test_address_create_rejects_same_code_in_same_category(): void
    {
        DB::beginTransaction();

        try {
            $addr = Address::orderBy('code')->firstOrFail();

            // 同分类同代码 → 走 alert 分支，不落库
            $this->post('/address/add', [
                'code' => $addr->code,
                'name' => 'DUP ADDR',
                'addr' => 'DUP ADDR LINE',
                'cata' => $addr->cata,
                'remark' => '',
            ])->assertOk()->assertSee('已存在');

            $this->assertDatabaseMissing('addresses', ['name' => 'DUP ADDR'], 'mysql');

            // 换一个分类，同代码允许共存
            $this->post('/address/add', [
                'code' => $addr->code,
                'name' => 'DUP ADDR',
                'addr' => 'DUP ADDR LINE',
                'cata' => '总单收货人',
                'remark' => '',
            ])->assertRedirect();

            $this->assertDatabaseHas('addresses', ['name' => 'DUP ADDR', 'cata' => '总单收货人'], 'mysql');
        } finally {
            DB::rollBack();
        }
    }

    public function test_address_update_and_delete(): void
    {
        DB::beginTransaction();

        try {
            $addr = Address::create([
                'code' => '__TSTADDR__',
                'name' => 'OLD NAME',
                'addr' => "LINE1\nLINE2",
                'cata' => '分单发货人',
                'remark' => '',
            ]);

            $this->post('/address/view/'.rawurlencode('分单发货人').'/__TSTADDR__', [
                'id' => $addr->id,
                'code' => '__TSTADDR__',
                'name' => 'NEW NAME',
                'addr' => "LINE1\nLINE2",
                'cata' => '分单发货人',
                'remark' => 'edited',
            ])->assertRedirect();

            $this->assertDatabaseHas('addresses', ['id' => $addr->id, 'name' => 'NEW NAME', 'remark' => 'edited'], 'mysql');

            $this->get('/address/del/'.rawurlencode('分单发货人').'/__TSTADDR__')
                ->assertRedirect(route('address_list', ['del' => 'yes', 'delno' => '__TSTADDR__']));

            $this->assertDatabaseMissing('addresses', ['id' => $addr->id], 'mysql');
        } finally {
            DB::rollBack();
        }
    }

    public function test_contact_crud_round_trip(): void
    {
        DB::beginTransaction();

        try {
            $this->post('/contact/add', [
                'code' => '__TSTCONTACT__',
                'name' => '测试联系人',
                'company' => '测试公司',
                'status' => '5',
                'gender' => '男',
                'title' => '',
                'mobile' => '',
                'tel' => '',
                'fax' => '',
                'mail' => '',
                'im' => '',
                'address' => '',
                'remark' => '',
            ])->assertRedirect();

            $contact = Contact::where('name', '测试联系人')->firstOrFail();

            // status 不在 fillable 里（与原项目一致），新建时用库默认值 1
            $this->assertSame(1, (int) $contact->status);

            // 修改时走 query builder update，status 可以被改
            $this->post('/contact/view/'.$contact->id, [
                'code' => $contact->code,
                'name' => '测试联系人改',
                'company' => '测试公司',
                'status' => '9',
                'gender' => '女',
                'title' => '经理',
                'mobile' => '13800000000',
                'tel' => '',
                'fax' => '',
                'mail' => '',
                'im' => '',
                'address' => '',
                'remark' => '',
            ])->assertRedirect();

            $this->assertDatabaseHas('contacts', ['id' => $contact->id, 'name' => '测试联系人改', 'status' => 9], 'mysql');

            $this->get('/contact/del/'.$contact->id)->assertRedirect();
            $this->assertDatabaseMissing('contacts', ['id' => $contact->id], 'mysql');
        } finally {
            DB::rollBack();
        }
    }

    public function test_contact_rejects_invalid_email(): void
    {
        DB::beginTransaction();

        try {
            $this->post('/contact/add', [
                'code' => '__TSTMAIL__',
                'name' => '邮箱错误',
                'company' => 'X',
                'mail' => 'not-an-email',
            ])->assertSessionHasErrors('mail');

            $this->assertDatabaseMissing('contacts', ['name' => '邮箱错误'], 'mysql');
        } finally {
            DB::rollBack();
        }
    }

    public function test_client_and_seller_create(): void
    {
        DB::beginTransaction();

        try {
            $this->post('/client/add', [
                'code' => '__TSTFORWARD__',
                'name' => 'TEST FORWARD',
                'cata' => '货源',
            ])->assertRedirect();

            $this->assertDatabaseHas('clients', ['code' => '__TSTFORWARD__', 'cata' => '货源'], 'mysql');

            $this->post('/seller/add', [
                'forward' => '__TSTFORWARD__',
                'seller' => 'TEST SELLER',
                'remark' => '',
            ])->assertRedirect();

            $this->assertDatabaseHas('sellers', ['forward' => '__TSTFORWARD__', 'seller' => 'TEST SELLER'], 'mysql');

            // 同一个货源重复分配 → unique 校验拦截
            $this->post('/seller/add', [
                'forward' => '__TSTFORWARD__',
                'seller' => 'ANOTHER',
                'remark' => '',
            ])->assertSessionHasErrors('forward');
        } finally {
            DB::rollBack();
        }
    }

    // -----------------------------------------------------------------
    // AJAX 联动接口
    // -----------------------------------------------------------------

    public function test_ajax_endpoints_return_json(): void
    {
        $port = Port::orderBy('code')->firstOrFail();
        $client = Client::where('cata', '货源')->first();
        $address = Address::where('cata', '分单发货人')->first();
        $contact = Contact::orderBy('id')->firstOrFail();

        $this->getJson('/get/dest/'.rawurlencode($port->code))
            ->assertOk()
            ->assertJsonPath('code', $port->code);

        if ($client) {
            $this->getJson('/get/forward/'.rawurlencode($client->code))
                ->assertOk()
                ->assertJsonPath('code', $client->code);
        }

        if ($address) {
            $this->getJson('/get/hshipper/'.rawurlencode($address->code))
                ->assertOk()
                ->assertJsonPath('cata', '分单发货人');
        }

        $this->getJson('/get/contact/'.rawurlencode($contact->code))
            ->assertOk()
            ->assertJsonPath('code', $contact->code);
    }
}
