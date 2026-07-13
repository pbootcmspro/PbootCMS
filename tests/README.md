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

## 目录

| 路径 | 说明 |
|---|---|
| `bootstrap.php` | 轻量环境，不启 Kernel |
| `support/` | Assert、ParserControllerHarness |
| `fixtures/security/` | pboot:if 绕过 payload |
| `unit/` | 单函数/单方法 |
| `integration/` | 多模块管线 |
| `contract/` | 源码/行为不变量 |

## 安全测试约束

- payload 仅作死字符串，禁止 `eval`、写文件、外网请求
- 断言「未执行 / 标签已中和」，不断言恶意代码副作用

## 关联

- [Issue #28](https://github.com/pbootcmspro/PbootCMS/issues/28) — `{pboot:if}` 编码绕过
