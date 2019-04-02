<?php
/**
 * Created by PhpStorm.
 * User: sinri
 * Date: 2019-03-31
 * Time: 23:00
 */

namespace sinri\Alderamin\test\permission;


use sinri\Alderamin\core\unit\ReportPermission;

class DevelopPermission extends ReportPermission
{
    public static function getInstance()
    {
        return new self("DevelopPermission", "Develop Permission", "For Developers");
    }
}