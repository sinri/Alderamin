<?php
/**
 * Created by PhpStorm.
 * User: sinri
 * Date: 2019-03-31
 * Time: 23:19
 */

require_once __DIR__ . '/../bootstrap.php';

$reportManage = new \sinri\Alderamin\manage\ReportManage();

$reportId = $reportManage->newReport("SampleReport", "Test-" . time(), "tester", ["limitation" => 10], "SampleReportSingleThread");
var_dump($reportId);