<?php

use App\Http\Controllers\AddrController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\JincangController;
use App\Http\Controllers\HawbController;
use App\Http\Controllers\ManifestController;
use App\Http\Controllers\MawbController;
use App\Http\Controllers\PortController;
use App\Http\Controllers\PrintController;
use App\Http\Controllers\SellerController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\TrackController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 路由表
|--------------------------------------------------------------------------
|
| 与原项目 routes/web.php 一一对应。
| 已完成的模块走真实控制器；未完成模块暂时指向"重构中"占位页，
| 先把导航与链接结构跑通，再逐个替换为真实实现。
|
*/

// ---- 认证 ----
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login']);
});

Route::post('logout', [LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {

    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('home', [HomeController::class, 'index']);

    // =================================================================
    // AJAX：按代码取数据库记录，填入表单
    // =================================================================
    Route::get('get/dest/{dest}', [PortController::class, 'getPort']);
    Route::get('get/forward/{forwardcode}', [ClientController::class, 'getForward']);
    Route::get('get/factory/{factorycode}', [ClientController::class, 'getFactory']);
    Route::get('get/carrier/{carrier}', [ClientController::class, 'getCarrierName']);
    Route::get('get/seller/{forward}', [SellerController::class, 'getSeller']);
    Route::get('get/hshipper/{shippercode}', [AddrController::class, 'getHShipper']);
    Route::get('get/hconsignee/{consigneecode}', [AddrController::class, 'getHConsignee']);
    Route::get('get/hnotify/{notifycode}', [AddrController::class, 'getHNotify']);
    Route::get('get/mconsignee/{oversea}', [AddrController::class, 'getMConsignee']);
    Route::get('get/contact/{contactcode}', [ContactController::class, 'getContact']);

    // =================================================================
    // 地址库
    // =================================================================
    Route::any('address', fn () => redirect()->route('address_list'));
    Route::any('address/list', [AddrController::class, 'lists'])->name('address_list');
    Route::get('address/add', [AddrController::class, 'add'])->name('address_add');
    Route::post('address/add', [AddrController::class, 'create']);
    Route::get('address/view/{cata}/{code}', [AddrController::class, 'show'])->name('address_view');
    Route::post('address/view/{cata}/{code}', [AddrController::class, 'update']);
    Route::get('address/del/{cata}/{code}', [AddrController::class, 'delete'])->name('address_del');

    // =================================================================
    // 目的港
    // =================================================================
    Route::any('port', fn () => redirect()->route('port_list'));
    Route::any('port/list', [PortController::class, 'lists'])->name('port_list');
    Route::get('port/add', [PortController::class, 'add'])->name('port_add');
    Route::post('port/add', [PortController::class, 'create']);
    Route::get('port/view/{code}', [PortController::class, 'show'])->name('port_view');
    Route::post('port/view/{code}', [PortController::class, 'update']);
    Route::get('port/del/{code}', [PortController::class, 'delete'])->name('port_del');

    // =================================================================
    // 客户
    // =================================================================
    Route::any('client', fn () => redirect()->route('client_list'));
    Route::any('client/list', [ClientController::class, 'lists'])->name('client_list');
    Route::get('client/add', [ClientController::class, 'add'])->name('client_add');
    Route::post('client/add', [ClientController::class, 'create']);
    Route::get('client/view/{cata}/{code}', [ClientController::class, 'show'])->name('client_view');
    Route::post('client/view/{cata}/{code}', [ClientController::class, 'update']);
    Route::get('client/del/{cata}/{code}', [ClientController::class, 'delete'])->name('client_del');

    // =================================================================
    // 销售分配
    // =================================================================
    Route::any('seller', fn () => redirect()->route('seller_list'));
    Route::any('seller/list', [SellerController::class, 'lists'])->name('seller_list');
    Route::get('seller/add', [SellerController::class, 'add'])->name('seller_add');
    Route::post('seller/add', [SellerController::class, 'create']);
    Route::get('seller/view/{forward}', [SellerController::class, 'show'])->name('seller_view');
    Route::post('seller/view/{forward}', [SellerController::class, 'update']);
    Route::get('seller/del/{forward}', [SellerController::class, 'delete'])->name('seller_del');

    // =================================================================
    // 企业通讯录
    // =================================================================
    Route::any('contact', fn () => redirect()->route('contact_list'));
    Route::any('contact/list', [ContactController::class, 'lists'])->name('contact_list');
    Route::get('contact/add', [ContactController::class, 'add'])->name('contact_add');
    Route::post('contact/add', [ContactController::class, 'create']);
    Route::get('contact/view/{id}', [ContactController::class, 'show'])->name('contact_view');
    Route::post('contact/view/{id}', [ContactController::class, 'update']);
    Route::get('contact/del/{id}', [ContactController::class, 'delete'])->name('contact_del');

    // =================================================================
    // 进仓
    // =================================================================
    Route::any('jincang', fn () => redirect()->route('jincang_list'));
    Route::any('jincang/list', [JincangController::class, 'lists'])->name('jincang_list');
    Route::get('jincang/add', [JincangController::class, 'add'])->name('jincang_add');
    Route::post('jincang/add', [JincangController::class, 'create']);
    Route::get('jincang/view/{jcno}', [JincangController::class, 'show'])->name('jincang_view');
    Route::post('jincang/view/{jcno}', [JincangController::class, 'update']);
    Route::get('jincang/del/{jcno}', [JincangController::class, 'delete'])->name('jincang_del');
    Route::get('jincang/status/{jcno}', [JincangController::class, 'status'])->name('jincang_status');

    // 列表页点击"预配日期"，按分单实际航班日期回填进仓单
    Route::get('get/fltdate/{hawb}', [HawbController::class, 'getFltdate']);
    Route::get('update/fltdate/{hawb}/{fltdate}', [JincangController::class, 'updateFltdate']);

    // =================================================================
    // 分单
    // =================================================================
    Route::any('hawb', fn () => redirect()->route('hawb_list'));
    Route::any('hawb/list', [HawbController::class, 'lists'])->name('hawb_list');
    Route::get('hawb/add', [HawbController::class, 'add'])->name('hawb_add');
    Route::post('hawb/add', [HawbController::class, 'create']);
    Route::get('hawb/view/{hawb}', [HawbController::class, 'show'])->name('hawb_view');
    Route::post('hawb/view/{hawb}', [HawbController::class, 'update']);
    Route::get('hawb/del/{hawb}', [HawbController::class, 'delete'])->name('hawb_del');
    Route::get('hawb/print/{hawb}', [HawbController::class, 'hawbPrint'])->name('hawb_print');
    Route::get('hawb/printold/{hawb}', [HawbController::class, 'hawbPrint_old'])->name('hawb_print_old');
    Route::post('hawb/print/{hawb}', [HawbController::class, 'hawbSavePrint']);

    // =================================================================
    // 总单
    // =================================================================
    Route::any('mawb', fn () => redirect()->route('mawb_list'));
    Route::any('mawb/list', [MawbController::class, 'lists'])->name('mawb_list');
    Route::get('mawb/print/{mawb}', [MawbController::class, 'mawbPrint'])->name('mawb_print');
    Route::post('mawb/print/{mawb}', [MawbController::class, 'mawbSavePrint']);
    Route::get('mawb/del/{mawb}', [MawbController::class, 'delete'])->name('mawb_del');

    // =================================================================
    // 清单
    // =================================================================
    Route::get('manifest', [ManifestController::class, 'search'])->name('manifest_search');
    Route::get('manifest/view/{mawb}', [ManifestController::class, 'show'])->name('manifest');
    Route::get('manifest/export/{mawb}', [ManifestController::class, 'export'])->name('manifest_export');

    // =================================================================
    // 单证打印
    // =================================================================
    Route::get('print/hawb_old/{hawb}', [PrintController::class, 'printHawb_old'])->name('print_hawb_old');
    Route::get('print/hawb/{hawb}', [PrintController::class, 'printHawb'])->name('print_hawb');
    Route::get('print/mawb/{mawb}', [PrintController::class, 'printMawb'])->name('print_mawb');

    // =================================================================
    // 用户管理（原系统未实现，按 users 表补齐）
    // =================================================================
    Route::middleware(EnsureAdmin::class)->group(function () {
        Route::get('user/list', [UserController::class, 'lists'])->name('user_list');
        Route::get('user/add', [UserController::class, 'add'])->name('user_add');
        Route::post('user/add', [UserController::class, 'create'])->name('user_create');
        Route::get('user/view/{name}', [UserController::class, 'show'])->name('user_view');
        Route::post('user/view/{name}', [UserController::class, 'update'])->name('user_update');
        Route::get('user/status/{name}', [UserController::class, 'status'])->name('user_status');
        Route::post('user/reset/{name}', [UserController::class, 'reset'])->name('user_reset');

        // 兼容原站的两条表单式路由（POST user/edit、POST user/del）
        Route::post('user/edit', [UserController::class, 'edit'])->name('user_edit');
        Route::post('user/del', [UserController::class, 'del'])->name('user_del');
    });

    // -----------------------------------------------------------------
    // 报表（/report/*）、总单货量（/stats/mawb）、统计首页（/stats）：
    // 2026-10-29 按需求**暂时取消入口**，不再保留占位页，访问即 404。
    // 原因：原系统这三处本身就没有实现（ReportController 只有构造函数，
    // 三条 report/* 路由指向不存在的方法；stats 与 stats/mawb 只是各 echo
    // 一行文字），没有可参照的逻辑或页面。
    // 需要恢复时不要退回占位页，直接按 users 表的做法补真实控制器，并注意
    // mawbs 表只到 2016 年，总单维度统计要按 mawb 对 hawbs 分组。
    // -----------------------------------------------------------------

    // =================================================================
    // 货物追踪
    // =================================================================
    Route::get('track', [TrackController::class, 'index'])->name('track_index');
    Route::get('track/airline', [TrackController::class, 'airline'])->name('track_airline');
    Route::get('track/status/{mawb}', [TrackController::class, 'status'])->name('track_status');
    Route::get('track/arrival/{mawb}', [TrackController::class, 'arrival'])->name('track_arrival');
    Route::get('track/flight/{mawb}', [TrackController::class, 'flight'])->name('track_flight');

    // =================================================================
    // 统计（只保留分单货量；stats 首页与 stats/mawb 已暂时取消，见上方说明）
    // =================================================================
    Route::any('stats/hawb', [StatsController::class, 'hawbQty'])->name('stats_hawb');
});
