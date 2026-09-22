<?php
/**
 * @copyright (C)2016-2099
 * @author pbootcms
 * @email support@pbootcms.com
 *   系统日志控制器
 */
namespace app\admin\controller\system;

use core\basic\Controller;
use app\admin\model\system\SyslogModel;
use core\log\TextLogReader;

class SyslogController extends Controller
{

    private $model;

    public function __construct()
    {
        $this->model = new SyslogModel();
    }

    public function index()
    {
        $tab = $this->resolveLogTab();
        $this->assignLogTabUrls();

        if ($tab === 'sys') {
            $this->assign('syslogs', $this->model->getList());
        } else {
            $this->assignTextLogs($tab);
        }

        $this->display('system/syslog.html');
    }

    public function clear()
    {
        if ($this->model->clearLog()) {
            alert_location('清空成功！', url('/admin/Syslog/index'));
        } else {
            alert_location('清空失败！', url('/admin/Syslog/index'));
        }
    }

    private function resolveLogTab()
    {
        $tab = get('tab', 'var');
        if (! in_array($tab, array(
            'sys',
            'spider',
            'ai'
        ), true)) {
            $tab = 'sys';
        }
        $this->assign('log_tab', $tab);
        return $tab;
    }

    private function assignLogTabUrls()
    {
        $baseUrl = url('/admin/Syslog/index');
        $this->assign('sys_tab_url', $baseUrl);
        $this->assign('spider_tab_url', $this->logTabQueryUrl($baseUrl, array(
            'tab' => 'spider'
        )));
        $this->assign('ai_tab_url', $this->logTabQueryUrl($baseUrl, array(
            'tab' => 'ai'
        )));
    }

    private function assignTextLogs($type)
    {
        $yearRaw = get('year');
        $monthRaw = get('month');
        $dayRaw = get('day');
        $hasY = ($yearRaw !== null && $yearRaw !== '');
        $hasM = ($monthRaw !== null && $monthRaw !== '');
        $hasD = ($dayRaw !== null && $dayRaw !== '');
        $allDefault = (! $hasY && ! $hasM && ! $hasD);

        $todayY = (int) date('Y');
        $todayM = (int) date('n');
        $todayD = (int) date('j');

        $year = $hasY ? (int) $yearRaw : $todayY;
        $month = $hasM ? (int) $monthRaw : $todayM;
        $day = $hasD ? (int) $dayRaw : ($allDefault ? $todayD : 0);

        $years = TextLogReader::listYears($type);
        $days = array();
        $logs = array();
        $emptyMonth = false;
        $emptyDay = false;
        $showDay = false;
        $truncated = false;
        $shown = 0;
        $dateInvalid = false;

        $baseUrl = url('/admin/Syslog/index');
        $ymValid = TextLogReader::isValidYear($year) && TextLogReader::isValidMonth($month);

        if (! $ymValid) {
            $dateInvalid = true;
        } else {
            $dayNums = TextLogReader::listDays($type, $year, $month);
            $emptyMonth = ($dayNums === array());
            foreach ($dayNums as $d) {
                $item = new \stdClass();
                $item->day = $d;
                $item->label = sprintf('%04d-%02d-%02d', $year, $month, $d);
                $item->url = $this->logTabQueryUrl($baseUrl, array(
                    'tab' => $type,
                    'year' => $year,
                    'month' => $month,
                    'day' => $d
                ));
                $item->current = ($day === (int) $d) ? 1 : 0;
                $item->btn_class = $item->current ? 'layui-btn layui-btn-sm' : 'layui-btn layui-btn-sm layui-btn-primary';
                $days[] = $item;
            }

            if ($day > 0) {
                if (! TextLogReader::isValidDay($year, $month, $day)) {
                    $dateInvalid = true;
                    $emptyMonth = false;
                } elseif (! $emptyMonth) {
                    $showDay = true;
                    $file = TextLogReader::dayFile($type, $year, $month, $day);
                    $read = TextLogReader::readTail($type, $file);
                    if (! $read['exists'] || $read['shown'] < 1) {
                        $emptyDay = true;
                    } else {
                        $truncated = $read['truncated'];
                        $shown = $read['shown'];
                        foreach (TextLogReader::parseLines($type, $read['lines']) as $row) {
                            $logs[] = (object) $row;
                        }
                        if ($logs === array()) {
                            $emptyDay = true;
                            $truncated = false;
                            $shown = 0;
                        }
                    }
                }
            }
        }

        if ($dateInvalid) {
            $emptyMonth = false;
            $emptyDay = false;
            $showDay = false;
        }

        $todayUrl = $this->logTabQueryUrl($baseUrl, array(
            'tab' => $type
        ));
        $months = range(1, 12);
        $this->assign('text_tab', $type);
        $this->assign('text_year', $year);
        $this->assign('text_month', $month);
        $this->assign('text_day', $day);
        $this->assign('text_years', $years);
        $this->assign('text_months', $months);
        $this->assign('text_days', $days);
        $this->assign('text_logs', $logs);
        $this->assign('text_empty_month', $emptyMonth);
        $this->assign('text_empty_day', $emptyDay);
        $this->assign('text_show_day', $showDay);
        $this->assign('text_truncated', $truncated);
        $this->assign('text_shown', $shown);
        $this->assign('text_date_invalid', $dateInvalid);
        $this->assign('text_today_url', $todayUrl);
    }

    private function logTabQueryUrl($url, array $query)
    {
        $qs = http_build_query($query);
        if ($qs === '') {
            return $url;
        }
        $sep = (strpos($url, '?') === false) ? '?' : '&';
        return $url . $sep . $qs;
    }
}
