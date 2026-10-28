@extends(theme('layout.layout'))

@section('container')
<div class="container" style="padding-top:70px">
    <div class="row">
        <div class="col-md-8 col-md-offset-2">
            <div class="panel panel-warning">
                <div class="panel-heading">{{ $module }} — 模块重构中</div>
                <div class="panel-body">
                    <p>该模块尚未迁移到 Laravel 13 版本。</p>
                    <p class="text-muted">
                        数据仍保存在同一个 MySQL 库中，可随时对照原站（旧 Laravel 5.4 版本）
                        @if (config('app.legacy_url'))
                            <a href="{{ config('app.legacy_url') }}" target="_blank">{{ config('app.legacy_url') }}</a>
                        @endif
                        验证功能一致性。
                    </p>
                    <a href="{{ route('home') }}" class="btn btn-default btn-sm">返回首页</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
