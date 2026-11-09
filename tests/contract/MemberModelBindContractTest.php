<?php

declare(strict_types=1);

/**
 * @suite contract
 * @covers apps/home/model/MemberModel.php
 * @covers apps/admin/model/member/MemberModel.php
 * @covers apps/admin/controller/member/MemberController.php
 *
 * 会员目标路径不得重新引入字符串 WHERE/SET 拼接。
 */

if (! defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

return TestAssert::runSuite(function () {
    echo "=== home MemberModel ===\n";

    $home = file_get_contents(APP_PATH . '/home/model/MemberModel.php');
    TestAssert::false($home === false, 'home MemberModel readable');
    TestAssert::notContains($home, 'session(\'pboot_uid\') . "', 'no session concat into SQL');
    TestAssert::notContains($home, "\"gcode='\$gcode'\"", 'no gcode string concat');
    TestAssert::notContains($home, "'id<>' .", 'no id<> concat');
    TestAssert::notContains($home, 'where("a.id=', 'no a.id string where');
    TestAssert::notContains($home, 'where("id=\'', 'no id string where');
    TestAssert::contains($home, 'whereCompare', 'uses whereCompare');
    TestAssert::contains($home, 'bindWhere', 'uses bindWhere');

    echo "=== admin MemberModel ===\n";

    $admin = file_get_contents(APP_PATH . '/admin/model/member/MemberModel.php');
    TestAssert::false($admin === false, 'admin MemberModel readable');
    TestAssert::notContains($admin, '"a.id=$id"', 'no a.id=$id');
    TestAssert::notContains($admin, '"id=$id"', 'no id=$id');
    TestAssert::contains($admin, 'whereCompare', 'admin uses whereCompare');
    TestAssert::contains($admin, 'modMemberStatus', 'has modMemberStatus');

    echo "=== admin MemberController ===\n";

    $ctl = file_get_contents(APP_PATH . '/admin/controller/member/MemberController.php');
    TestAssert::false($ctl === false, 'admin MemberController readable');
    TestAssert::notContains($ctl, 'checkMember("', 'no string checkMember');
    TestAssert::notContains($ctl, '"$field=\'$value\'"', 'no field value SET concat');
    TestAssert::notContains($ctl, '"status=1"', 'no status=1 string SET');
    TestAssert::notContains($ctl, '"status=0"', 'no status=0 string SET');
    TestAssert::contains($ctl, 'modMemberStatus', 'uses modMemberStatus');
    TestAssert::contains($ctl, "admin_posted_field(array('status')", 'status field whitelist');
    TestAssert::contains($ctl, "\$posted[1] !== '0' && \$posted[1] !== '1'", 'status values limited to 0/1');
});
