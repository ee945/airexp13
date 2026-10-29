# airexp13 · 出口单证系统（Laravel 13 重构版）

国际货运代理的出口单证管理系统。原系统基于 **Laravel 5.4 + PHP 7.2**，因跨 8 个大版本、
Symfony 3.2 → 7，且两个核心依赖（`laravelcollective/html`、`maatwebsite/excel`）已废弃，
无法原地升级，故重建工程骨架、**沿用原 MySQL 库与业务表、复用原 Blade 视图**，
以最小侵入方式完成技术栈迁移。

> 原站（Laravel 5.4，PHP 7.2）作为对照基准保留，本仓库是正式演进方向。

## 技术栈

| 层 | 选型 |
|---|---|
| 框架 | Laravel 13.39 |
| 运行时 | PHP 8.5（FPM） |
| 数据库 | MySQL 8.4，库名 `yh`（与原站共用） |
| 前端 | Bootstrap 3 + jQuery（沿用原站静态资源，无构建步骤） |
| 测试 | PHPUnit 12，**102 用例 / 486 断言** |
| 导出 | 自研 `App\Support\Xlsx`（零依赖，仅需 `zip` 扩展） |

规模：99 条路由、15 个控制器、37 个视图、9 张业务表
（分单 12,773 / 进仓 3,816 / 总单 2,076 …）。

## 功能模块

| 模块 | 路由前缀 | 说明 |
|---|---|---|
| 数据维护 | `address` `port` `client` `seller` `contact` | 地址 / 港口 / 客户 / 发货人 / 联系人 |
| 进仓 | `jincang` | 列表、登记、修改、删除、出仓、回仓、航班日期批量更新 |
| 分单 | `hawb` | 列表、增改删、新旧两套打印、打印页保存、模七校验 |
| 总单 | `mawb` | 列表、打印、打印页保存、删除 |
| 清单 | `manifest` | 按总单汇总、XLSX 导出（分页打印友好） |
| 单证打印 | `print` | 分单 / 旧版分单 / 总单三套坐标式打印 |
| 货量统计 | `stats/hawb` | 按航班日期区间 / 星期 / 目的港统计件数、重量、体积（`stats` 首页与 `stats/mawb` 已取消） |
| 货物追踪 | `track` | 运抵（浦东货站 / 东航物流）、航班（22 家承运人）、状态（查本库） |
| 用户管理 | `user` | 列表 / 新增 / 修改 / 启停 / 删除 / 重置密码，仅 `level = 1` 可进 |

## 快速开始

```bash
composer install
cp .env.example .env
php artisan key:generate
# 编辑 .env：DB_HOST / DB_DATABASE / DB_USERNAME / DB_PASSWORD
php artisan serve            # 或挂到 Nginx + PHP-FPM
```

访问 `http://127.0.0.1:8000/login`。账号须满足 `users.level = 1` 才允许登录
（原系统的隐藏约束，已在 `LoginController` 中保留）。

### 必需的 `.env` 约定

```dotenv
APP_TIMEZONE=Asia/Shanghai   # 必须，否则打印日期与写库时间差 8 小时
BCRYPT_ROUNDS=10             # 必须，原库哈希即为 10；改了会触发 needsRehash 静默改写生产库
SESSION_DRIVER=file          # 与原站共库，禁止用 database 驱动
CACHE_STORE=file             # 同上
QUEUE_CONNECTION=sync        # 同上
```

## 关键自定义（替代已废弃依赖）

| 文件 | 作用 |
|---|---|
| `app/Support/Form.php` | 复刻 `laravelcollective/html` 的子集：`open/close/model/text/label/date/textarea/submit/checkbox/radio/select`，含 `old() → 模型属性 → null` 的绑定栈，使 42 个旧视图几乎原样复用 |
| `app/Support/Xlsx.php` | 零依赖 OOXML 生成器，支持合并单元格、列宽、行高、边框、字体、横向打印与 `fitToHeight` |
| `app/Support/helpers.php` | `theme()` 模板目录机制（`APP_TM` → `resources/views/{theme}`） |

## 测试

```bash
./vendor/bin/phpunit            # 全量
./vendor/bin/phpunit --filter PrintTest
```

测试直接跑在 `.env` 指向的**真实业务库**上（本系统所有页面依赖存量数据，sqlite 无法覆盖），
因此：

- 凭据**一律不写进代码**，统一由 `Tests\TestCase::useBusinessDatabase()` 从 `.env` 读取；
- 写操作类测试必须自带事务回滚，保证库中零残留；
- 缺少 `DB_DATABASE` 配置时自动 `markTestSkipped`，而非报一串连接错误。

## 踩过的坑（改动前务必看）

1. **门面别名**：Laravel 11 起骨架不再生成 `config('app.aliases')`，需用
   `Facade::defaultAliases()` 全量恢复，否则 `Class "Auth" not found`。
2. **不要保留 `TrimStrings` / `ConvertEmptyStringsToNull`**：会把未填字段变成 `null`，
   撞上 NOT NULL 列报 1048；原站 5.4 的行为是传空串。
3. **零日期**：`fltdate` / `regtime` 存在 `0000-00-00`，MySQL 连接已自定义 `modes`
   去掉 `NO_ZERO_DATE`；控制器把空值归一为 `'0000-00-00'` / `'0000-00-00 00:00:00'`，
   列是 NOT NULL，**不要改成 NULL**。
4. **打印页保存**必须按 `$request->has()` 白名单更新，禁止 `except()` 直接 update，
   否则会冲掉表单未提交的业务列（`?save=yes` 这类查询参数也会被当成列名写库）。
5. **`route()` 参数名严格**：`{mawb}` / `{id}` 必须与定义一致，否则 `Missing required parameter`。
6. **视图缓存目录**：若运行在受限 sandbox（`unlink`/`rename` 被拒），把
   `VIEW_COMPILED_PATH` 指到 `/var/tmp` 下；正常环境用默认 `storage/framework/views` 即可。

## 已知限制

- **6 家抓取型航司**（国航、东航、马航、UPS、土航、空桥）不做服务端抓取：原实现的
  硬编码 `__VIEWSTATE` 已失效，相关站点或返回 412、或直接不可达，现改为跳转官网入口。
- **报表、统计首页、总单货量暂不做**：原站这三处本身就没有实现（`ReportController`
  只有构造函数，`report/*` 三条路由指向不存在的方法；`stats` 与 `stats/mawb` 各只有
  一行 `echo`），没有可参照的口径，2026-10-29 起入口连同路由一并下线，访问即 404。
  需要时按 `UserController` 的做法补真实实现，不要再回到占位页。
- **`users` 表与原站共用**：在用户管理里改密码 / 停用账号会直接影响原站登录；
  原站只放行 `level = 1` 的账号，所以 `level` 实际就是「启用 / 停用」开关。
- 依赖历史表 `password_resets`（原站命名），与 Laravel 13 默认的
  `password_reset_tokens` 不同，启用找回密码功能前需适配。

## License

私有项目，未授权禁止转载或商用。
