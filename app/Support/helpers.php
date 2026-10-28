<?php

/**
 * 全局辅助函数（对照原项目 app/function.php 迁移）
 */

if (! function_exists('theme')) {
    /**
     * 给视图路径加上模板目录前缀，用于切换主题。
     * 原实现依赖 env('APP_TM')，这里改走 config，避免 config:cache 后 env() 失效。
     */
    function theme(string $view): string
    {
        return config('app.tm', 'default').'.'.$view;
    }
}
