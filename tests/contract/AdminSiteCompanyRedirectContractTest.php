<?php

declare(strict_types=1);

/**
 * @suite contract
 * @covers apps/admin/controller/content/SiteController.php mod/imagickProbe
 * @covers apps/admin/controller/content/CompanyController.php mod
 *
 * 运行：php tests/contract/AdminSiteCompanyRedirectContractTest.php
 */

if (!defined('TEST_ROOT')) {
    require dirname(__DIR__) . '/bootstrap.php';
}

return TestAssert::runSuite(function () {
    echo "=== site and company save return to their admin pages ===\n";

    $site = file_get_contents(APP_PATH . '/admin/controller/content/SiteController.php');
    $company = file_get_contents(APP_PATH . '/admin/controller/content/CompanyController.php');
    TestAssert::false($site === false, 'SiteController readable');
    TestAssert::false($company === false, 'CompanyController readable');

    $siteMod = admin_redirect_method_body($site, 'function mod(');
    $siteProbe = admin_redirect_method_body($site, 'function imagickProbe(');
    $companyMod = admin_redirect_method_body($company, 'function mod(');

    TestAssert::contains($siteMod, "url('/admin/Site/index')", 'site mod target is site index');
    TestAssert::contains($siteMod, 'writeOk($this->model->modSite($data), true)', 'site update treats unchanged as saved');
    TestAssert::contains($siteMod, 'writeOk($this->model->addSite($data), false)', 'site insert does not treat 0 as saved');
    TestAssert::notContains($siteMod, '-1', 'site mod does not use referer sentinel');
    TestAssert::notContains($siteMod, 'location(', 'site mod does not send a bare location');

    TestAssert::contains($siteProbe, "url('/admin/Site/server')", 'imagick probe returns to server page');
    TestAssert::notContains($siteProbe, '-1', 'imagick probe does not use referer sentinel');

    TestAssert::contains($companyMod, "url('/admin/Company/index')", 'company mod target is company index');
    TestAssert::contains($companyMod, 'writeOk($this->model->modCompany($data), true)', 'company update treats unchanged as saved');
    TestAssert::contains($companyMod, 'writeOk($this->model->addCompany($data), false)', 'company insert does not treat 0 as saved');
    TestAssert::notContains($companyMod, '-1', 'company mod does not use referer sentinel');
    TestAssert::notContains($companyMod, 'location(', 'company mod does not send a bare location');
});

function admin_redirect_method_body($source, $signature)
{
    $start = strpos($source, $signature);
    TestAssert::false($start === false, $signature . ' found');
    $from = $start + strlen($signature);
    $candidates = array();
    foreach (array("\n    public function ", "\n    private function ") as $marker) {
        $pos = strpos($source, $marker, $from);
        if ($pos !== false) {
            $candidates[] = $pos;
        }
    }
    TestAssert::false(count($candidates) === 0, $signature . ' has a following method');
    $next = min($candidates);
    return substr($source, $start, $next - $start);
}
