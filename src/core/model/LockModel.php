<?php
/**
 * Created by PhpStorm.
 * User: sinri
 * Date: 2019-03-30
 * Time: 00:19
 */

namespace sinri\Alderamin\core\model;


use sinri\Alderamin\core\Alderamin;
use sinri\ark\database\model\ArkDatabaseTableModel;
use sinri\ark\database\pdo\ArkPDO;

class LockModel extends ArkDatabaseTableModel
{
    /**
     * Insert a record into table
     * Based on the UK `lock_code`
     * If cannot obtain exception thrown
     *
     * @param string $lockCode
     * @param int $byReportId
     * @param string $byReportCode
     * @return bool|string
     * @throws \Exception
     */
    public static function obtainLock($lockCode, $byReportId, $byReportCode)
    {
        $result = (new self())->insert([
            'lock_code' => $lockCode,
            'lock_by_report_id' => $byReportId,
            'lock_by_report_code' => $byReportCode,
            'lock_time' => self::now(),
        ]);
        if (empty($result)) {
            throw new \Exception("Cannot obtain Lock for code [{$lockCode}] for #{$byReportCode} {$byReportCode}.");
        }
        return $result;
    }

    /**
     * Delete the record by the UK `lock_code`
     * Do nothing when fail but return the result
     *
     * @param string $lockCode
     * @return int|false
     */
    public static function releaseLock($lockCode)
    {
        $result = (new self())->delete(['lock_code' => $lockCode]);
        return $result;
    }

    /**
     * @return ArkPDO
     * @throws \Exception
     */
    public function db()
    {
        return Alderamin::getSharedCoreDatabase();
    }

    /**
     * @return string
     */
    protected function mappingTableName()
    {
        return "lock";
    }
}