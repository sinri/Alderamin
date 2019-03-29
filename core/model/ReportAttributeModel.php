<?php
/**
 * Created by PhpStorm.
 * User: sinri
 * Date: 2019-03-30
 * Time: 00:21
 */

namespace sinri\Alderamin\core\model;


use sinri\Alderamin\core\Alderamin;
use sinri\ark\database\model\ArkDatabaseTableModel;
use sinri\ark\database\pdo\ArkPDO;

class ReportAttributeModel extends ArkDatabaseTableModel
{

    const TYPE_SCALAR = "SCALAR";
    const TYPE_ARRAY = "ARRAY";

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
        return "report_attribute";
    }


}