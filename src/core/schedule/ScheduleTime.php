<?php
/**
 * Created by PhpStorm.
 * User: sinri
 * Date: 2019-03-30
 * Time: 00:39
 */

namespace sinri\Alderamin\core\schedule;


use sinri\ark\core\ArkHelper;

class ScheduleTime
{
    const KEY_DAY = "DAY";
    const KEY_WEEKDAY = "WEEKDAY";
    const KEY_MONTH = "MONTH";
    const KEY_HOUR = "HOUR";
    const KEY_MINUTE = "MINUTE";

    protected $fields = [];

    public function __construct()
    {
        $this->setMinute(-1)
            ->setHour(-1)
            ->setMonth("*")
            ->setDay("*")
            ->setWeekday("*");
    }

    /**
     * @param string $weekday
     * @return ScheduleTime
     */
    public function setWeekday(string $weekday)
    {
        ArkHelper::writeIntoArray($this->fields, [self::KEY_WEEKDAY], $weekday);
        return $this;
    }

    /**
     * @param string $day
     * @return ScheduleTime
     */
    public function setDay(string $day)
    {
        ArkHelper::writeIntoArray($this->fields, [self::KEY_DAY], $day);
        return $this;
    }

    /**
     * @param string $month
     * @return ScheduleTime
     */
    public function setMonth(string $month)
    {
        ArkHelper::writeIntoArray($this->fields, [self::KEY_MONTH], $month);
        return $this;
    }

    /**
     * @param string $hour
     * @return ScheduleTime
     */
    public function setHour(string $hour)
    {
        ArkHelper::writeIntoArray($this->fields, [self::KEY_HOUR], $hour);
        return $this;
    }

    /**
     * @param string $minute
     * @return ScheduleTime
     */
    public function setMinute(string $minute)
    {
        ArkHelper::writeIntoArray($this->fields, [self::KEY_MINUTE], $minute);
        return $this;
    }

    public static function currentTime()
    {
        $dayInMonth = date('j');// 1-31
        $weekday = date('w');//0-6
        $month = date('n');//1-12
        $hour = date('G');//0-23
        $minute = intval(date('i'), 10);//0-59

        $current = (new ScheduleTime())
            ->setDay($dayInMonth)
            ->setWeekday($weekday)
            ->setMonth($month)
            ->setHour($hour)
            ->setMinute($minute);
        return $current;
    }

    /**
     * @return string
     */
    public function getDay(): string
    {
        return ArkHelper::readTarget($this->fields, [self::KEY_DAY]);
    }

    /**
     * @return string
     */
    public function getWeekday(): string
    {
        return ArkHelper::readTarget($this->fields, [self::KEY_WEEKDAY]);
    }

    /**
     * @return string
     */
    public function getMonth(): string
    {
        return ArkHelper::readTarget($this->fields, [self::KEY_MONTH]);
    }

    /**
     * @return string
     */
    public function getHour(): string
    {
        return ArkHelper::readTarget($this->fields, [self::KEY_HOUR]);
    }

    /**
     * @return string
     */
    public function getMinute(): string
    {
        return ArkHelper::readTarget($this->fields, [self::KEY_MINUTE]);
    }
}