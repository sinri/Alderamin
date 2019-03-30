<?php
/**
 * Created by PhpStorm.
 * User: sinri
 * Date: 2019-03-29
 * Time: 23:28
 */

namespace sinri\Alderamin\core;


use sinri\Alderamin\core\configuration\AlderaminConfig;
use sinri\ark\core\ArkLogger;
use sinri\ark\database\mysqli\ArkMySQLi;
use sinri\ark\database\pdo\ArkPDO;

class Alderamin
{
    /**
     * @var AlderaminConfig
     */
    protected static $config;

    /**
     * @return AlderaminConfig
     */
    public static function getConfig()
    {
        return self::$config;
    }

    /**
     * @param AlderaminConfig $config
     */
    public static function setConfig($config)
    {
        self::$config = $config;
    }


    /**
     * @var ArkMySQLi[]
     */
    public static $databaseNodes = [];
    /**
     * @var ArkLogger[]
     */
    protected static $loggers = [];
    /**
     * @var ArkPDO
     */
    protected static $coreDatabase;

    /**
     * @param string $aspect
     * @return ArkLogger
     */
    public static function getLogger($aspect = "default")
    {
        if (!isset(self::$loggers[$aspect])) {
            self::$loggers[$aspect] = new ArkLogger(self::getConfig()->getLogDirPath(), $aspect);
            self::$loggers[$aspect]->setShowProcessID(true);
            self::$loggers[$aspect]->setIgnoreLevel(self::getConfig()->getLogBaseLevel());
        }
        return self::$loggers[$aspect];
    }

    /**
     * @param string $name
     * @return ArkMySQLi
     * @throws \Exception
     */
    public static function getDatabaseNode($name)
    {
        if (!isset(self::$databaseNodes[$name])) {
            $DB = new ArkMySQLi(self::getConfig()->getMySQLiNodeConfig($name));
            $DB->connect();

            self::$databaseNodes[$name] = $DB;
        }
        return self::$databaseNodes[$name];
    }

    /**
     * @param string $name
     */
    public static function revokeDatabaseNode($name)
    {
        if (isset(self::$databaseNodes[$name])) {
            self::$databaseNodes[$name]->getInstanceOfMySQLi()->close();
            self::$databaseNodes[$name] = null;
            unset(self::$databaseNodes[$name]);
        }
    }

    /**
     * @param bool $forceReload
     * @return ArkPDO
     * @throws \Exception
     */
    public static function getSharedCoreDatabase($forceReload = false)
    {
        if (!self::$coreDatabase || $forceReload) {
            self::$coreDatabase = self::getNewCoreDatabase();
        }
        return self::$coreDatabase;
    }

    /**
     * @return ArkPDO
     * @throws \Exception
     */
    public static function getNewCoreDatabase()
    {
        $coreDatabase = new ArkPDO(self::getConfig()->getCorePdoConfig());
        $coreDatabase->connect();
        return $coreDatabase;
    }

    /**
     * @param string|string[] $row
     * @param string $dstCharset utf8 or so
     * @param string|null $srcCharset gbk or so
     */
    public static function switchCharset(&$row, $dstCharset, $srcCharset = null)
    {
        if ($dstCharset == null) {
            return;
        }
        if (is_array($row)) {
            array_walk(
                $row,
                function (&$item, /** @noinspection PhpUnusedParameterInspection */
                          $key) use ($srcCharset, $dstCharset) {
                    $item = mb_convert_encoding($item, $dstCharset, $srcCharset);
                }
            );
        } else {
            $row = mb_convert_encoding($row, $dstCharset, $srcCharset);
        }
    }

    /**
     * @param $inp
     * @return array|mixed
     */
    public static function escapeForMySQL($inp)
    {
        if (is_array($inp)) {
            return array_map([__CLASS__, __METHOD__], $inp);
        }

        if ($inp === true) return 1;
        if ($inp === false) return 0;
        if ($inp === null) return "NULL";
        if ($inp === "") return "''";

        if (!empty($inp) && is_string($inp)) {
            $x = str_replace(array('\\', "\0", "\n", "\r", "'", '"', "\x1a"), array('\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'), $inp);
            return "'{$x}'";
        }

        return $inp;
    }
}