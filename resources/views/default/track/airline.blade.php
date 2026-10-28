@extends(theme('layout.layout'))

@section('container')
<div class="container theme-showcase" role="main">
    <div class="row">
        <div class="col-md-12">
            <h4 style="text-align:center">{{ $title }}</h4>
            <p class="text-center text-muted">
                查询总单号时保持「3 位代号 + 8 位序号」格式，例如 999-12345678。
            </p>

            <table class="table table-striped table-hover table-bordered table-condensed" style="width:80%;margin:0 auto">
                <thead>
                    <tr>
                        <th width="22%">承运人</th>
                        <th width="20%">总单代号</th>
                        <th>货运追踪网址</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($carriers as $carrier)
                    <tr>
                        <td>{{ $carrier['name'] }}</td>
                        <td>{{ implode(' / ', $carrier['prefixes']) }}</td>
                        <td>
                            <a href="{{ $carrier['site'] }}" target="_blank" rel="noopener">{{ $carrier['site'] }}</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            <div style="text-align:center;margin-top:12px">
                <a href="{{ route('track_index') }}" class="btn btn-default btn-sm">返回货物追踪</a>
            </div>
        </div>
    </div>
</div>
@endsection
