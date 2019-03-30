<?php
/**
 * Created by PhpStorm.
 * User: sinri
 * Date: 2019-03-30
 * Time: 00:22
 */

namespace sinri\Alderamin\core\model;


use sinri\Alderamin\core\Alderamin;
use sinri\ark\database\model\ArkDatabaseTableModel;
use sinri\ark\database\pdo\ArkPDO;

class ReportModel extends ArkDatabaseTableModel
{
    const STATUS_APPLIED = "APPLIED";
    const STATUS_ENQUEUED = "ENQUEUED";
    const STATUS_RUNNING = "RUNNING";
    const STATUS_DONE = "DONE";
    const STATUS_ERROR = "ERROR";
    const STATUS_CANCELLED = "CANCELLED";

    const ARCHIVE_STATUS_PENDING = "PENDING";
    const ARCHIVE_STATUS_SENDING = "SENDING";
    const ARCHIVE_STATUS_DONE = "DONE";
    const ARCHIVE_STATUS_ERROR = "ERROR";

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
        return "report";
    }
}