<?php

declare(strict_types=1);

/**
 * @suite unit
 * @covers core/function/handle.php verify_retrieve_code()
 *
 * fix #201 回归：找回密码验证码过期与失败锁定
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

return TestAssert::runSuite(function () {
    $now = 1700000000;
    $baseSession = array(
        'retrieve_checkcode' => 'abc123',
        'retrieve_email' => 'user@example.com',
        'retrieve_checkcode_time' => $now,
        'retrieve_checkcode_failures' => 0
    );

    echo "=== verify_retrieve_code() ok ===\n";

    $ok = verify_retrieve_code('ABC123', 'User@Example.com', $baseSession, $now);
    TestAssert::same('ok', $ok['status'], 'verify: match succeeds');
    TestAssert::same(0, $ok['failures'], 'verify: ok keeps failures');

    echo "=== verify_retrieve_code() missing ===\n";

    $missing = verify_retrieve_code('abc123', 'user@example.com', array(), $now);
    TestAssert::same('missing', $missing['status'], 'verify: empty session');

    echo "=== verify_retrieve_code() expired ===\n";

    $expired = verify_retrieve_code(
        'abc123',
        'user@example.com',
        array_merge($baseSession, array('retrieve_checkcode_time' => $now - RETRIEVE_CODE_TTL - 1)),
        $now
    );
    TestAssert::same('expired', $expired['status'], 'verify: past TTL');

    $noTime = verify_retrieve_code(
        'abc123',
        'user@example.com',
        array_merge($baseSession, array('retrieve_checkcode_time' => null)),
        $now
    );
    TestAssert::same('expired', $noTime['status'], 'verify: missing issued time');

    echo "=== verify_retrieve_code() mismatch and lock ===\n";

    $mismatch = verify_retrieve_code('wrong1', 'user@example.com', $baseSession, $now);
    TestAssert::same('mismatch', $mismatch['status'], 'verify: wrong code');
    TestAssert::same(1, $mismatch['failures'], 'verify: mismatch increments failures');

    $lockedSession = array_merge($baseSession, array('retrieve_checkcode_failures' => RETRIEVE_CODE_MAX_FAIL));
    $locked = verify_retrieve_code('abc123', 'user@example.com', $lockedSession, $now);
    TestAssert::same('locked', $locked['status'], 'verify: already at max failures');

    $session = $baseSession;
    $last = null;
    for ($i = 1; $i <= RETRIEVE_CODE_MAX_FAIL; $i++) {
        $last = verify_retrieve_code('bad' . $i, 'user@example.com', $session, $now);
        $session['retrieve_checkcode_failures'] = $last['failures'];
    }
    TestAssert::same('locked', $last['status'], 'verify: locked after max consecutive failures');
    TestAssert::same(RETRIEVE_CODE_MAX_FAIL, $last['failures'], 'verify: failures at threshold');

    echo "=== retrieve_code_expired() boundary ===\n";

    TestAssert::false(retrieve_code_expired($now, $now), 'expired: fresh code');
    TestAssert::true(retrieve_code_expired($now - RETRIEVE_CODE_TTL, $now), 'expired: at TTL');
    TestAssert::true(retrieve_code_expired($now - RETRIEVE_CODE_TTL - 1, $now), 'expired: one second past TTL');
});
