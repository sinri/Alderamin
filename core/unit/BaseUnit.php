<?php
/**
 * Created by PhpStorm.
 * User: sinri
 * Date: 2019-03-30
 * Time: 00:02
 */

namespace sinri\Alderamin\core\unit;


use Exception;
use sinri\Alderamin\core\Alderamin;
use sinri\Alderamin\core\database\DatabaseReader;
use sinri\Alderamin\core\database\DatabaseWriter;
use sinri\Alderamin\core\model\LockModel;
use sinri\ark\core\ArkHelper;
use sinri\ark\database\mysqli\ArkMySQLi;
use sinri\ark\database\pdo\ArkPDO;

abstract class BaseUnit
{
    const PARAM_TYPE_DATE = "DATE";
    const PARAM_TYPE_DATETIME = "DATETIME";
    const PARAM_TYPE_STRING = "STRING";
    const PARAM_TYPE_DECIMAL = "DECIMAL";
    const PARAM_TYPE_ARRAY_OF_STRING = "ARRAY_OF_STRING";
    const PARAM_TYPE_ARRAY_OF_DECIMAL = "ARRAY_OF_DECIMAL";

    /**
     * @var \sinri\ark\core\ArkLogger
     */
    protected $logger;
    /**
     * @var array
     */
    protected $parameters = [];
    /**
     * @var DatabaseReader
     */
    private $alderaminReader;
    private $readDatabaseNode;
    /**
     * @var DatabaseWriter
     */
    private $alderaminWriter;
    private $writeDatabaseNode;

    /**
     * @return string
     */
    abstract public function getName(): string;

    abstract public function run(): bool;

    /**
     * Delete the record by the UK `lock_code`
     * Do nothing when fail but return the result
     *
     * @param string $lockCode
     * @return int|false
     */
    public function releaseLock($lockCode)
    {
        $result = LockModel::releaseLock($lockCode);
        $this->logger->info("运行了释放锁 {$lockCode} 的方法", ["lock_code" => $lockCode, "result" => $result]);
        return $result;
    }

    /**
     * Fetch the template from archive,
     * You can turn off the common replace option.
     *
     * @param string $name
     * @param null|string $folder if null, `$this->reportName` would be used
     * @param string $type
     * @param bool $runCommonReplace
     * @return false|string
     * @throws \Exception
     */
    protected function fetchQueryTemplateFromSqlStore($name, $folder, $type, $runCommonReplace = true)
    {
        $sqlPath = Alderamin::readConfig(['core', 'sql-store'], '/dummy');
        $sqlPath .= DIRECTORY_SEPARATOR . $type;
        $sqlPath .= DIRECTORY_SEPARATOR . $folder;
        $sqlPath .= DIRECTORY_SEPARATOR . $name . ".sql";

        if (!file_exists($sqlPath)) {
            $error = "Cannot find sql file: " . $sqlPath;
            $this->logger->error($error);
            throw new \Exception($error);
        }

        $template = file_get_contents($sqlPath);

        if ($runCommonReplace) {
            $this->parseTemplate($template);
        }

        return $template;
    }

    /**
     * Replace the common parameters
     *
     * #{NAME} -> 'X'
     * ${NAME} -> X
     * #[#NAME] -> ('X','X')
     * #[$NAME] -> (X,X)
     *
     * E.G.
     * #{startTime} -> 'XX'
     * #{endTime} -> 'XX'
     * #[#partyIds] -> (X,X)
     * #[#shopIds] -> (X,X)
     * ${requestId} -> X
     *
     * @param string $template
     * @return string
     * @throws Exception
     */
    public function parseTemplate(&$template)
    {
        if ($template === false || $template === null) {
            throw new Exception(__METHOD__ . '@' . __LINE__ . " Template is not available");
        }

        $p = $this->parameters;

        $template = preg_replace_callback('/#\{([A-Za-z0-9-_]+)\}/', function ($matches) use ($p) {
            $v = ArkHelper::readTarget($p, [$matches[1]]);
            if ($v === null) throw new Exception("Parameter Error For " . $matches[1]);
            return ArkPDO::dryQuote($v);
        }, $template);
        $template = preg_replace_callback('/\$\{([A-Za-z0-9-_]+)\}/', function ($matches) use ($p) {
            $v = ArkHelper::readTarget($p, [$matches[1]]);
            if ($v === null) throw new Exception("Parameter Error For " . $matches[1]);
            return $v;
        }, $template);
        $template = preg_replace_callback('/#\[#([A-Za-z0-9-_]+)\]/', function ($matches) use ($p) {
            $v = ArkHelper::readTarget($p, [$matches[1]], []);
            $s = [];
            if (!empty($v)) foreach ($v as $item) {
                $s[] = ArkPDO::dryQuote($item);
            }
            return '(' . implode(',', $s) . ')';
        }, $template);
        $template = preg_replace_callback('/#\[\$([A-Za-z0-9-_]+)\]/', function ($matches) use ($p) {
            $v = ArkHelper::readTarget($p, [$matches[1]], []);
            $v = array_filter($v, function ($item) {
                return is_numeric($item);
            });
            return '(' . implode(',', $v) . ')';
        }, $template);


        return $template;
    }

    /**
     * @param string[] $partNames
     * @param string $targetSchema
     * @param string $targetTable
     * @param string $description
     * @param null|callable $sqlFetchCallback
     * @throws Exception
     */
    protected function queryAndInsertIntoTargetTable($partNames, $targetSchema, $targetTable, $description, $sqlFetchCallback = null)
    {
        $this->logger->notice(__FUNCTION__ . " Ready to run. $description");
        foreach ($partNames as $partName) {
            $written = false;
            try {
                if (!is_callable($sqlFetchCallback)) {
                    $sql = $this->getQueryTemplate($partName);
                } else {
                    $sql = call_user_func_array($sqlFetchCallback, []);
                }

                // 注意！这里的SELECT出来的字段必须要（通过AS等手段）保证字段名和目标表的字段名一致。这是妄图以约定减小开发量的设计。
                $this->getReader()
                    ->executeForRawResult($sql)
                    ->insertIntoTable($this->getWriter(), $targetSchema, $targetTable);
                $written = true;
                $this->logger->notice("$description [$partName] done", ["written" => $written]);
            } catch (\Exception $exception) {
                $this->logger->error("$description [$partName] failed. Exception: " . $exception->getMessage(), ['written' => $written, "exception" => $exception]);
                throw new \Exception(__FUNCTION__ . '@' . __LINE__ . " $description [$partName] failed");
            }
        }
    }

    abstract public function getQueryTemplate($name, $runCommonReplace = true, $folder = null);

    /**
     * @return DatabaseReader
     * @throws Exception
     */
    protected final function getReader()
    {
        if (!$this->alderaminReader) {
            $this->alderaminReader = new DatabaseReader($this->getReadDatabaseNode(), $this->getLogger());
        }
        return $this->alderaminReader;
    }

    /**
     * @return ArkMySQLi
     */
    protected function getReadDatabaseNode()
    {
        if (!$this->readDatabaseNode) {
            $this->readDatabaseNode = Alderamin::getDatabaseNode(Alderamin::KEY_READ_NODE);
        }
        return $this->readDatabaseNode;
    }

    /**
     * @return \sinri\ark\core\ArkLogger
     */
    public final function getLogger(): \sinri\ark\core\ArkLogger
    {
        return $this->logger;
    }

    /**
     * @param \sinri\ark\core\ArkLogger $logger
     */
    public final function setLogger(\sinri\ark\core\ArkLogger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @return DatabaseWriter
     * @throws Exception
     */
    protected final function getWriter()
    {
        if (!$this->alderaminWriter) {
            $this->alderaminWriter = new DatabaseWriter($this->getWriteDatabaseNode(), $this->getLogger());
        }
        return $this->alderaminWriter;
    }

    /**
     * @return ArkMySQLi
     */
    protected function getWriteDatabaseNode()
    {
        if (!$this->writeDatabaseNode) {
            $this->writeDatabaseNode = Alderamin::getDatabaseNode(Alderamin::KEY_WRITE_NODE);
        }
        return $this->writeDatabaseNode;
    }

    /**
     * @param string[] $partNames
     * @param string $targetSchema
     * @param string $targetTable
     * @param string $description
     * @param null|callable $sqlFetchCallback
     * @throws Exception
     */
    protected function queryAndReplaceIntoTargetTable($partNames, $targetSchema, $targetTable, $description, $sqlFetchCallback = null)
    {
        $this->logger->notice(__FUNCTION__ . " Ready to run. $description");
        foreach ($partNames as $partName) {
            $written = false;
            try {
                if (!is_callable($sqlFetchCallback)) {
                    $sql = $this->getQueryTemplate($partName);
                } else {
                    $sql = call_user_func_array($sqlFetchCallback, []);
                }

                // 注意！这里的SELECT出来的字段必须要（通过AS等手段）保证字段名和目标表的字段名一致。这是妄图以约定减小开发量的设计。
                $this->getReader()
                    ->executeForRawResult($sql)
                    ->replaceIntoTable($this->getWriter(), $targetSchema, $targetTable);
                $written = true;
                $this->logger->notice("$description [$partName] done", ["written" => $written]);
            } catch (\Exception $exception) {
                $this->logger->error("$description [$partName] failed. Exception: " . $exception->getMessage(), ['written' => $written, "exception" => $exception]);
                throw new \Exception(__FUNCTION__ . '@' . __LINE__ . " $description [$partName] failed");
            }
        }
    }

    /**
     * 尝试`repeatTries`次以获取锁`lockCode`
     * 失败后等待`sleepSeconds`秒然后卷土重来
     * 耐心耗尽之后，在乌江边自刎而死
     * 如果还带着虞姬记得一起杀掉（builder嵌套了锁的时候半路死掉的话，可以用freeAllReportRelatedLocks方法自己解锁）
     *
     * 如果repeatTries和sleepSeconds都用默认值1的话（比如忽略这两个参数）就和obtainLockForOnce方法的效果一样了
     *
     * @param string $lockCode
     * @param int $byReportId
     * @param int $repeatTries
     * @param int $sleepSeconds
     * @return bool|string
     * @throws Exception
     */
    protected function obtainLockWithRepeatTries($lockCode, $byReportId, $repeatTries = 1, $sleepSeconds = 1)
    {
        ArkHelper::quickNotEmptyAssert("Repeat Tries and Sleep Seconds should be at least 1.", $repeatTries >= 1, $sleepSeconds >= 1);
        $byReportCode = $this->getCode();
        for ($i = 1; $i <= $repeatTries; $i++) {
            try {
                $lockId = $this->obtainLockForOnce($lockCode, $byReportId);
                $this->logger->info("第{$i}次妄图抢占锁 [{$lockCode}] 成功~", [
                    "lock_id" => $lockId,
                    "lock_code" => $lockCode,
                    "by_report_id" => $byReportId,
                    "by_runner_code" => $byReportCode,
                ]);
                return $lockId;
            } catch (\Exception $exception) {
                $this->logger->warning("第{$i}次妄图抢占锁 [{$lockCode}] 然而并没有成功。", [
                    "lock_code" => $lockCode,
                    "by_report_id" => $byReportId,
                    "by_runner_code" => $byReportCode,
                ]);
            }
            if ($i < $repeatTries) sleep($sleepSeconds);
        }
        $this->logger->error("经过{$repeatTries}次虚无的争夺，还是没有抢占到锁，气数已尽，大势已去，告辞。", [
            "lock_code" => $lockCode,
            "by_report_id" => $byReportId,
            "by_runner_code" => $byReportCode,
        ]);
        throw new \Exception("Cannot obtain Polaris Lock for code [{$lockCode}] for #{$byReportId} {$byReportCode} after tried for  {$repeatTries} times.");
    }

    /**
     * @return string
     */
    abstract public function getCode(): string;

    /**
     * Insert a record into table
     * Based on the UK `lock_code`
     * If cannot obtain, an exception thrown
     *
     * @param string $lockCode
     * @param int $byReportId
     * @return bool|string
     * @throws \Exception
     */
    protected function obtainLockForOnce($lockCode, $byReportId)
    {
        return LockModel::obtainLock($lockCode, $byReportId, $this->getCode());
    }
}