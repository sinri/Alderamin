<?php
/**
 * Created by PhpStorm.
 * User: sinri
 * Date: 2019-03-29
 * Time: 23:28
 */

namespace sinri\Alderamin\core;


use Psr\Log\LogLevel;
use sinri\ark\core\ArkHelper;
use sinri\ark\core\ArkLogger;
use sinri\ark\database\mysqli\ArkMySQLi;
use sinri\ark\database\mysqli\ArkMySQLiConfig;
use sinri\ark\database\pdo\ArkPDO;
use sinri\ark\database\pdo\ArkPDOConfig;

class Alderamin
{
    const KEY_WRITE_NODE = "write_node";
    const KEY_READ_NODE = "read_node";
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
            self::$loggers[$aspect] = new ArkLogger(self::readConfig(['log', 'path'], __DIR__ . '/../log'), $aspect);
            self::$loggers[$aspect]->setShowProcessID(true);
            self::$loggers[$aspect]->setIgnoreLevel(self::readConfig(['log', 'level'], LogLevel::INFO));
        }
        return self::$loggers[$aspect];
    }

    /**
     * @param string|array $keyChain
     * @param null|mixed $default
     * @return mixed|null
     */
    public static function readConfig($keyChain, $default = null)
    {
        $config = [];
        require __DIR__ . '/../config/config.php';
        return ArkHelper::readTarget($config, $keyChain, $default);
    }

    public static function getDatabaseNode($name)
    {
        if (!isset(self::$databaseNodes[$name])) {
            $mysqli_config = new ArkMySQLiConfig([
                ArkMySQLiConfig::CONFIG_HOST => self::readConfig(['nodes', $name, 'host']),
                ArkMySQLiConfig::CONFIG_PORT => self::readConfig(['nodes', $name, 'port']),
                ArkMySQLiConfig::CONFIG_USERNAME => self::readConfig(['nodes', $name, 'username']),
                ArkMySQLiConfig::CONFIG_PASSWORD => self::readConfig(['nodes', $name, 'password']),
                ArkMySQLiConfig::CONFIG_CHARSET => self::readConfig(['nodes', $name, 'charset']),
            ]);
            $DB = new ArkMySQLi($mysqli_config);
            $DB->connect();

            self::$databaseNodes[$name] = $DB;
        }
        return self::$databaseNodes[$name];
    }

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
        $pdoConfig = new ArkPDOConfig(self::readConfig(['core', 'pdo']));
        $coreDatabase = new ArkPDO($pdoConfig);
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
                function (&$item, $key) use ($srcCharset, $dstCharset) {
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