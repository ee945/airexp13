@extends(theme('layout.layout'))

@section('container')
<div class="container theme-showcase" role="main">
    <div class="row">
        <div class="col-md-8 col-md-offset-2">
            <div class="panel panel-warning">
                <div class="panel-heading">{{ $title }}</div>
                <div class="panel-body">
                    <p style="font-size:1.1em">{{ $message }}</p>
                    @if($detail)
                        <p class="text-muted">{{ $detail }}</p>
                    @endif
                    <hr>
                    @if($link)
                        <a href="{{ $link }}" target="_blank" class="btn btn-primary btn-sm">{{ $linkText ?: '打开链接' }}</a>
                    @endif
                    <a href="{{ route('track_index') }}" class="btn btn-default btn-sm">返回货物追踪</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
