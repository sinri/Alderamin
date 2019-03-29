<?php
/**
 * Created by PhpStorm.
 * User: sinri
 * Date: 2019-03-30
 * Time: 00:20
 */

namespace sinri\Alderamin\core\model;


use sinri\ark\database\model\ArkDatabaseTableModel;
use sinri\ark\database\pdo\ArkPDO;

class AttributeReportViewModel extends ArkDatabaseTableModel
{

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
        return "attribute_report_view";
    }
}