<?php
/**
 * Created by PhpStorm.
 * User: sinri
 * Date: 2019-03-30
 * Time: 22:24
 */


require_once __DIR__ . '/../../vendor/autoload.php';

date_default_timezone_set("Asia/Shanghai");

$alderaminConfig = new \sinri\Alderamin\core\configuration\AlderaminConfig();

require_once __DIR__ . '/config/pdo.php';
$alderaminConfig->setCorePdoConfig($pdoConfig);
$alderaminConfig->setCraftStore(__DIR__ . '/../store/craft-store');
$alderaminConfig->setReportStore(__DIR__ . '/../store/report-store');
$alderaminConfig->setUnitStoreNamespace("sinri\\Alderamin\\test\\units");
$alderaminConfig->setSqlStore(__DIR__ . '/../store/sql-store');
$alderaminConfig->setCronMax(5);
$alderaminConfig->setLogBaseLevel(\Psr\Log\LogLevel::DEBUG);
$alderaminConfig->setLogDirPath(__DIR__ . '/../log');

require_once __DIR__ . '/config/nodes.php';

$alderaminConfig->setNodesMySQLiConfigList([
    \sinri\Alderamin\core\configuration\AlderaminConfig::KEY_READ_NODE => $readNodeConfig,
    \sinri\Alderamin\core\configuration\AlderaminConfig::KEY_WRITE_NODE => $writeNodeConfig,
]);

require_once __DIR__ . '/config/mail.php';

$alderaminConfig->setSmtpConfig($smtpConfig);

\sinri\Alderamin\core\Alderamin::setConfig($alderaminConfig);

\sinri\Alderamin\core\unit\ReportPermission::setPermissionDefinitions([
    \sinri\Alderamin\test\permission\DevelopPermission::getInstance(),
]);
