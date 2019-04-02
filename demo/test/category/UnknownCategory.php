<?php
/**
 * Created by PhpStorm.
 * User: sinri
 * Date: 2019-03-31
 * Time: 22:52
 */

namespace sinri\Alderamin\test\category;


use sinri\Alderamin\core\unit\ReportCategory;

class UnknownCategory extends ReportCategory
{
    public static function getInstance()
    {
        return new self("UnknownCategory", "Unknown-Category");
    }
}