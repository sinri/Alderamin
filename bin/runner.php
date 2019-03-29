<?php
/**
 * Created by PhpStorm.
 * User: sinri
 * Date: 2019-03-30
 * Time: 00:49
 */

require_once __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set("Asia/Shanghai");

\sinri\ark\cli\ArkCliProgram::run("sinri\\Alderamin\\cli\\");