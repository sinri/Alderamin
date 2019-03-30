<?php
/**
 * Created by PhpStorm.
 * User: sinri
 * Date: 2019-03-29
 * Time: 23:42
 */

namespace sinri\Alderamin\core\database;


use Exception;
use sinri\Alderamin\core\Alderamin;
use sinri\ark\core\ArkLogger;
use sinri\ark\database\mysqli\ArkMySQLi;

class DatabaseWriter
{
    const STRATEGY_INSERT = "INSERT";
    const STRATEGY_REPLACE = "REPLACE";

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

    public function beginTransaction()
    {
        // This function doesn't work with non transactional table types (like MyISAM or ISAM).
        $this->arkMySQLi->getInstanceOfMySQLi()->autocommit(false);
    }

    public function endTransaction($metException = false)
    {
        if ($metException) {
            $this->arkMySQLi->getInstanceOfMySQLi()->rollback();
        } else {
            $this->arkMySQLi->getInstanceOfMySQLi()->commit();
        }
        //$this->arkMySQLi->getInstanceOfMySQLi()->close();
    }

    /**
     * @param string s$sql
     * @param string $description
     * @param array $detail
     * @return mixed
     * @throws Exception
     */
    public function insert($sql, $description, &$detail = null)
    {
        if ($detail === null) $detail = [];
        $done = $this->query($sql, $description, $detail);
        $detail['done'] = $done;
        if ($done === false) {
            $detail['errno'] = $this->arkMySQLi->getInstanceOfMySQLi()->errno;
            $detail['error'] = $this->arkMySQLi->getInstanceOfMySQLi()->error;
            $this->logger->error("Could not modify." . $description, $detail);
            throw new Exception("Cannot run sql to modify. " . $description . " Error [{$detail['errno']}] {$detail['error']}");
        }
        $detail['inserted_id'] = $this->arkMySQLi->getInstanceOfMySQLi()->insert_id;
        $this->logger->info("Inserted {$description}", $detail);
        return $this->arkMySQLi->getInstanceOfMySQLi()->insert_id;
    }

    /**
     * Run the query [DML]
     *
     * @param $sql
     * @param $description
     * @param array $detail
     * @return bool|\mysqli_result
     * @throws \Exception
     */
    public function query($sql, $description, &$detail = null)
    {
        if ($detail === null) $detail = [];
        $done = $this->arkMySQLi->getInstanceOfMySQLi()->query($sql);
        $detail['done'] = $done;
        if ($done) {
            $this->logger->info("Modified. " . $description, $detail);
        } else {
            $detail['errno'] = $this->arkMySQLi->getInstanceOfMySQLi()->errno;
            $detail['error'] = $this->arkMySQLi->getInstanceOfMySQLi()->error;
            $this->logger->error("Could not query." . $description, $detail);
            $this->logger->logInline($sql . PHP_EOL);
            throw new Exception("Cannot run sql to query. " . $description . " Error [{$detail['errno']}] {$detail['error']}");
        }
        //$this->logger->info("Queried {$description}", $detail);
        return $done;
    }

    /**
     * @param $sql
     * @param $description
     * @param array $detail
     * @return int
     * @throws Exception
     */
    public function modify($sql, $description, &$detail = null)
    {
        if ($detail === null) $detail = [];
        $done = $this->query($sql, $description, $detail);
        $detail['done'] = $done;
        if ($done === false) {
            $detail['errno'] = $this->arkMySQLi->getInstanceOfMySQLi()->errno;
            $detail['error'] = $this->arkMySQLi->getInstanceOfMySQLi()->error;
            $this->logger->error("Could not modify." . $description, $detail);
            throw new Exception("Cannot run sql to modify. " . $description . " Error [{$detail['errno']}] {$detail['error']}");
        }
        $detail['afx'] = $this->arkMySQLi->getInstanceOfMySQLi()->affected_rows;
        $this->logger->info("Modified {$description}", $detail);
        return $this->arkMySQLi->getInstanceOfMySQLi()->affected_rows;
    }

    /**
     * @param $schemaName
     * @param $procedureName
     * @param $parameters
     * @param $description
     * @param array $detail
     * @return bool|\mysqli_result
     * @throws Exception
     */
    public function callProcedure($schemaName, $procedureName, $parameters, $description, &$detail = null)
    {
        if ($detail === null) $detail = [];
        $sql = "call {$schemaName}.{$procedureName}(";
        $x = [];
        foreach ($parameters as $parameter) {
            $x[] = Alderamin::escapeForMySQL($parameter);
        }
        $sql .= implode(",", $x);
        $sql .= ");";

        $done = $this->query($sql, $description, $detail);
        $detail['done'] = $done;
        if ($done === false) {
            $detail['errno'] = $this->arkMySQLi->getInstanceOfMySQLi()->errno;
            $detail['error'] = $this->arkMySQLi->getInstanceOfMySQLi()->error;
            $this->logger->error("Could not execute." . $description, $detail);
            throw new Exception("Cannot run sql to execute. " . $description . " Error [{$detail['errno']}] {$detail['error']}");
        }
        $this->logger->info("Modified {$description}", $detail);
        return $done;
    }

    /**
     * @param $result
     * @param $schema
     * @param $table
     * @param int $chunkSize
     * @return bool
     * @throws Exception
     */
    public function insertIntoTable($result, $schema, $table, $chunkSize = 500)
    {
        return $this->writeIntoTable($result, self::STRATEGY_INSERT, $schema, $table, $chunkSize);
    }

    /**
     * @param \mysqli_result $result
     * @param $strategy
     * @param $schema
     * @param $table
     * @param int $chunkSize
     * @return bool
     * @throws Exception
     */
    protected function writeIntoTable($result, $strategy, $schema, $table, $chunkSize = 500)
    {
        $startTime = microtime(true);
        $components = [];

        $fields = array_column($result->fetch_fields(), 'name');

        $total = 0;

        while (true) {
            $row = $result->fetch_array(MYSQLI_NUM);
            $this->logger->debug(__METHOD__ . '@' . __LINE__ . " row fetched", ['row' => $row]);

            if ($row !== null) {
                $component = [];
                for ($i = 0; $i < count($row); $i++) {
                    $component[] = Alderamin::escapeForMySQL($row[$i]);
                }
                $components[] = "(" . implode(",", $component) . ")";

                if (count($components) >= $chunkSize) {
                    // flush
                    $this->flushComponents($components, $schema, $table, $fields, $strategy, $total);
                    $components = [];
                }
            } else {
                if (!empty($components)) {
                    $this->flushComponents($components, $schema, $table, $fields, $strategy, $total);
                }

                break;
            }
        }

        $this->logger->logInline(PHP_EOL);

        $endTime = microtime(true);
        $this->logger->info(__METHOD__ . '@' . __LINE__ . " given " . ($result->num_rows) . " rows written", ['time_cost' => ($endTime - $startTime)]);
        $result->close();
        return true;
    }

    /**
     * @param $components
     * @param $schema
     * @param $table
     * @param $fields
     * @param $strategy
     * @param int $total
     * @throws Exception
     */
    protected function flushComponents($components, $schema, $table, $fields, $strategy, &$total)
    {
        $fieldsSQL = "`" . implode("`,`", $fields) . "`";
        $presetWriteSqlHead = "{$strategy} INTO `{$schema}`.`{$table}` ({$fieldsSQL}) VALUES ";;

        $sql = $presetWriteSqlHead . implode(",", $components);

        $this->logger->debug("CHUNK SQL: " . $sql);
        $startTime = microtime(true);
        $inserted = $this->arkMySQLi->getInstanceOfMySQLi()->query($sql);
        $endTime = microtime(true);
        $this->logger->debug(__METHOD__ . '@' . __LINE__ . " write result", ["result" => $inserted, "cost_time" => ($endTime - $startTime)]);
        if (!$inserted) {
            $this->logger->error(__METHOD__ . " Cannot {$strategy} into table", [
                "schema" => $schema,
                "table" => $table,
                "inserted" => $inserted,
                "errno" => $this->arkMySQLi->getInstanceOfMySQLi()->errno,
                "error" => $this->arkMySQLi->getInstanceOfMySQLi()->error,
            ]);
            throw new \Exception(__METHOD__ . " Cannot {$strategy} into table");
        }

        $total += count($components);

        $this->logger->logInline("→" . $total);
    }

    /**
     * @param $result
     * @param $schema
     * @param $table
     * @param int $chunkSize
     * @return bool
     * @throws Exception
     */
    public function replaceIntoTable($result, $schema, $table, $chunkSize = 500)
    {
        return $this->writeIntoTable($result, self::STRATEGY_REPLACE, $schema, $table, $chunkSize);
    }

    /**
     * @param $rows
     * @param $schema
     * @param $table
     * @throws Exception
     */
    public function insertIntoTableFromDataSet($rows, $schema, $table)
    {
        $this->writeRowsFromDataSet($rows, $schema, $table, self::STRATEGY_INSERT);
    }

    /**
     * @param string[][] $rows
     * @param $schema
     * @param $table
     * @param $strategy
     * @param int $chunkSize
     * @throws Exception
     */
    protected function writeRowsFromDataSet($rows, $schema, $table, $strategy, $chunkSize = 500)
    {
        $fields = array_keys($rows[0]);
        $startTime = microtime(true);
        $chunks = array_chunk($rows, $chunkSize);

        $total = 0;

        foreach ($chunks as $chunk) {
            $components = [];
            foreach ($chunk as $row) {
                $component = [];
                for ($i = 0; $i < count($row); $i++) {
                    $component[] = Alderamin::escapeForMySQL($row[$i]);
                }
                $components[] = "(" . implode(",", $component) . ")";
            }
            $this->flushComponents($components, $schema, $table, $fields, $strategy, $total);
        }
        $this->logger->logInline(PHP_EOL);
        $endTime = microtime(true);
        $this->logger->info(__METHOD__ . '@' . __LINE__ . " given " . count($rows) . " rows written", ['time_cost' => ($endTime - $startTime)]);
    }

    /**
     * @param $rows
     * @param $schema
     * @param $table
     * @throws Exception
     */
    public function replaceIntoTableFromDataSet($rows, $schema, $table)
    {
        $this->writeRowsFromDataSet($rows, $schema, $table, self::STRATEGY_REPLACE);
    }

    /**
     * @param $schema
     * @param $table
     * @param $ddl
     * @param bool $shouldTryDropFirst
     * @throws Exception
     */
    public function ensureTableExists($schema, $table, $ddl, $shouldTryDropFirst = true)
    {
        $this->arkMySQLi->getInstanceOfMySQLi()->query("use " . $schema . ";");

        $checkExists = $this->arkMySQLi->getInstanceOfMySQLi()->query("show tables in `{$schema}` like '{$table}';");
        if ($checkExists !== false && $checkExists->num_rows > 0) {
            $this->logger->info(__METHOD__ . " Table `{$schema}`.`{$table}` Seems Existed", [
                "num_rows" => $checkExists->num_rows,
                "fetched" => $checkExists->fetch_all(),
            ]);
            return;
        }

        if ($shouldTryDropFirst) {
            $sql = "drop table if exists `{$schema}`.`{$table}`;";
            $dropped = $this->arkMySQLi->getInstanceOfMySQLi()->query($sql);
            if ($dropped === false) {
                $this->logger->error(__METHOD__ . " Cannot drop table", [
                    "schema" => $schema,
                    "table" => $table,
                    "sql" => $sql,
                    "errno" => $this->arkMySQLi->getInstanceOfMySQLi()->errno,
                    "error" => $this->arkMySQLi->getInstanceOfMySQLi()->error,
                ]);
                throw new \Exception(__METHOD__ . " Cannot drop table `{$schema}`.`{$table}`");
            }
        }

        $created = $this->arkMySQLi->getInstanceOfMySQLi()->query($ddl);
        if ($created === false) {
            $this->logger->error(__METHOD__ . " Cannot create table", [
                "schema" => $schema,
                "table" => $table,
                "ddl" => $ddl,
                "errno" => $this->arkMySQLi->getInstanceOfMySQLi()->errno,
                "error" => $this->arkMySQLi->getInstanceOfMySQLi()->error,
            ]);
            throw new \Exception(__METHOD__ . " Cannot create table `{$schema}`.`{$table}`");
        }
    }

    /**
     * @param $schema
     * @param $table
     * @param string $description
     * @return bool|\mysqli_result
     * @throws Exception
     */
    public function dropTable($schema, $table, $description = '')
    {
        $this->arkMySQLi->getInstanceOfMySQLi()->query("use " . $schema . ";");
        $sql = "drop table if exists `{$schema}`.`{$table}`;";
        $dropped = $this->arkMySQLi->getInstanceOfMySQLi()->query($sql);
        if ($dropped) {
            $this->logger->info(__METHOD__ . '@' . __LINE__ . "$description Drop `{$schema}`.`{$table}` Done", ['dropped' => $dropped]);
        } else {
            $this->logger->warning(__METHOD__ . '@' . __LINE__ . "$description Drop `{$schema}`.`{$table}` Failed", ['dropped' => $dropped]);
            throw new \Exception("$description Drop `{$schema}`.`{$table}` Failed");
        }
        return $dropped;
    }

    /**
     * @param $schema
     * @param $table
     * @param string $description
     * @return bool|\mysqli_result
     * @throws Exception
     */
    public function truncateTable($schema, $table, $description = '')
    {
        $this->arkMySQLi->getInstanceOfMySQLi()->query("use " . $schema . ";");
        $sql = "TRUNCATE TABLE `{$schema}`.`{$table}`;";
        $truncated = $this->arkMySQLi->getInstanceOfMySQLi()->query($sql);
        if ($truncated) {
            $this->logger->info(__METHOD__ . '@' . __LINE__ . "$description Truncate `{$schema}`.`{$table}` Done", ['truncated' => $truncated]);
        } else {
            $this->logger->warning(__METHOD__ . '@' . __LINE__ . "$description Truncate `{$schema}`.`{$table}` Failed", ['truncated' => $truncated]);
            throw new \Exception("$description Truncate `{$schema}`.`{$table}` Failed");
        }
        return $truncated;
    }

    /**
     * @return string
     */
    public function getErrorString(): string
    {
        return "#" . $this->arkMySQLi->getInstanceOfMySQLi()->errno . ":" . $this->arkMySQLi->getInstanceOfMySQLi()->error;
    }
}