<?php
/**
 * Created by PhpStorm.
 * User: sinri
 * Date: 2019-03-30
 * Time: 00:52
 */

namespace sinri\Alderamin\core\schedule;


abstract class BaseScheduleFactory
{
    /**
     * @return ScheduleJob[]
     */
    abstract public function generateSchedules();
}