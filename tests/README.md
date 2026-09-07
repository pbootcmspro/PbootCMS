# PbootCMS 原生 PHP 测试

无 Composer / PHPUnit 依赖，仅 PHP CLI。

## 运行

```bash
php tests/run.php
php tests/run.php --suite=unit
php tests/run.php --suite=integration
php tests/run.php --filter=ValidateIf
php tests/unit/ValidateIfConditionTest.php
```

`run.php` 会为每个 `*Test.php` 启动独立 PHP 进程，避免 `SITE_DIR` 等常量与静态状态跨文件互相污染。

## 目录

| 路径 | 说明 |
|---|---|
| `bootstrap.php` | 轻量环境，不启 Kernel |
| `support/` | Assert、ParserControllerHarness、DatabaseTestSupport |
| `fixtures/security/` | pboot:if 绕过 payload |
| `fixtures/php8_upgrade_bootstrap/` | PHP 8 在线升级自举补丁文件清单 |
| `unit/` | 单函数/单方法 |
| `integration/` | 多模块管线 |
| `contract/` | 源码/行为不变量 |
| `scripts/` | 子进程探测、自举补丁打包（`build_php8_bootstrap_patch.php`） |

## 安全测试约束

- payload 仅作死字符串，禁止 `eval`、写文件、外网请求
- 断言「未执行 / 标签已中和」，不断言恶意代码副作用

## 关联

- [Issue #28](https://github.com/pbootcmspro/PbootCMS/issues/28) — `{pboot:if}` 编码绕过
- 审计 #6 — `AreaController` 区域编码与域名输入过滤（`AreaInputFilterTest` / `AreaControllerFilterContractTest`）
- 审计 #8 — 内容/栏目/站点标题与描述输入清洗（`TitleDescInputFilterTest` / `TitleDescControllerFilterContractTest`）
- 审计 #11 — 常规清缓存保留 `runtime/image`（`ClearCachePreserveImageTest` / `ClearCachePreserveImageContractTest`）
- Issue #26 / 审计 #7 — 站点统计代码完整输出（可信管理员；`StatisticalSanitizeTest` / `StatisticalSanitizeContractTest`）
- Issue #199 — `get_user_ip()` 可信代理与转发头解析（`GetUserIpTrustedProxyTest`）
- Issue #200 — UEditor 远程抓图 SSRF 加固（`RemoteFetchValidationTest` / `UeditorCrawlerSsrfContractTest`）
- Issue #223 — 批量复制内容清空自定义 URL 名称（`ContentCopyFilenameTest`）

