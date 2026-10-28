<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <link href="{{ asset('css/track-flight.css') }}" rel="stylesheet">
</head>
<body>
<p style="font-family:Arial,sans-serif;padding:12px">
    正在跳转到东航物流查询总单 {{ $mawbno }} …
</p>

{{-- 东航物流的查询页只接受 POST，这里用隐藏表单自动提交（与原站做法一致） --}}
<form id="trackForm" method="post" action="{{ $action }}" style="display:none">
    @foreach($fields as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach
</form>

<p style="font-family:Arial,sans-serif;padding:12px">
    如果没有自动跳转，请点击
    <a href="{{ $action }}" target="_blank">东航物流查询页</a> 手动查询，
    或返回<a href="{{ route('track_index') }}">货物追踪</a>。
</p>

<script>document.getElementById('trackForm').submit();</script>
</body>
</html>
