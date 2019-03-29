<?php
/**
 * Created by PhpStorm.
 * User: sinri
 * Date: 2019-03-30
 * Time: 00:50
 */

namespace sinri\Alderamin\cli;


use sinri\Alderamin\core\schedule\ScheduleJob;
use sinri\ark\cli\ArkCliProgram;

class ScheduleCommand extends ArkCliProgram
{
// 每十分钟跑一次
    // 跑完之后发邮件

    public function actionDefault()
    {
        $current = ScheduleJob::currentTime();

        $schedules = $this->getSchedules();

        ScheduleJob::getLogger()->info("Check with " . count($schedules) . " schedules");

        foreach ($schedules as $schedule) {
            try {
                if (!$schedule->isMatch($current)) {
                    continue;
                }
                // execute
                $schedule->execute();
            } catch (\Exception $e) {
                ScheduleJob::getLogger()->error("Exception met for handling schedule: " . $e->getMessage(), [
                    'exception' => $e,
                ]);
            }
        }
    }

    /**
     * @return ScheduleJob[]
     */
    protected function getSchedules()
    {
        $schedules = [];

        $scheduleClassList = [
            //SomeScheduleFactory::class,
        ];

        foreach ($scheduleClassList as $scheduleClass) {
            $class = new $scheduleClass();
            $jobs = call_user_func_array([$class, 'generateSchedules'], []);
            $schedules = array_merge($schedules, $jobs);
        }

        return $schedules;
    }
}