<?php

namespace App\Http\Controllers;

use App\Models\Hawb;
use App\Support\Xlsx;
use Illuminate\Http\Request;

/**
 * 分单清单：预览 + 导出 Excel。
 *
 * 对照原项目 app/Http/Controllers/ManifestController.php。
 *
 * 原实现用 maatwebsite/excel 2.1（PhpExcel）导出 .xls；该库已停止维护且
 * 本环境无法再装 Composer 包，改用项目内置的 App\Support\Xlsx 直接生成
 * 标准 .xlsx，版式（合并标题、列宽、行高、细边框、横向、按页高缩放）保持一致。
 *
 * 另：原 routes/web.php 的 GET manifest 指向 ManifestController@search，
 * 但控制器没有该方法，直接访问会 500。这里补上：带 mawb 参数就跳到清单页，
 * 否则回总单列表。
 */
class ManifestController extends Controller
{
    public function search(Request $request)
    {
        $mawb = trim((string) $request->query('mawb', ''));

        return $mawb !== ''
            ? redirect()->route('manifest', ['mawb' => $mawb])
            : redirect()->route('mawb_list');
    }

    public function show($mawb)
    {
        $title = '分单清单';

        $hawbs = Hawb::where('mawb', $mawb)->orderBy('hawb', 'asc')->get();

        // 该总单下没有分单，退回总单列表（原实现会因 $hawb_first 为空而报错）
        if ($hawbs->isEmpty()) {
            return redirect()->route('mawb_list');
        }

        $hawb_first = $hawbs->first();
        $hawb_count = $hawbs->count();
        $amount_num = $hawbs->sum('num');
        $amount_gw = $hawbs->sum('gw');

        return view(theme('manifest.manifest'), compact(
            'hawbs',
            'hawb_first',
            'hawb_count',
            'amount_num',
            'amount_gw',
            'title'
        ));
    }

    public function export($mawb)
    {
        $hawbs = Hawb::where('mawb', $mawb)->orderBy('hawb', 'asc')->get();

        if ($hawbs->isEmpty()) {
            return redirect()->route('mawb_list');
        }

        $first = $hawbs->first();

        $dest = (string) $first->dest;
        $count = $hawbs->count();
        $sumNum = (int) $hawbs->sum('num');
        $sumGw = (int) $hawbs->sum('gw');
        $prefix = env('CONF_HAWB_PREFIX');

        $xlsx = new Xlsx('清单');
        $xlsx->landscape()->margins(0.4)->fitToHeight(1);

        // 列宽与行高（原版设定）
        foreach (['A' => 12, 'B' => 9, 'C' => 9, 'D' => 23, 'E' => 9, 'F' => 9, 'G' => 24, 'H' => 22, 'I' => 12] as $col => $width) {
            $xlsx->width($col, $width);
        }
        foreach ([1 => 30, 2 => 20, 3 => 20, 4 => 15, 5 => 20] as $row => $height) {
            $xlsx->height($row, $height);
        }

        // 第 1 行：总标题（A1:I1 合并）
        $xlsx->merge('A1:I1');
        $xlsx->text('A1', 'HOUSE CARGO MANIFEST', Xlsx::TITLE);

        // 第 2 行：表头（A2:C2、E2:F2 合并）
        $xlsx->merge('A2:C2')->merge('E2:F2');
        $headers = [
            'A2' => 'Air Freight Agent',
            'D2' => 'Master AWB No.',
            'E2' => 'Port of Discharge',
            'G2' => 'Total No.of Shipment',
            'H2' => 'Flight No.',
            'I2' => 'Date',
        ];
        foreach (range('A', 'I') as $col) {
            $xlsx->text($col.'2', $headers[$col.'2'] ?? '', Xlsx::HEAD_CENTER);
        }

        // 第 3 行：总单信息（Arial 8、不加粗、上下居中，与原版一致）
        $xlsx->merge('A3:C3')->merge('E3:F3');
        $info = [
            'A3' => '',
            'D3' => (string) $first->mawb,
            'E3' => $dest,
            'G3' => $count,
            'H3' => (string) $first->fltno,
            'I3' => (string) $first->fltdate,
        ];
        foreach (range('A', 'I') as $col) {
            $ref = $col.'3';
            if (isset($info[$ref]) && is_int($info[$ref])) {
                $xlsx->number($ref, $info[$ref], Xlsx::DATA_CENTER);
            } else {
                $xlsx->text($ref, $info[$ref] ?? '', Xlsx::DATA_CENTER);
            }
        }

        // 第 5 行：分单列表表头
        foreach ([
            'A' => 'Hawb No.',
            'B' => 'Package',
            'C' => 'Weight',
            'D' => 'Nature of Goods',
            'E' => 'Departure',
            'F' => 'Destination',
            'G' => 'Name & Address of Shipper',
            'H' => 'Name & Address of Consignee',
            'I' => 'Offical Use',
        ] as $col => $label) {
            $xlsx->text($col.'5', $label, Xlsx::HEAD_LEFT);
        }

        // 第 6 行起：分单明细
        $row = 6;
        foreach ($hawbs as $hawb) {
            // 品名只取第一行；库里的换行是 CRLF，按通用换行切分（原实现 explode("\n")
            // 会残留一个 \r）
            $goods = preg_split('/\r\n|\n|\r/', (string) $hawb->cgodescp)[0] ?? '';
            $shipper = str_replace('\n', "\n", (string) $hawb->shipper);
            $consignee = str_replace('\n', "\n", (string) $hawb->consignee);

            $xlsx->text('A'.$row, $prefix.$hawb->hawb, Xlsx::DATA_LEFT);
            $xlsx->number('B'.$row, (int) $hawb->num, Xlsx::DATA_LEFT);
            $xlsx->number('C'.$row, (float) $hawb->gw, Xlsx::DATA_LEFT);
            $xlsx->text('D'.$row, $goods, Xlsx::DATA_LEFT);
            $xlsx->text('E'.$row, 'SHA', Xlsx::DATA_LEFT);
            $xlsx->text('F'.$row, (string) $hawb->dest, Xlsx::DATA_LEFT);
            $xlsx->text('G'.$row, $shipper, Xlsx::DATA_WRAP_CENTER);
            $xlsx->text('H'.$row, $consignee, Xlsx::DATA_WRAP_CENTER);
            $xlsx->text('I'.$row, '', Xlsx::DATA_LEFT);

            $row++;
        }

        // 合计行（空一行再写）
        $row++;
        $xlsx->text('A'.$row, 'TOTAL:', Xlsx::FOOT_LEFT);
        $xlsx->number('B'.$row, $sumNum, Xlsx::FOOT_LEFT);
        $xlsx->number('C'.$row, $sumGw, Xlsx::FOOT_LEFT);

        return $xlsx->download('manifest-'.$mawb.'-'.$dest.'.xlsx');
    }
}
