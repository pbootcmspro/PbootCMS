<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers core\basic\Smtp::setMail() 长正文 base64 按 76 字符换行
 * @covers core\basic\Smtp::readFile() 附件 base64 按 76 字符换行
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

require_once CORE_PATH . '/basic/Smtp.php';

class SmtpLongBodyHarness extends \core\basic\Smtp
{
    public function dataPayload(): string
    {
        foreach ($this->getCommand() as $cmd) {
            if ($cmd[1] === 250 && strpos($cmd[0], 'Content-Transfer-Encoding') !== false) {
                return $cmd[0];
            }
        }
        return '';
    }
}

function smtp_long_body_max_line(string $data): int
{
    return max(array_map('strlen', explode("\r\n", $data)));
}

return TestAssert::runSuite(function () {
    // 756 字节原文，未换行时 base64 为 1008 字符单行，超过 RFC 5322 的 998 限制
    $body = str_repeat('留言内容', 63);

    echo "=== 长正文每行不超过 998 字符且可还原 ===\n";
    $smtp = new SmtpLongBodyHarness('smtp.example.com', 'from@example.com', 'x');
    $smtp->setReceiver('to@example.com');
    $smtp->setMail('长正文', $body);
    $data = $smtp->dataPayload();
    TestAssert::true($data !== '', 'DATA payload found');
    TestAssert::true(smtp_long_body_max_line($data) <= 998, 'body lines <= 998');
    TestAssert::contains($data, "\r\n" . substr(base64_encode($body), 0, 76) . "\r\n", 'body wrapped at 76');
    $parts = explode("Content-Transfer-Encoding: base64\r\n\r\n", $data, 2);
    $encoded = strstr($parts[1], "\r\n--", true);
    TestAssert::true(base64_decode($encoded) === $body, 'body decodes to original');

    echo "=== 附件每行不超过 998 字符 ===\n";
    $file = tempnam(sys_get_temp_dir(), 'smtp');
    $content = str_repeat('A', 2000);
    file_put_contents($file, $content);
    $smtp = new SmtpLongBodyHarness('smtp.example.com', 'from@example.com', 'x');
    $smtp->setReceiver('to@example.com');
    $smtp->setMail('附件', 'short');
    $smtp->addAttachment($file);
    $data = $smtp->dataPayload();
    unlink($file);
    TestAssert::true(smtp_long_body_max_line($data) <= 998, 'attachment lines <= 998');
    TestAssert::contains($data, "\r\n" . substr(base64_encode($content), 0, 76) . "\r\n", 'attachment wrapped at 76');
});
