<?php
/**
 * Created by PhpStorm.
 * User: sinri
 * Date: 2019-03-29
 * Time: 23:21
 */

$config = [];

/**
 * The core data
 */
$config['core'] = [
    "pdo" => [
        "host" => "",
        "port" => "3306",
        "username" => "",
        "password" => "",
        "database" => "polaris",
        "charset" => \sinri\ark\database\pdo\ArkPDOConfig::CHARSET_UTF8,
        "engine" => \sinri\ark\database\pdo\ArkPDOConfig::ENGINE_MYSQL,
    ],
    "sql-store" => "",
    "report-store" => "",
    "cron-max" => 3,
];

$config['tp'] = [
    "sample_tp_code" => "sample_tp_key",
];

$config['nodes'] = [
    'write_node' => [
        "host" => "",
        "port" => 3306,
        "username" => "",
        "password" => "",
        "charset" => "utf8",
    ],
    'read_node' => [
        "host" => "",
        "port" => 3306,
        "username" => "",
        "password" => "",
        "charset" => "utf8",
    ],
    // You might define more here
];

$config['log'] = [
    'path' => __DIR__ . '/../log',
    "level" => \Psr\Log\LogLevel::INFO,
];

$config['aliyun-oss'] = [
    'ak_id' => '',
    'ak_secret' => '',
    'endpoint' => '',
    'bucket' => '',
];

$config['email'] = [
    'host' => 'smtp.alderamin.cc',
    'smtp_auth' => true,
    'username' => '',
    'password' => '',
    'smtp_secure' => 'ssl',
    'port' => 465,
    'display_name' => 'Alderamin Post Office',
];