<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers core/function/handle.php get_user_os()
 * @covers core/function/handle.php is_mobile()
 *
 * UA样本出处见 PbootCMS-dev#108、#115
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

return TestAssert::runSuite(function () {

    $withUa = function (string $ua) {
        $_SERVER['HTTP_USER_AGENT'] = $ua;
    };

    echo "=== 纯血鸿蒙（HarmonyOS NEXT / OpenHarmony）===\n";

    $withUa('Mozilla/5.0 (Phone; OpenHarmony 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36 ArkWeb/4.1.6.1 Mobile');
    TestAssert::same('HarmonyOS', get_user_os(), 'ArkWeb手机: os为HarmonyOS');
    TestAssert::same(true, is_mobile(), 'ArkWeb手机: 判定为移动设备');

    $withUa('Mozilla/5.0 (Phone; OpenHarmony 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36 ArkWeb/4.1.6.1 Mobile HuaweiBrowser/5.0.4.303');
    TestAssert::same('HarmonyOS', get_user_os(), '华为浏览器手机: os为HarmonyOS');
    TestAssert::same(true, is_mobile(), '华为浏览器手机: 判定为移动设备');

    $withUa('Mozilla/5.0 (Tablet; OpenHarmony 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36 ArkWeb/6.1.0.117 HuaweiBrowser/6.1.5.300');
    TestAssert::same('HarmonyOS Pad', get_user_os(), '鸿蒙平板: os为HarmonyOS Pad');
    TestAssert::same(true, is_mobile(), '鸿蒙平板: 判定为移动设备（UA中无Mobile标记）');

    $withUa('Mozilla/5.0 (PC; OpenHarmony 6.1; Windows NT 10.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/132.0.0.0 Safari/537.36 ArkWeb/6.1.0.100 HuaweiBrowser/6.1.3.321');
    TestAssert::same('HarmonyOS PC', get_user_os(), '鸿蒙二合一: os为HarmonyOS PC，未被windows nt 10抢先匹配');
    TestAssert::same(false, is_mobile(), '鸿蒙二合一: 不判定为移动设备');

    // 出处：matomo device-detector 桌面端 fixture；非 Mozilla/5.0 形状，版本号用连字符
    $withUa('com.huawei.hmos.browser (2in1;OpenHarmony-6.0.2.130;HAD-W24) Chrome/132.0.0.0');
    TestAssert::same('HarmonyOS PC', get_user_os(), '鸿蒙二合一2in1拼写: os为HarmonyOS PC');
    TestAssert::same(false, is_mobile(), '鸿蒙二合一2in1拼写: 不判定为移动设备');

    $withUa('Mozilla/5.0 (Phone; OpenHarmony 5.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36 ArkWeb/4.1.6.1 Mobile MicroMessenger/8.0.9.39(0x28002733) Weixin NetType/WIFI Language/zh_CN');
    TestAssert::same('HarmonyOS', get_user_os(), '鸿蒙微信: os为HarmonyOS');
    TestAssert::same(true, is_mobile(), '鸿蒙微信: 判定为移动设备');
    TestAssert::same('Weixin', get_user_bs(), '鸿蒙微信: 浏览器仍识别为Weixin');

    // 合成UA，无真实出处，仅用于钉住未知形态的兜底策略
    $withUa('Mozilla/5.0 (OpenHarmony 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36 ArkWeb/4.1.6.1');
    TestAssert::same('HarmonyOS PC', get_user_os(), '鸿蒙未知形态: 兜底为非移动设备的HarmonyOS PC');
    TestAssert::same(false, is_mobile(), '鸿蒙未知形态: 不判定为移动设备');

    // 小写形态token且同时含Android；is_mobile 改动前后均为 true，仅os标签精化
    $withUa('Mozilla/5.0 (phone; Android 12; OpenHarmony 6.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.4896.58 Safari/537.36 UCBrowser/17.1.8.1349');
    TestAssert::same('HarmonyOS', get_user_os(), '鸿蒙UC浏览器小写phone: os为HarmonyOS');
    TestAssert::same(true, is_mobile(), '鸿蒙UC浏览器小写phone: 判定为移动设备');

    // 同时含OpenHarmony与HarmonyOS双token，守卫「改去匹配harmonyos关键词」的实现
    $withUa('Mozilla/5.0 (PC; OpenHarmony 5.0; HarmonyOS 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36 Browser/harmony360Browser/1.0.0');
    TestAssert::same('HarmonyOS PC', get_user_os(), '鸿蒙双token二合一: os为HarmonyOS PC');
    TestAssert::same(false, is_mobile(), '鸿蒙双token二合一: 不判定为移动设备');

    echo "=== 传入检测值的直接检测路径 ===\n";

    $withUa('Mozilla/5.0 (Phone; OpenHarmony 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36 ArkWeb/4.1.6.1 Mobile');
    TestAssert::same(true, get_user_os('OpenHarmony'), 'get_user_os(OpenHarmony): 命中');
    TestAssert::same(false, get_user_os('Android'), 'get_user_os(Android): 纯血鸿蒙不含android');

    echo "=== 旧版鸿蒙2.0-4.x（AOSP内核，UA含android）===\n";

    $withUa('Mozilla/5.0 (Linux; Android 12; HarmonyOS; JAD-AL50; HMSCore 6.13.0.320; GMSCore 24.15.15) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.5735.196 HuaweiBrowser/17.0.4.302 Mobile Safari/537.36');
    TestAssert::same('Android', get_user_os(), '旧版鸿蒙: os保持为Android，未被改标');
    TestAssert::same(true, is_mobile(), '旧版鸿蒙: 仍判定为移动设备');

    echo "=== 既有设备回归 ===\n";

    $withUa('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1');
    TestAssert::same('iPhone', get_user_os(), 'iPhone: os为iPhone');
    TestAssert::same(true, is_mobile(), 'iPhone: 判定为移动设备');

    $withUa('Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1');
    TestAssert::same('iPad', get_user_os(), 'iPad: os为iPad');
    TestAssert::same(true, is_mobile(), 'iPad: 判定为移动设备');

    $withUa('Mozilla/5.0 (Linux; Android 13; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36');
    TestAssert::same('Android', get_user_os(), 'Android: os为Android');
    TestAssert::same(true, is_mobile(), 'Android: 判定为移动设备');

    $withUa('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    TestAssert::same('Windows 10', get_user_os(), 'Windows 10: os为Windows 10');
    TestAssert::same(false, is_mobile(), 'Windows 10: 不判定为移动设备');

    $withUa('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    TestAssert::same('Mac', get_user_os(), 'Mac: os为Mac');
    TestAssert::same(false, is_mobile(), 'Mac: 不判定为移动设备');

    $withUa('Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/109.0.0.0 Safari/537.36');
    TestAssert::same('Windows 7', get_user_os(), 'Windows 7: os为Windows 7');
    TestAssert::same(false, is_mobile(), 'Windows 7: 不判定为移动设备');

    $withUa('Mozilla/5.0 (Windows Phone 10.0; Android 6.0.1; Microsoft; Lumia 950) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/52.0.2743.116 Mobile Safari/537.36 Edge/15.15254');
    TestAssert::same(true, is_mobile(), 'Windows Phone: 仍判定为移动设备');
});
