<?php
/**
 * Created by PhpStorm.
 * User: sinri
 * Date: 2019-03-30
 * Time: 00:20
 */

namespace sinri\Alderamin\core\model;


use sinri\Alderamin\core\Alderamin;
use sinri\ark\database\model\ArkDatabaseTableModel;
use sinri\ark\database\pdo\ArkPDO;

class KillRequestModel extends ArkDatabaseTableModel
{

    const STATUS_PENDING = "PENDING";
    const STATUS_DONE = "DONE";
    const STATUS_FAILED = "FAILED";

    /**
     * @return ArkPDO
     * @throws \Exception
     */
    public function db()
    {
        return Alderamin::getSharedCoreDatabase();
    }

    public function whenKillerFailed($id, $feedback)
    {
        return $this->update(
            ['id' => $id, 'status' => self::STATUS_PENDING],
            [
                'status' => self::STATUS_FAILED,
                'feedback' => $feedback,
                'execute_time' => self::now()
            ]
        );
    }

    public function whenKillerDone($id, $feedback)
    {
        return $this->update(
            ['id' => $id, 'status' => self::STATUS_PENDING],
            [
                'status' => self::STATUS_DONE,
                'feedback' => $feedback,
                'execute_time' => self::now()
            ]
        );
    }

    /**
     * @return string
     */
    protected function mappingTableName()
    {
        return "kill_request";
    }
}