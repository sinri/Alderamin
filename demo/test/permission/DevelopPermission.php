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
    const CODE = "DevelopPermission";
    const TITLE = "Develop Permission";

    public static function getInstance()
    {
        return new self(self::CODE, self::TITLE, "For Developers");
    }
}