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

$alderaminConfig->setCorePdoConfig(new \sinri\ark\database\pdo\ArkPDOConfig([
    \sinri\ark\database\pdo\ArkPDOConfig::CONFIG_TITLE => "ALDERAMIN",
    \sinri\ark\database\pdo\ArkPDOConfig::CONFIG_HOST => "127.0.0.1",
    \sinri\ark\database\pdo\ArkPDOConfig::CONFIG_PORT => 3306,
    \sinri\ark\database\pdo\ArkPDOConfig::CONFIG_USERNAME => "sinri",
    \sinri\ark\database\pdo\ArkPDOConfig::CONFIG_PASSWORD => "123456",
    \sinri\ark\database\pdo\ArkPDOConfig::CONFIG_DATABASE => "alderamin",
]));
$alderaminConfig->setCraftStore(__DIR__ . '/../store/craft-store');
$alderaminConfig->setReportStore(__DIR__ . '/../store/report-store');
$alderaminConfig->setUnitStoreNamespace("sinri\\Alderamin\\test\\units");
$alderaminConfig->setSqlStore(__DIR__ . '/../store/sql-store');
$alderaminConfig->setCronMax(5);
$alderaminConfig->setLogBaseLevel(\Psr\Log\LogLevel::DEBUG);
$alderaminConfig->setLogDirPath(__DIR__ . '/../log');


$readNodeConfig = (new \sinri\ark\database\mysqli\ArkMySQLiConfig())
    ->setTitle("ALDERAMIN_READ")
    ->setHost("127.0.0.1")
    ->setPort(3306)
    ->setUsername("sinri")
    ->setPassword("123456")
    ->setDatabase("test");
$writeNodeConfig = (new \sinri\ark\database\mysqli\ArkMySQLiConfig())
    ->setTitle("ALDERAMIN_READ")
    ->setHost("127.0.0.1")
    ->setPort(3306)
    ->setUsername("sinri")
    ->setPassword("123456")
    ->setDatabase("test");

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
