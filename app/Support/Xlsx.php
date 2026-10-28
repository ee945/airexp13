<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * 极简 XLSX 写出器（零第三方依赖，仅用 PHP 自带 zip 扩展）。
 *
 * 原项目用 maatwebsite/excel 2.1（PhpExcel）导出清单。该库已被 3.x 重写、
 * 且当前环境不允许再装 Composer 包，因此这里直接按 OOXML 规范生成 xlsx：
 * 支持内联字符串、数字、合并单元格、列宽、行高、细边框、字体与横向/按页缩放。
 *
 * 用法：
 *   $x = new Xlsx('清单');
 *   $x->title('A1:I1', 'HOUSE CARGO MANIFEST');
 *   $x->text('A2', 'Air Freight Agent', Xlsx::HEAD_CENTER);
 *   $x->number('B6', 12, Xlsx::DATA_LEFT);
 *   return $x->download('manifest-999-12345678-LAX.xlsx');
 */
class Xlsx
{
    // ---- 预置单元格样式（对应 styles.xml 的 cellXfs 顺序） ----
    public const NORMAL = 0;        // 默认
    public const TITLE = 1;         // 16pt 粗体，居中（总标题）
    public const HEAD_CENTER = 2;   // 10pt 粗体，居中 + 细边框
    public const HEAD_LEFT = 3;     // 10pt 粗体，左对齐 + 细边框
    public const DATA_LEFT = 4;     // 8pt，左对齐 + 细边框
    public const DATA_CENTER = 5;   // 8pt，居中 + 细边框
    public const DATA_WRAP = 6;     // 8pt，左对齐、自动换行、顶端对齐 + 细边框
    public const FOOT_LEFT = 7;     // 8pt，左对齐，无边框（合计行，与旧版 PhpExcel 一致）
    public const DATA_WRAP_CENTER = 8; // 8pt，左对齐、自动换行、上下居中 + 细边框

    protected string $sheetName;

    /** @var array<string, array{v: string, n: bool, s: int}> */
    protected array $cells = [];

    /** @var string[] */
    protected array $merges = [];

    /** @var array<int, float> 列序号 => 宽度（字符） */
    protected array $colWidths = [];

    /** @var array<int, float> 行号 => 高度（磅） */
    protected array $rowHeights = [];

    protected bool $landscape = false;

    protected float $margin = 0.4;

    protected ?int $fitToHeight = null;

    public function __construct(string $sheetName = 'Sheet1')
    {
        $this->sheetName = $sheetName;
    }

    // ------------------------------------------------------------------
    // 写入内容
    // ------------------------------------------------------------------

    public function text(string $coord, mixed $value, int $style = self::NORMAL): static
    {
        $this->cells[strtoupper($coord)] = [
            'v' => (string) $value,
            'n' => false,
            's' => $style,
        ];

        return $this;
    }

    public function number(string $coord, int|float|string|null $value, int $style = self::NORMAL): static
    {
        if ($value === null || $value === '') {
            return $this->text($coord, '', $style);
        }

        $this->cells[strtoupper($coord)] = [
            'v' => (string) $value,
            'n' => true,
            's' => $style,
        ];

        return $this;
    }

    /**
     * 整行写入：$values 按列顺序从 $startColumn 开始填。
     */
    public function row(int $rowNumber, array $values, int $style = self::NORMAL, string $startColumn = 'A'): static
    {
        $column = $this->columnIndex($startColumn);
        foreach ($values as $value) {
            $coord = $this->columnLetter($column).$rowNumber;
            if (is_int($value) || is_float($value)) {
                $this->number($coord, $value, $style);
            } else {
                $this->text($coord, $value, $style);
            }
            $column++;
        }

        return $this;
    }

    public function merge(string $range): static
    {
        $this->merges[] = strtoupper($range);

        return $this;
    }

    public function width(string $column, float $width): static
    {
        $this->colWidths[$this->columnIndex($column)] = $width;

        return $this;
    }

    public function height(int $rowNumber, float $height): static
    {
        $this->rowHeights[$rowNumber] = $height;

        return $this;
    }

    public function landscape(bool $on = true): static
    {
        $this->landscape = $on;

        return $this;
    }

    public function margins(float $all): static
    {
        $this->margin = $all;

        return $this;
    }

    /** 按页高缩放：1 表示所有行压缩到一页 */
    public function fitToHeight(int $pages): static
    {
        $this->fitToHeight = $pages;

        return $this;
    }

    // ------------------------------------------------------------------
    // 输出
    // ------------------------------------------------------------------

    /**
     * 生成 xlsx 并作为下载响应返回。
     */
    public function download(string $filename)
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $this->save($path);

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function save(string $path): void
    {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('无法创建 xlsx 文件: '.$path);
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->rootRelsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
        $zip->addFromString('xl/styles.xml', $this->stylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheetXml());

        $zip->close();
    }

    // ------------------------------------------------------------------
    // XML 片段
    // ------------------------------------------------------------------

    protected function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    protected function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    protected function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            .' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.$this->escape($this->sheetName).'" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    protected function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    protected function stylesXml(): string
    {
        $fonts = [
            '<font><sz val="11"/><name val="宋体"/><charset val="134"/></font>',
            '<font><b/><sz val="16"/><name val="Times New Roman"/></font>',
            '<font><b/><sz val="10"/><name val="Times New Roman"/></font>',
            '<font><sz val="8"/><name val="Arial"/></font>',
        ];

        // 对齐：1=左 2=中 3=右；垂直：1=上 2=中 3=下
        $aligns = [
            '',                                                                 // 0 默认
            '<alignment horizontal="center" vertical="center"/>',               // 1
            '<alignment horizontal="center" vertical="center"/>',               // 2
            '<alignment horizontal="left" vertical="center"/>',                 // 3
            '<alignment horizontal="left" vertical="center"/>',                 // 4
            '<alignment horizontal="center" vertical="center"/>',               // 5
            '<alignment horizontal="left" vertical="top" wrapText="1"/>',       // 6
            '<alignment horizontal="left" vertical="center"/>',                 // 7
            '<alignment horizontal="left" vertical="center" wrapText="1"/>',    // 8
        ];

        $xfs = [
            // 默认
            '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>',
            // TITLE：16pt 粗体居中，无边框
            '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1">'.$aligns[1].'</xf>',
            // HEAD_CENTER
            '<xf numFmtId="0" fontId="2" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1">'.$aligns[2].'</xf>',
            // HEAD_LEFT
            '<xf numFmtId="0" fontId="2" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1">'.$aligns[3].'</xf>',
            // DATA_LEFT
            '<xf numFmtId="0" fontId="3" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1">'.$aligns[4].'</xf>',
            // DATA_CENTER
            '<xf numFmtId="0" fontId="3" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1">'.$aligns[5].'</xf>',
            // DATA_WRAP
            '<xf numFmtId="0" fontId="3" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1">'.$aligns[6].'</xf>',
            // FOOT_LEFT：合计行，Arial 8 不加粗（旧版由 A6:I100 统一样式带来）
            '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1">'.$aligns[7].'</xf>',
            // DATA_WRAP_CENTER
            '<xf numFmtId="0" fontId="3" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1">'.$aligns[8].'</xf>',
        ];

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="'.count($fonts).'">'.implode('', $fonts).'</fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            .'<borders count="2">'
            .'<border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border><left style="thin"><color auto="1"/></left><right style="thin"><color auto="1"/></right>'
            .'<top style="thin"><color auto="1"/></top><bottom style="thin"><color auto="1"/></bottom><diagonal/></border>'
            .'</borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="'.count($xfs).'">'.implode('', $xfs).'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }

    protected function sheetXml(): string
    {
        $rows = [];
        $rowNumbers = array_unique(array_merge(
            array_map(fn ($coord) => $this->rowIndex($coord), array_keys($this->cells)),
            array_keys($this->rowHeights)
        ));
        sort($rowNumbers);

        foreach ($rowNumbers as $rowNumber) {
            $rowCells = [];
            foreach ($this->cells as $coord => $cell) {
                if ($this->rowIndex($coord) !== $rowNumber) {
                    continue;
                }

                $rowCells[$coord] = $cell;
            }
            // 按列排序，保证 XML 顺序合法
            uksort($rowCells, fn ($a, $b) => $this->columnIndex($this->columnOf($a)) <=> $this->columnIndex($this->columnOf($b)));

            $height = isset($this->rowHeights[$rowNumber])
                ? ' ht="'.$this->rowHeights[$rowNumber].'" customHeight="1"'
                : '';

            $xml = '<row r="'.$rowNumber.'"'.$height.'>';
            foreach ($rowCells as $coord => $cell) {
                $ref = $this->escape($coord);
                if ($cell['n']) {
                    $xml .= '<c r="'.$ref.'" s="'.$cell['s'].'"><v>'.$this->escape($cell['v']).'</v></c>';
                } else {
                    $xml .= '<c r="'.$ref.'" s="'.$cell['s'].'" t="inlineStr"><is><t xml:space="preserve">'
                        .$this->escape($cell['v']).'</t></is></c>';
                }
            }
            $xml .= '</row>';
            $rows[] = $xml;
        }

        $cols = '';
        if ($this->colWidths !== []) {
            ksort($this->colWidths);
            $cols = '<cols>';
            foreach ($this->colWidths as $index => $width) {
                $cols .= '<col min="'.$index.'" max="'.$index.'" width="'.$width.'" customWidth="1"/>';
            }
            $cols .= '</cols>';
        }

        $merges = '';
        if ($this->merges !== []) {
            $merges = '<mergeCells count="'.count($this->merges).'">';
            foreach ($this->merges as $range) {
                $merges .= '<mergeCell ref="'.$this->escape($range).'"/>';
            }
            $merges .= '</mergeCells>';
        }

        $pageSetup = '<pageSetup paperSize="9" orientation="'.($this->landscape ? 'landscape' : 'portrait').'"'
            .($this->fitToHeight !== null ? ' fitToHeight="'.$this->fitToHeight.'"' : '')
            .'/>';

        $sheetPr = $this->fitToHeight !== null
            ? '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
            : '';

        $m = $this->margin;

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .$sheetPr
            .'<sheetViews><sheetView workbookViewId="0"/></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="14.25"/>'
            .$cols
            .'<sheetData>'.implode('', $rows).'</sheetData>'
            .$merges
            .'<pageMargins left="'.$m.'" right="'.$m.'" top="'.$m.'" bottom="'.$m.'" header="0.3" footer="0.3"/>'
            .$pageSetup
            .'</worksheet>';
    }

    // ------------------------------------------------------------------
    // 辅助
    // ------------------------------------------------------------------

    protected function escape(string $value): string
    {
        // 去掉 XML 非法控制字符，避免 Excel 打开时报文件损坏
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value);

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    protected function rowIndex(string $coord): int
    {
        return (int) preg_replace('/[^0-9]/', '', $coord);
    }

    protected function columnOf(string $coord): string
    {
        return preg_replace('/[^A-Z]/', '', strtoupper($coord));
    }

    protected function columnIndex(string $column): int
    {
        $column = strtoupper($column);
        $index = 0;
        foreach (str_split($column) as $char) {
            $index = $index * 26 + (ord($char) - 64);
        }

        return $index;
    }

    protected function columnLetter(int $index): string
    {
        $letters = '';
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letters = chr(65 + $mod).$letters;
            $index = intdiv($index - 1, 26);
        }

        return $letters;
    }
}
