@extends(theme('layout.layout'))

@section('container')
<div class="container" style="padding-top:70px">

    <div class="row">
        <div class="col-md-12">
            <h3>系统概览</h3>
            <p class="text-muted">
                当前运行：Laravel {{ app()->version() }} · PHP {{ PHP_VERSION }} ·
                数据库 {{ config('database.connections.mysql.database') }}
            </p>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="panel panel-primary">
                <div class="panel-heading">分单 HAWB</div>
                <div class="panel-body">
                    <h2 style="margin:0">{{ number_format($stats['hawb']) }}</h2>
                    <small class="text-muted">hawbs 表记录数</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="panel panel-success">
                <div class="panel-heading">总单 MAWB</div>
                <div class="panel-body">
                    <h2 style="margin:0">{{ number_format($stats['mawb']) }}</h2>
                    <small class="text-muted">mawbs 表记录数</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="panel panel-info">
                <div class="panel-heading">进仓记录</div>
                <div class="panel-body">
                    <h2 style="margin:0">{{ number_format($stats['jincang']) }}</h2>
                    <small class="text-muted">jincang 表记录数</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row" style="margin-top:20px">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading">重构进度</div>
                <div class="panel-body">
                    <p>已完成：登录 / 退出 / 首页仪表盘 / 数据模型 / 主题与 Form 兼容层</p>
                    <p>进行中：进仓、分单、总单、清单、打印、报表、数据维护、货物追踪</p>
                    <p class="text-muted" style="margin-bottom:0">
                        未完成的模块点击导航会进入"重构中"占位页，不影响对照原站功能。
                    </p>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
