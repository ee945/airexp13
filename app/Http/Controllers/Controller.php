<?php

namespace App\Http\Controllers;

/**
 * 控制器基类。
 *
 * 原项目每个控制器构造里写 $this->middleware('auth')，Laravel 11 起
 * 控制器基类不再提供 middleware() 方法；本项目的权限统一由 routes/web.php
 * 的 auth 中间件组控制，因此这里只保留跨模块复用的辅助方法。
 */
abstract class Controller
{
    /**
     * 复刻原项目 "已存在" 的提示：弹窗后返回上一页。
     */
    protected function alertBack(string $message)
    {
        return response(
            "<script>alert('".e($message)."');history.back(-1)</script>"
        )->header('Content-Type', 'text/html; charset=utf-8');
    }
}
