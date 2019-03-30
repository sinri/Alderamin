<?php
/**
 * Created by PhpStorm.
 * User: sinri
 * Date: 2019-03-29
 * Time: 23:42
 */

namespace sinri\Alderamin\core\database;


use sinri\ark\core\ArkLogger;
use sinri\ark\database\mysqli\ArkMySQLi;

class DatabaseReader
{
    /**
     * @var ArkMySQLi
     */
    protected $arkMySQLi;
    /**
     * @var ArkLogger
     */
    protected $logger;

    /**
     * ClusterReader constructor.
     * @param ArkMySQLi $cluster
     * @param ArkLogger $logger
     */
    public function __construct($cluster, $logger)
    {
        $this->arkMySQLi = $cluster;
        $this->logger = $logger;
    }

    /**
     * @param string $sql
     * @param int $resultType
     * @return array|bool
     * @throws \Exception
     */
    public function readRows($sql, $resultType = MYSQLI_ASSOC)
    {
        $result = $this->executeForRawResult($sql);
        if (!$result) {
            $this->logger->error(__METHOD__ . ' Cannot fetch raw result for sql below, and false would be returned.');
            $this->logger->logInline($sql . PHP_EOL);
            return false;
        }
        return $result->readRows($resultType);
    }

    /**
     * Run query with MYSQLI_USE_RESULT
     * @param string $sql
     * @return DataResult|false
     * @throws \Exception
     */
    public function executeForRawResult($sql)
    {
        $md5 = md5($sql);
        $this->logger->debug(__METHOD__ . "SQL: " . $sql, ["sql_md5" => $md5]);

        $db = $this->arkMySQLi->getInstanceOfMySQLi();
        $startTime = microtime(true);
        $result = $db->query($sql, MYSQLI_USE_RESULT);
        $endTime = microtime(true);
        $this->logger->info(__METHOD__ . '@' . __LINE__ . " executed", ["time_cost" => ($endTime - $startTime), "sql_md5" => $md5]);

        if (!$result) {
            $this->logger->error(__METHOD__ . '@' . __LINE__ . " query failed", ['result' => $result, 'errno' => $db->errno, 'error' => $db->error, "sql_md5" => $md5]);
            throw new \Exception(__METHOD__ . " Fetch Failed. " . $this->getErrorString());
        }
        return new DataResult($result, $this->logger);
    }

    /**
     * @return string
     */
    public function getErrorString(): string
    {
        return "#" . $this->arkMySQLi->getInstanceOfMySQLi()->errno . ":" . $this->arkMySQLi->getInstanceOfMySQLi()->error;
    }

    /**
     * @param string $sql
     * @param string|int $key
     * @param int $resultType
     * @return array|bool
     * @throws \Exception
     */
    public function readColumns($sql, $key, $resultType = MYSQLI_ASSOC)
    {
        $result = $this->executeForRawResult($sql);
        if (!$result) {
            $this->logger->error(__METHOD__ . ' Cannot fetch raw result for sql below, and false would be returned.');
            $this->logger->logInline($sql . PHP_EOL);
            return false;
        }
        return $result->readColumns($key, $resultType);
    }

    /**
     * @param string $sql
     * @param int $resultType
     * @return array|bool
     * @throws \Exception
     */
    public function readRow($sql, $resultType = MYSQLI_ASSOC)
    {
        $result = $this->executeForRawResult($sql);
        if (!$result) {
            $this->logger->error(__METHOD__ . ' Cannot fetch raw result for sql below, and false would be returned.');
            $this->logger->logInline($sql . PHP_EOL);
            return false;
        }
        return $result->readRow($resultType);
    }

    /**
     * @param string $sql
     * @return array|bool
     * @throws \Exception
     */
    public function readCell($sql)
    {
        $result = $this->executeForRawResult($sql);
        if (!$result) {
            $this->logger->error(__METHOD__ . ' Cannot fetch raw result for sql below, and false would be returned.');
            $this->logger->logInline($sql . PHP_EOL);
            return false;
        }
        return $result->readCell();
    }
}