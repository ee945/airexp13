@extends(theme('layout.layout'))

@section('container')
<div class="container theme-showcase" role="main">
    <div class="row">
        <div class="col-md-12">
            <h4 style="text-align:center">总单 {{ $mawbno }} 在本系统内的状态</h4>

            <table class="table table-bordered table-condensed" style="width:90%;margin:0 auto 10px auto">
                <tr>
                    <th width="12%">总单号</th>
                    <td width="21%">{{ $mawbno }}</td>
                    <th width="12%">目的港</th>
                    <td width="21%">{{ $mawbRow->dest ?? ($hawbs->first()->dest ?? '') }}</td>
                    <th width="12%">承运人</th>
                    <td>{{ $mawbRow->carrier ?? ($hawbs->first()->carriername ?? '') }}</td>
                </tr>
                <tr>
                    <th>航班号</th>
                    <td>{{ $mawbRow->fltno ?? ($hawbs->first()->fltno ?? '') }}</td>
                    <th>航班日期</th>
                    <td>{{ $mawbRow->fltdate ?? ($hawbs->first()->fltdate ?? '') }}</td>
                    <th>制单日期</th>
                    <td>{{ $mawbRow->opdate ?? ($hawbs->first()->opdate ?? '') }}</td>
                </tr>
                <tr>
                    <th>分单票数</th>
                    <td>{{ $hawbs->count() }}</td>
                    <th>总件数</th>
                    <td>{{ $sumNum }}</td>
                    <th>总重量 / 体积</th>
                    <td>{{ $sumGw }} K / {{ rtrim(rtrim(number_format($sumCbm, 3, '.', ''), '0'), '.') }} CBM</td>
                </tr>
            </table>

            <table class="table table-striped table-hover table-bordered table-condensed" style="width:90%;margin:0 auto">
                <thead>
                    <tr>
                        <th width="14%">分运单号</th>
                        <th width="10%">目的港</th>
                        <th width="12%">航班号</th>
                        <th width="12%">航班日期</th>
                        <th width="8%">件数</th>
                        <th width="8%">重量</th>
                        <th width="10%">付费方式</th>
                        <th>品名</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($hawbs as $hawb)
                    <tr>
                        <td><a href="{{ route('hawb_view', ['hawb' => $hawb->hawb]) }}">{{ $hawb->hawb }}</a></td>
                        <td>{{ $hawb->dest }}</td>
                        <td>{{ $hawb->fltno }}</td>
                        <td>{{ $hawb->fltdate }}</td>
                        <td>{{ $hawb->num }}</td>
                        <td>{{ $hawb->gw }}</td>
                        <td>{{ $hawb->paymt }}</td>
                        <td>{{ \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', (string) $hawb->cgodescp), 40) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted">总单已录入，但还没有分单</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>

            <div style="text-align:center;margin-top:12px">
                <a href="{{ route('manifest', ['mawb' => $mawbno]) }}" class="btn btn-default btn-sm">查看清单</a>
                <a href="{{ route('track_arrival', ['mawb' => $mawbno]) }}" target="_blank" class="btn btn-success btn-sm">运抵查询</a>
                <a href="{{ route('track_flight', ['mawb' => $mawbno]) }}" target="_blank" class="btn btn-primary btn-sm">航班查询</a>
                <a href="{{ route('track_index') }}" class="btn btn-default btn-sm">返回货物追踪</a>
            </div>
        </div>
    </div>
</div>
@endsection
