<?php

namespace App\Http\Controllers;

use App\Models\Hawb;
use App\Models\Mawb;

/**
 * 单证打印：分单（新旧两版）/ 总单。
 *
 * 对照原项目 app/Http/Controllers/PrintController.php。
 *
 * 打印页是绝对定位的套打模板，控制器只负责：取出记录、算出每个字段的
 * 定位像素（毫米 × 比例），再交给视图。这些坐标来自实物运单的测量值，
 * 一律原样保留，不做"优化"，否则套打位置会整体偏移。
 *
 * 唯一的行为调整：日期为零值（0000-00-00 / 空）时输出空串，而不是
 * 让 strtotime 返回 false 后打印出 1970 的日期。
 */
class PrintController extends Controller
{
    public function printHawb($hawbno)
    {
        $title = '打印分单';

        $hawb = Hawb::where('hawb', $hawbno)->first();

        if ($hawb === null) {
            return redirect()->route('hawb_list');
        }

        $data = $this->hawbExtra($hawb);

        /* 打印位置：基本常数 */
        $showratex = 3.65;
        $showratey = 3.7;
        $hleft = -20;
        $htop = -15;

        $x = [
            'issued' => 150, 'mawb' => 55, 'hawb' => 198, 'hawbfoot' => 198,
            'shipper' => 40, 'consignee' => 40, 'notify' => 130, 'pay' => 130,
            'depar' => 40, 'dest' => 36, 'carrier' => 45, 'desti' => 40,
            'flt' => 85, 'curr' => 127, 'paymt' => 144, 'nvd' => 166,
            'special' => 40, 'case' => 36, 'num' => 36, 'gw' => 50, 'kg' => 63,
            'rclass' => 75, 'cw' => 95, 'cgodescp' => 169, 'cbm' => 210,
            'asarrangedc' => 120, 'prtdate' => 115, 'atplace' => 155, 'agent' => 155,
        ];

        $y = [
            'issued' => 45, 'mawb' => 26, 'hawb' => 26, 'hawbfoot' => 292,
            'shipper' => 38, 'consignee' => 62, 'notify' => 90, 'pay' => 86,
            'depar' => 114, 'dest' => 122, 'carrier' => 122, 'desti' => 131,
            'flt' => 131, 'curr' => 122, 'paymt' => 122, 'nvd' => 122,
            'special' => 142, 'case' => 163, 'num' => 170, 'gw' => 170, 'kg' => 170,
            'rclass' => 170, 'cw' => 170, 'cgodescp' => 163, 'cbm' => 215,
            'asarrangeda' => 225, 'asarrangedb' => 276, 'asarrangedc' => 225,
            'prtdate' => 282, 'atplace' => 282, 'agent' => 263,
        ];

        foreach ($x as $key => $mm) {
            $data[$key.'_x'] = ($mm + $hleft) * $showratex;
        }
        foreach ($y as $key => $mm) {
            $data[$key.'_y'] = ($mm + $htop) * $showratey;
        }

        // 运费"另议"标记的横向位置随付费方式变化
        if (in_array($hawb->paymt, ['PP', 'PC'], true)) {
            $data['asarrangeda_x'] = (40 + $hleft) * $showratex;
            $data['asarrangedb_x'] = (40 + $hleft) * $showratex;
        } else {
            $data['asarrangeda_x'] = (75 + $hleft) * $showratex;
            $data['asarrangedb_x'] = (75 + $hleft) * $showratex;
        }

        return view(theme('print.hawb'), compact('hawb', 'title'))->with($data);
    }

    public function printHawb_old($hawbno)
    {
        $title = '打印分单';

        $hawb = Hawb::where('hawb', $hawbno)->first();

        if ($hawb === null) {
            return redirect()->route('hawb_list');
        }

        $data = $this->hawbExtra($hawb);

        /* 打印位置：基本常数 */
        $showrate = 3.65;
        $hleft = 0;
        $htop = 4;

        $x = [
            'mawb' => 25, 'hawb' => 170, 'hawbfoot' => 170, 'shipper' => 15,
            'consignee' => 15, 'notify' => 15, 'pay' => 120, 'depar' => 15,
            'dest' => 10, 'carrier' => 20, 'desti' => 15, 'flt' => 58,
            'curr' => 110, 'paymt' => 124, 'nvd' => 150, 'special' => 20,
            'case' => 10, 'num' => 10, 'gw' => 20, 'kg' => 36, 'rclass' => 44,
            'cw' => 72, 'cgodescp' => 113, 'cbm' => 185, 'asarrangedc' => 100,
            'prtdate' => 98, 'atplace' => 135, 'agent' => 135,
        ];

        $y = [
            'mawb' => 8, 'hawb' => 8, 'hawbfoot' => 275, 'shipper' => 19,
            'consignee' => 50, 'notify' => 74, 'pay' => 80, 'depar' => 100,
            'dest' => 108, 'carrier' => 108, 'desti' => 117, 'flt' => 117,
            'curr' => 108, 'paymt' => 108, 'nvd' => 108, 'special' => 130,
            'case' => 150, 'num' => 155, 'gw' => 155, 'kg' => 155, 'rclass' => 155,
            'cw' => 155, 'cgodescp' => 152, 'cbm' => 201, 'asarrangeda' => 216,
            'asarrangedb' => 258, 'asarrangedc' => 216, 'prtdate' => 260,
            'atplace' => 260, 'agent' => 245,
        ];

        foreach ($x as $key => $mm) {
            $data[$key.'_x'] = ($mm + $hleft) * $showrate;
        }
        foreach ($y as $key => $mm) {
            $data[$key.'_y'] = ($mm + $htop) * $showrate;
        }

        if (in_array($hawb->paymt, ['PP', 'PC'], true)) {
            $data['asarrangeda_x'] = (15 + $hleft) * $showrate;
            $data['asarrangedb_x'] = (15 + $hleft) * $showrate;
        } else {
            $data['asarrangeda_x'] = (46 + $hleft) * $showrate;
            $data['asarrangedb_x'] = (46 + $hleft) * $showrate;
        }

        return view(theme('print.hawb_old'), compact('hawb', 'title'))->with($data);
    }

    public function printMawb($mawbno)
    {
        $title = '打印总单';

        $mawb = Mawb::where('mawb', $mawbno)->first();

        if ($mawb === null) {
            return redirect()->route('mawb_list');
        }

        $data = [
            'mawb_topleft' => substr((string) $mawb->mawb, 0, 3),
            'mawb_topright' => substr((string) $mawb->mawb, -8),
            'curr' => 'CNY',
            'nvd' => 'NVD.',
            'pay' => 'FREIGHT PREPAID',
            'kg' => 'K.',
            'cbm' => round((float) $mawb->cbm, 3).'&nbsp;CBM',
            'flt' => $mawb->fltno.' / '.$this->upperDate($mawb->fltdate, 'd,M'),
            'opdate' => $this->upperDate($mawb->opdate, 'M. d, Y'),
            'aw' => $mawb->awn.' : '.$mawb->aw,
            'my' => $mawb->myn.' : '.$mawb->my,
            'sc' => $mawb->scn.' : '.$mawb->sc,
        ];

        /* 打印位置：基本常数 */
        $showrate = 3.6;
        $mleft = 12;
        $mtop = 1;

        $x = [
            'mawb' => 150, 'mawb_topleft' => 21, 'mawb_topright' => 41,
            'mawb_bottom' => 150, 'dest' => 21, 'desti' => 23, 'depa' => 31,
            'depar' => 50, 'shipper' => 25, 'consignee' => 25, 'agentabbr' => 25,
            'agentcode' => 25, 'agentaccount' => 70, 'carrier' => 35, 'flt' => 65,
            'special' => 30, 'package' => 21, 'num' => 22, 'numb' => 22,
            'gw' => 32, 'gwb' => 32, 'cw' => 82, 'cbm' => 165, 'rclass' => 51,
            'up' => 100, 'freight' => 125, 'freightb' => 30, 'aw' => 95,
            'my' => 120, 'sc' => 155, 'other' => 30, 'amount' => 30,
            'cgodescp' => 150, 'signature' => 130, 'atplace' => 135,
            'operator' => 175, 'opdate' => 95, 'pay' => 130, 'kg' => 44,
            'curr' => 111, 'wpp' => 125, 'opp' => 135, 'nvd' => 155, 'scs' => 130,
        ];

        $y = [
            'mawb' => 11, 'mawb_topleft' => 11, 'mawb_topright' => 11,
            'mawb_bottom' => 273, 'dest' => 105, 'desti' => 114, 'depa' => 12,
            'depar' => 97, 'shipper' => 21, 'consignee' => 46, 'agentabbr' => 75,
            'agentcode' => 88, 'agentaccount' => 88, 'carrier' => 105, 'flt' => 114,
            'special' => 125, 'package' => 145, 'num' => 150, 'numb' => 195,
            'gw' => 150, 'gwb' => 195, 'cw' => 150, 'cbm' => 195, 'rclass' => 150,
            'up' => 150, 'freight' => 150, 'freightb' => 206, 'aw' => 210,
            'my' => 210, 'sc' => 210, 'other' => 239, 'amount' => 255,
            'cgodescp' => 150, 'signature' => 241, 'atplace' => 258,
            'operator' => 258, 'opdate' => 258, 'pay' => 80, 'kg' => 150,
            'curr' => 105, 'wpp' => 105, 'opp' => 105, 'nvd' => 105, 'scs' => 85,
        ];

        foreach ($x as $key => $mm) {
            $data[$key.'_x'] = ($mm + $mleft) * $showrate;
        }
        foreach ($y as $key => $mm) {
            $data[$key.'_y'] = ($mm + $mtop) * $showrate;
        }

        return view(theme('print.mawb'), compact('mawb', 'title'))->with($data);
    }

    // ------------------------------------------------------------------

    /**
     * 分单打印的派生字段（新旧两版共用）。
     */
    protected function hawbExtra(Hawb $hawb): array
    {
        $data = [
            'carreier' => substr((string) $hawb->fltno, 0, 2),
            'flt' => $hawb->fltno.' / '.$this->upperDate($hawb->fltdate, 'd,M'),
            'prtdate' => $this->upperDate($hawb->opdate, 'M. d, Y'),
            'cbm' => round((float) $hawb->cbm, 3).' CBM',
        ];

        // 付费方式：CP/CC 为运费到付，其余为预付
        if ($hawb->paymt === 'CP') {
            $data['paymtli'] = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;C&nbsp;&nbsp;&nbsp;&nbsp;P';
            $data['pay'] = 'FREIGHT COLLECT';
        } elseif ($hawb->paymt === 'CC') {
            $data['paymtli'] = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;C&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;C';
            $data['pay'] = 'FREIGHT COLLECT';
        } else {
            $data['paymtli'] = '&nbsp;P&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;P';
            $data['pay'] = 'FREIGHT PREPAID';
        }

        // 运费另议
        if ((int) $hawb->arranged === 0) {
            $data['asarranged'] = ' AS ARRANGED';
        }

        return $data;
    }

    /**
     * 格式化日期并转大写；零日期返回空串，避免打印出 1970 年。
     */
    protected function upperDate(?string $value, string $format): string
    {
        if ($value === null || $value === '' || str_starts_with($value, '0000-00-00')) {
            return '';
        }

        $time = strtotime($value);

        return $time === false ? '' : strtoupper(date($format, $time));
    }
}
