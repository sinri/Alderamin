<?php
/**
 * Created by PhpStorm.
 * User: sinri
 * Date: 2019-03-30
 * Time: 00:40
 */

namespace sinri\Alderamin\core\schedule;


use sinri\Alderamin\core\Alderamin;
use sinri\Alderamin\core\model\ReportAttributeModel;
use sinri\Alderamin\core\model\ReportModel;
use sinri\Alderamin\core\unit\ReportAgent;
use sinri\ark\core\ArkHelper;

class ScheduleJob extends ScheduleTime
{
    /**
     * @var string
     */
    protected $reportCode;
    /**
     * @var array
     */
    protected $parameters;
    /**
     * @var string[]
     */
    protected $emails;
    /**
     * @var string
     */
    protected $description;

    public function __construct($minute, $hour, $day = "*", $month = "*", $weekday = "*")
    {
        parent::__construct();
        $this->setMinute($minute)
            ->setHour($hour)
            ->setMonth($month)
            ->setDay($day)
            ->setWeekday($weekday);
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @param string $description
     * @return ScheduleJob
     */
    public function setDescription(string $description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return string[]
     */
    public function getEmails(): array
    {
        return $this->emails;
    }

    /**
     * @param string[] $emails
     * @return ScheduleJob
     */
    public function setEmails(array $emails)
    {
        $this->emails = $emails;
        return $this;
    }

    /**
     * @param ScheduleTime $current
     * @return bool
     */
    public function isMatch($current)
    {
        foreach ($this->fields as $key => $request) {
            if ($request === '*') continue;
            if ($request === ArkHelper::readTarget($current->fields, [$key])) continue;
            return false;
        }
        return true;
    }

    /**
     * @return string
     */
    public function getReportCode(): string
    {
        return $this->reportCode;
    }

    /**
     * @param string $reportCode
     * @return ScheduleJob
     */
    public function setReportCode(string $reportCode)
    {
        $this->reportCode = $reportCode;
        return $this;
    }

    /**
     * @return array
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * @param array $parameters
     * @return ScheduleJob
     */
    public function setParameters(array $parameters)
    {
        $this->parameters = $parameters;
        return $this;
    }

    /**
     * @throws \Exception
     */
    public function execute()
    {
        self::getLogger()->info("Start to executed Schedule for report " . $this->reportCode, [
            'parameters' => $this->parameters,
        ]);

        $reportBuilder = ReportAgent::reportBuilderFactory($this->reportCode, 0);

        $reportId = (new ReportModel())->insert(
            [
                "report_code" => $this->reportCode,
                "report_title" => "定时报表(" . date('Y-m-d H:i:s') . ")-" . $reportBuilder->getName(),
                "parameters" => json_encode($this->parameters),
                "apply_user" => "oc-robot",
                "status" => ReportModel::STATUS_ENQUEUED,
                "priority" => 10,
                "apply_time" => ReportModel::now(),
                "enqueue_time" => ReportModel::now(),
                "archive_status" => ReportModel::ARCHIVE_STATUS_PENDING,
            ]
        );
        ArkHelper::quickNotEmptyAssert("Cannot create new report request", $reportId);

        $batchInsertData = [];
        $this->parameters['emails'] = $this->emails;
        $this->parameters['description'] = $this->description;
        $this->parameters['is_schedule'] = 'YES';
        foreach ($this->parameters as $key => $value) {
            if (is_scalar($value)) {
                $batchInsertData[] = [
                    "report_id" => $reportId,
                    "key" => $key,
                    "value" => $value,
                    "type" => ReportAttributeModel::TYPE_SCALAR,
                ];
            } elseif (is_array($value)) {
                foreach ($value as $item) {
                    $batchInsertData[] = [
                        "report_id" => $reportId,
                        "key" => $key,
                        "value" => $item,
                        "type" => ReportAttributeModel::TYPE_ARRAY,
                    ];
                }
            }
            // else do nothing
        }
        $attrIds = (new ReportAttributeModel())->batchInsert($batchInsertData);

        self::getLogger()->info("Executed Schedule for report " . $this->reportCode, [
            'report_id' => $reportId,
            "attr_ids" => $attrIds,
            'parameters' => $this->parameters,
        ]);
    }

    /**
     * @return \sinri\ark\core\ArkLogger
     */
    public static function getLogger()
    {
        return Alderamin::getLogger("schedule");
    }
}