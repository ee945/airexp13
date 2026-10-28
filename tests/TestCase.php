<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * 把测试切到 .env 配置的真实 MySQL 业务库（与原站同一个库）。
     *
     * 本项目所有页面都依赖真实业务表与存量数据，无法用 sqlite :memory: 跑，
     * 所以 phpunit.xml 不再改写 DB_CONNECTION；host / port / database /
     * username / password 一律来自 .env，由 config/database.php 在启动时读取，
     * **不写死在测试代码里**。
     *
     * 未配置业务库时直接跳过，而不是报一串连接失败。
     */
    protected function useBusinessDatabase(): void
    {
        if (blank(config('database.connections.mysql.database'))) {
            $this->markTestSkipped('未配置 MySQL 业务库（.env 缺少 DB_DATABASE），跳过依赖真实数据的测试。');
        }

        config(['database.default' => 'mysql']);
        DB::purge('mysql');
    }
}
