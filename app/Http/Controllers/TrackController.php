<?php

namespace App\Http\Controllers;

use App\Models\Hawb;
use App\Models\Mawb;
use Carbon\Carbon;
use Illuminate\View\View;

/**
 * 货物追踪。
 *
 * 对照原项目 app/Http/Controllers/TrackController.php 补全：
 *
 *   index   —— 按「明日 / 今日 / 昨日 / 前日」四个航班日期列出分单（原样迁移）
 *   airline —— 原站只 echo 一句 "under construction"，现按下面的承运人表生成真实列表
 *   status  —— 原站是占位文本，现改为查本系统库里的总单 + 分单明细
 *   arrival —— 运抵：PACTL 直接跳转查询页，东航物流用隐藏表单 POST（与原站一致）
 *   flight  —— 航班货况：按总单前三位分发到对应航司查询页
 *
 * 原站的 flight() 里，国航 / 东航 / 马航 / UPS / 土航 / 空桥六家是「服务端
 * file_get_contents + 正则抠表格 + echo 原始 HTML」。那套实现依赖 2016 年的页面
 * 结构和硬编码的 __VIEWSTATE，上游站点全部改版或下线，照搬只会输出空白或超时。
 * 这里保留「航司分发」的行为，抓取型改为给出该航司官网查询入口，由用户手动查。
 */
class TrackController extends Controller
{
    /**
     * 运抵查询：浦东货站（PACTL）直接跳转运单查询页。
     */
    protected const ARRIVAL_PACTL = [
        '828', '020', '023', '043', '065', '071', '160', '176',
        '232', '235', '406', '580', '607', '695', '738', '999',
    ];

    /**
     * 运抵查询：东航物流（需要 POST，用隐藏表单自动提交）。
     */
    protected const ARRIVAL_CEAIR = [
        '016', '018', '098', '112', '126', '172', '205', '217',
        '297', '501', '537', '618', '672', '756', '784', '843', '933', '988',
    ];

    /**
     * 航班 / 货况查询：总单前三位 => [航司名, 带总单号的查询地址, 官网入口]。
     *
     * url 为空表示原站是服务端抓取、没有可直接拼的查询地址，只能给官网入口。
     * {p3} / {p8} 会被替换成总单的三位代号与八位序号。
     */
    protected const FLIGHT = [
        '999' => ['国航货运', null, 'https://www.airchinacargo.com/'],
        '112' => ['东航物流', null, 'http://cargo2.ceair.com/'],
        '784' => ['东航物流', null, 'http://cargo2.ceair.com/'],
        '172' => ['东航物流', null, 'http://cargo2.ceair.com/'],
        '217' => ['东航物流', null, 'http://cargo2.ceair.com/'],
        '756' => ['东航物流', null, 'http://cargo2.ceair.com/'],
        '018' => ['东航物流', null, 'http://cargo2.ceair.com/'],
        '537' => ['东航物流', null, 'http://cargo2.ceair.com/'],
        '618' => ['东航物流', null, 'http://cargo2.ceair.com/'],
        '843' => ['东航物流', null, 'http://cargo2.ceair.com/'],
        '160' => ['国泰货运', 'http://www.cathaypacificcargo.com/%E7%AE%A1%E7%90%86%E6%82%A8%E7%9A%84%E8%B4%A7%E4%BB%B6/%E8%B4%A7%E4%BB%B6%E8%BF%BD%E8%B8%AA/tabid/110/SingleAWBNo/{p3}-{p8}/language/zh-CN/Default.aspx', null],
        '043' => ['国泰货运', 'http://www.cathaypacificcargo.com/%E7%AE%A1%E7%90%86%E6%82%A8%E7%9A%84%E8%B4%A7%E4%BB%B6/%E8%B4%A7%E4%BB%B6%E8%BF%BD%E8%B8%AA/tabid/110/SingleAWBNo/{p3}-{p8}/language/zh-CN/Default.aspx', null],
        '232' => ['马航货运', null, 'http://www.maskargo.com/'],
        '406' => ['UPS', null, 'https://www.ups.com/'],
        '235' => ['土耳其航空货运', null, 'http://www.turkishcargo.com.tr/en/e-cargo/cargo-tracking'],
        '016' => ['美联航货运', 'https://booking.unitedcargo.com/skychain/app?service=page/nwp:Trackshipmt&doc_typ=AWB&awb_pre={p3}&awb_no={p8}&job_no=', null],
        '695' => ['长荣航空货运', 'http://www.brcargo.com/ec_web/Default.aspx?Parm2=191&Parm3=?TNT_FLAG=Y&AWB_CODE={p3}&MAWB_NUMBER={p8}', null],
        '988' => ['韩亚航空', 'https://www.asianacargo.com/tracking/viewTraceAirWaybill.do?lang=zh&mawb=988-{p8}', null],
        '501' => ['丝路西部航空', 'http://www.silkwaywest.com/pages/awb.php?awb={p3}{p8}&pfx=', null],
        '933' => ['日本货物航空', 'https://www.nca.aero/icoportal/jsp/operations/shipment/AWBTracking.jsf?prefix1={p3}&doc1={p8}', null],
        '699' => ['友和道通航空', 'http://www.uni-top.com:8081/CargoTracking/queryWaybill.aspx?mawb={p8}', null],
        '020' => ['汉莎货运', 'https://lufthansa-cargo.com/eservices/etracking/awb-details?p_p_id=etrkawbdetails_WAR_etrkportlet&p_p_lifecycle=0&_etrkawbdetails_WAR_etrkportlet_fromPortlet=OverviewAwbStatus&_etrkawbdetails_WAR_etrkportlet_selectedAwb={p3}-{p8}&_etrkawbdetails_WAR_etrkportlet_searchFor=AWB#filter=NAVIGATION', null],
        '176' => ['阿联酋航空货运', 'https://skychain.emirates.com/skychain/app?PID=WEB01-10&doc_typ=AWB&awb_pre={p3}&awb_no={p8}&job_no=', null],
        '098' => ['印度航空', 'http://www.airindia.in/cargo-tracking.htm?MAWB={p3}-{p8}', null],
        '607' => ['阿提哈德航空', 'https://www.etihadcargo.com/content/eag/egcmc/etihadcargo/en-ae/track-and-trace.html?awb={p3}{p8}', null],
        '126' => ['印尼鹰航', 'https://icms.garuda-indonesia.com/HtmlFiles/AWBTracking/AWBTracking.html?BasedOn=0&AWBNo={p8}&CarrierCode={p3}', null],
        '580' => ['空桥货运', null, 'https://www.airbridgecargo.com/en/tracking/'],
        '205' => ['全日空货运', 'https://cargo.ana.co.jp/anaicportal/portal/trackshipments?trkTxnValue={p3}-{p8}', null],
        '023' => ['联邦快递', 'https://www.fedex.com/apps/fedextrack/index.html?tracknumbers={p3}{p8}&cntry_code=cn', null],
        '828' => ['香港空运货站', 'https://www.hkaircargo.com/sc/track-your-shipment/?Code={p3}&WaybillNo={p8}', null],
        '297' => ['中华航空', 'https://cargo.china-airlines.com/ccnetv2/content/manage/ShipmentTracking.aspx', null],
    ];

    // ------------------------------------------------------------------
    // 页面
    // ------------------------------------------------------------------

    public function index()
    {
        $title = '货物追踪';

        $today = Carbon::today();
        $tomorrow = Carbon::tomorrow();
        $yesterday = Carbon::yesterday();

        $order = fn () => Hawb::orderBy('fltdate', 'desc')->orderBy('hawb', 'desc');

        $hawb_today = $order()->where('fltdate', $today->toDateString())->get();
        $hawb_after1 = $order()->where('fltdate', $tomorrow->toDateString())->get();
        $hawb_before1 = $order()->where('fltdate', $yesterday->toDateString())->get();
        $hawb_before2 = $order()->where('fltdate', $yesterday->copy()->subDay()->toDateString())->get();

        $data = [
            'today' => $today->format('m.d'),
            'after1' => $tomorrow->format('m.d'),
            'before1' => $yesterday->format('m.d'),
            'before2' => $yesterday->copy()->subDay()->format('m.d'),
        ];

        return view(theme('track.track'), compact(
            'hawb_today',
            'hawb_after1',
            'hawb_before1',
            'hawb_before2',
            'title'
        ))->with($data);
    }

    /**
     * 航空公司货运追踪网址列表（原站只有一句占位文本）。
     */
    public function airline()
    {
        $title = '航空公司货运追踪网址';

        // 按航司名归并，同一家（如东航的 9 个代号）只列一行
        $carriers = [];
        foreach (self::FLIGHT as $prefix => $item) {
            [$name, $url, $site] = $item;
            $carriers[$name]['name'] ??= $name;
            // 列表页给出官网入口即可；带 {p3}/{p8} 的查询地址由追踪页自动拼总单号
            $carriers[$name]['site'] ??= $this->homepage($url ?? $site);
            $carriers[$name]['prefixes'][] = $prefix;
        }

        // 按总单代号排序，保证每次打开顺序一致（中文名直接 ksort 是字节序，没有意义）
        uasort($carriers, fn ($a, $b) => min($a['prefixes']) <=> min($b['prefixes']));

        return view(theme('track.airline'), compact('carriers', 'title'));
    }

    /**
     * 本系统内查询货物状态：总单 + 分单明细（原站是占位文本）。
     */
    public function status($mawb)
    {
        $title = '货物状态';
        $parsed = $this->parseMawb($mawb);

        if (! $parsed['ok']) {
            return $this->notice($title, $parsed['msg']);
        }

        $hawbs = Hawb::where('mawb', $mawb)->orderBy('hawb')->get();

        // 兼容库里存的是不带横线的总单号
        if ($hawbs->isEmpty()) {
            $hawbs = Hawb::where('mawb', $parsed['p3'].'-'.$parsed['p8'])->orderBy('hawb')->get();
        }

        $mawbRow = Mawb::where('mawb', $mawb)->first()
            ?? Mawb::where('mawb', $parsed['p3'].'-'.$parsed['p8'])->first();

        if ($hawbs->isEmpty() && $mawbRow === null) {
            return $this->notice(
                $title,
                '系统里没有总单 '.$parsed['full'].' 的记录。',
                '总单 '.$parsed['full'].' 在本系统内没有记录，可能是同行货或尚未录入；可改用航司官网查询。',
                route('track_airline'),
                '航空公司货运追踪网址'
            );
        }

        $sumNum = (int) $hawbs->sum('num');
        $sumGw = (int) $hawbs->sum('gw');
        $sumCbm = (float) $hawbs->sum('cbm');

        return view(theme('track.status'), compact(
            'hawbs',
            'mawbRow',
            'sumNum',
            'sumGw',
            'sumCbm',
            'title'
        ))->with(['mawbno' => $parsed['full']]);
    }

    /**
     * 运抵 / 放行查询。
     */
    public function arrival($mawb)
    {
        $title = '运抵信息';
        $parsed = $this->parseMawb($mawb);

        if (! $parsed['ok']) {
            return $this->notice($title, $parsed['msg']);
        }

        if (in_array($parsed['p3'], self::ARRIVAL_PACTL, true)) {
            // 浦东货站：直接把总单号带过去
            $url = 'http://www.pactl.com.cn/cn/WaybillTracking.aspx'
                .'?bill_no_1='.$parsed['p3'].'&bill_no_2='.$parsed['p8'].'&typeId=E';

            return redirect()->away($url);
        }

        if (in_array($parsed['p3'], self::ARRIVAL_CEAIR, true)) {
            // 东航物流只接受 POST，用隐藏表单自动提交（与原站做法一致）
            return view(theme('track.post'), [
                'title' => $title,
                'action' => 'http://www.eal-ceair.com/service/track.html',
                'fields' => [
                    'tabCode' => 'cargo_trace',
                    'awbNos' => $parsed['p3'].$parsed['p8'],
                    'verCode' => '1111',
                ],
                'mawbno' => $parsed['full'],
            ]);
        }

        return $this->notice(
            $title,
            '暂不支持查询 '.$parsed['p3'].' 的运抵信息。',
            '原系统只接入了浦东货站与东航物流两家的运抵查询，'.$parsed['p3'].' 不在其中。',
            route('track_airline'),
            '航空公司货运追踪网址'
        );
    }

    /**
     * 航班 / 货况查询。
     */
    public function flight($mawb)
    {
        $title = '航班信息';
        $parsed = $this->parseMawb($mawb);

        if (! $parsed['ok']) {
            return $this->notice($title, $parsed['msg']);
        }

        $carrier = self::FLIGHT[$parsed['p3']] ?? null;

        if ($carrier === null) {
            return $this->notice(
                $title,
                '暂不支持直接查询 '.$parsed['p3'].' 运单的航班信息。',
                '系统里只登记了常用承运人的查询入口，'.$parsed['p3'].' 还没有。',
                route('track_airline'),
                '航空公司货运追踪网址'
            );
        }

        [$name, $url, $site] = $carrier;

        if (! empty($url)) {
            return redirect()->away(str_replace(
                ['{p3}', '{p8}'],
                [$parsed['p3'], $parsed['p8']],
                $url
            ));
        }

        return $this->notice(
            $title,
            $name.' 需要到官网查询',
            '原系统对 '.$name.' 是服务端抓取网页再显示，该接口随航司官网改版已经不可用；'
                .'点击下面的按钮到官网输入总单号 '.$parsed['full'].' 查询。',
            $site,
            $name.' 官网查询'
        );
    }

    // ------------------------------------------------------------------
    // 辅助
    // ------------------------------------------------------------------

    /**
     * 校验总单号：去掉横线后必须是 11 位数字，且第 4-10 位对 7 取模等于末位。
     *
     * @return array{ok: bool, msg: string, full: string, p3: string, p8: string}
     */
    protected function parseMawb(string $mawb): array
    {
        $digits = str_replace(['-', ' '], '', trim($mawb));

        $p3 = substr($digits, 0, 3);
        $p8 = substr($digits, 3, 8);

        $result = [
            'ok' => false,
            'msg' => '',
            'full' => $p3 === '' ? $mawb : $p3.'-'.$p8,
            'p3' => $p3,
            'p8' => $p8,
        ];

        if (! preg_match('/^\d{11}$/', $digits)) {
            $result['msg'] = '总运单格式不正确！应为 3 位代号 + 8 位序号，共 11 位数字。';
            $result['full'] = $mawb;

            return $result;
        }

        // 校验位：第 4-10 位（7 位数）除以 7 的余数应等于第 11 位
        if ((int) substr($digits, 3, 7) % 7 !== (int) substr($digits, 10, 1)) {
            $result['msg'] = '总运单号 '.$p3.'-'.$p8.' 不符合校验规则（末位校验码不对），请核对后重试。';

            return $result;
        }

        $result['ok'] = true;
        $result['full'] = $p3.'-'.$p8;

        return $result;
    }

    /**
     * 取 URL 的站点根（带协议与端口），用于"官网入口"链接。
     */
    protected function homepage(string $url): string
    {
        $parts = parse_url($url);

        if ($parts === false || empty($parts['host'])) {
            return $url;
        }

        $scheme = $parts['scheme'] ?? 'http';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return $scheme.'://'.$parts['host'].$port.'/';
    }

    /**
     * 统一的结果提示页（替代原站的 echo 文本）。
     */
    protected function notice(
        string $title,
        string $message,
        string $detail = '',
        ?string $link = null,
        string $linkText = ''
    ): View {
        return view(theme('track.notice'), compact(
            'title',
            'message',
            'detail',
            'link',
            'linkText'
        ));
    }
}
