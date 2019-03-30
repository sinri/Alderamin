<?php
/**
 * Created by PhpStorm.
 * User: sinri
 * Date: 2019-03-30
 * Time: 00:43
 */

namespace sinri\Alderamin\core\unit;


use Exception;
use sinri\Alderamin\core\Alderamin;
use sinri\Alderamin\core\model\ReportModel;
use sinri\ark\core\ArkLogger;

class ReportAgent
{
    /**
     * @var ArkLogger
     */
    protected $logger;
    /**
     * @var ReportUnit
     */
    protected $reportBuilder;

    /**
     * ReportAgent constructor.
     * @param ArkLogger|null $logger
     */
    public function __construct($logger = null)
    {
        if ($logger === null)
            $this->logger = ArkLogger::makeSilentLogger();
        else
            $this->logger = $logger;
    }

    /**
     * @param ArkLogger $logger
     */
    public function setLogger(ArkLogger $logger)
    {
        $this->logger = $logger;
    }

    public function dealReportById($reportId)
    {
        try {
            $reportRow = (new ReportModel())->selectRow(['report_id' => $reportId]);
            if (empty($reportRow)) {
                throw new Exception("Cannot fetch report with id " . json_encode($reportId));
            }

            if ($reportRow['status'] !== ReportModel::STATUS_ENQUEUED) {
                throw new Exception("Report is not enqueued now, id " . json_encode($reportId));
            }

            $reportCode = $reportRow['report_code'];
            //$parameters = empty($reportRow['parameters']) ? [] : json_decode($reportRow['parameters'], true);

            $this->reportBuilder = self::reportBuilderFactory($reportCode);
            $this->reportBuilder->loadReportById($reportId);

            $dequeue = (new ReportModel())->update(
                [
                    'report_id' => $reportId,
                    'status' => ReportModel::STATUS_ENQUEUED,
                ],
                [
                    'status' => ReportModel::STATUS_RUNNING,
                    'execute_time' => ReportModel::now(),
                    'pid' => getmypid(),
                ]
            );
            if (empty($dequeue)) {
                throw new \Exception("Cannot start a report request, status is not enqueued or cannot make it running");
            }

            $done = $this->reportBuilder->run();
            $feedback = $this->reportBuilder->getFeedback();

            $afx = null;
            if ($done) {
                $afx = (new ReportModel())->update(
                    [
                        'report_id' => $reportId,
                        'status' => ReportModel::STATUS_RUNNING,
                    ],
                    [
                        'status' => ReportModel::STATUS_DONE,
                        'feedback' => $feedback,
                        'finish_time' => ReportModel::now(),
                    ]
                );
                $this->logger->notice("Building Report Done, " . $feedback, ["report_id" => $reportId, "report_code" => $reportCode, "afx" => $afx]);
            } else {
                $afx = (new ReportModel())->update(
                    [
                        'report_id' => $reportId,
                        'status' => ReportModel::STATUS_RUNNING,
                    ],
                    [
                        'status' => ReportModel::STATUS_ERROR,
                        'feedback' => $feedback,
                        'finish_time' => ReportModel::now(),
                    ]
                );
                $this->logger->error("Building Report Error, " . $feedback, ["report_id" => $reportId, "report_code" => $reportCode, "afx" => $afx]);
            }
        } catch (\Exception $exception) {
            $this->logger->error(
                __METHOD__ . '@' . __LINE__ . ' ' . $exception->getMessage(),
                [
                    'report_id' => $reportId,
                    "exception" => $exception,
                ]
            );
        }

    }

    /**
     * @param string $reportCode
     * @return ReportUnit
     */
    public static function reportBuilderFactory($reportCode)
    {
        $reportBuilderClassName = Alderamin::getConfig()->getUnitStoreNamespace() . "\\report\\{$reportCode}";
        return new $reportBuilderClassName();
    }
}