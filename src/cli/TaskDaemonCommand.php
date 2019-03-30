<?php
/**
 * Created by PhpStorm.
 * User: sinri
 * Date: 2019-03-30
 * Time: 21:51
 */

namespace sinri\Alderamin\cli;


use sinri\ark\cli\ArkCliProgram;

class TaskDaemonCommand extends ArkCliProgram
{
    protected $logger;
    protected $reportModel;
    protected $killModel;

    public function __construct()
    {
        parent::__construct();
        $this->logger = \sinri\Alderamin\core\Alderamin::getLogger("TaskDaemon");
        $this->reportModel = new \sinri\Alderamin\core\model\ReportModel();
        $this->killModel = new \sinri\Alderamin\core\model\KillRequestModel();
    }

    public function actionDefault()
    {
        $this->actionExecuteKillRequests();

        $this->actionCheckRunningJobs();

        $this->actionDealNextJob();
    }

    public function actionExecuteKillRequests()
    {
        try {
            $rows = $this->killModel->selectRows(['status' => \sinri\Alderamin\core\model\KillRequestModel::STATUS_PENDING]);
            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $report_id = $row['report_id'];

                    $report = $this->reportModel->selectRow(['report_id' => $report_id]);
                    if (empty($report)) {
                        $this->killModel->whenKillerFailed($row['id'], 'Report Not Exists');
                        continue;
                    }
                    if ($report['status'] != \sinri\Alderamin\core\model\ReportModel::STATUS_RUNNING) {
                        $this->killModel->whenKillerFailed($row['id'], 'Report Not Running');
                        continue;
                    }
                    exec("ps aux|grep cronRunner.php|grep " . intval($report['pid']) . "|grep -v grep", $output, $returnVar);
                    $this->logger->info("Check PID " . $report['pid'], ['output' => $output, 'return' => $returnVar]);
                    if (empty($output) || $returnVar != 0) {
                        $this->killModel->whenKillerFailed($row['id'], 'PID not found. RETURN ' . $returnVar);
                        continue;
                    }
                    exec("kill -9 " . intval($report['pid']), $output, $returnVar);
                    $this->logger->info("Kill PID " . $report['pid'], ['output' => $output, 'return' => $returnVar]);
                    $this->killModel->whenKillerDone($row['id'], "Killed. Return " . $returnVar);
                }
            }
        } catch (\Exception $exception) {
            $this->logger->error("Cron Killer Failed: " . $exception->getMessage(), ["exception" => $exception]);
        }
    }

    public function actionCheckRunningJobs()
    {
        // check running alive
        $runningTasks = $this->reportModel->selectRows([
            'status' => \sinri\Alderamin\core\model\ReportModel::STATUS_RUNNING
        ]);
        if (!empty($runningTasks)) {
            foreach ($runningTasks as $runningTask) {
                $report_id = $runningTask['report_id'];
                $pid = $runningTask['pid'];
                exec("ps " . escapeshellarg($pid), $output, $returnVar);
                if ($returnVar != 0) {
                    $this->logger->warning("Report {$report_id} with PID {$pid} Dismissed.");
                    // make it ERROR
                    $afx = (new \sinri\Alderamin\core\model\ReportModel())->update(
                        [
                            'status' => \sinri\Alderamin\core\model\ReportModel::STATUS_RUNNING,
                            "report_id" => $report_id,
                            "pid" => $pid,
                        ],
                        [
                            'status' => \sinri\Alderamin\core\model\ReportModel::STATUS_ERROR,
                            "finish_time" => \sinri\Alderamin\core\model\ReportModel::now(),
                            "feedback" => "Report {$report_id} with PID {$pid} Dismissed.",
                        ]
                    );
                    if ($afx) {
                        $this->logger->info("Report {$report_id} Shutdown Done", ["afx" => $afx]);
                    } else {
                        $this->logger->error("Report {$report_id} Shutdown Failed", ["afx" => $afx]);
                    }
                }
            }

            if (count($runningTasks) >= \sinri\Alderamin\core\Alderamin::getConfig()->getCronMax()) {
                $this->logger->error("Cron Busy");
                exit;
            }
        }
    }

    public function actionDealNextJob()
    {
        try {
            // seek one to deal
            $checkPage = 1;
            $checkPageSize = 100;
            while (true) {
                $rows = $this->reportModel->selectRowsWithSort(
                    [
                        "status" => \sinri\Alderamin\core\model\ReportModel::STATUS_ENQUEUED,
                    ],
                    "priority desc, apply_time asc",
                    $checkPageSize,
                    ($checkPage - 1) * $checkPageSize
                );
                if (empty($rows)) {
                    $this->logger->info("No enqueued report requests.");
                    return;
                }
                foreach ($rows as $row) {
                    // check if same exclusive_hash already running
                    if (
                        $this->reportModel->selectRowsForCount([
                            'status' => \sinri\Alderamin\core\model\ReportModel::STATUS_RUNNING,
                            'exclusive_hash' => $row['exclusive_hash'],
                        ]) > 0
                    ) {
                        $this->logger->warning("Checked Report [{$row['report_id']}], Code [{$row['report_code']}], Conflict Found with exclusive hash", ["exclusive_hash" => $row['exclusive_hash']]);
                        continue;
                    }

                    $this->logger->notice("Send report {$row['report_id']} to agent");
                    (new \sinri\Alderamin\core\unit\ReportAgent($this->logger))->dealReportById($row['report_id']);

                    return;
                }
                $checkPage++;
            }
        } catch (\Exception $exception) {
            $this->logger->error("Cron Failed: " . $exception->getMessage(), ["exception" => $exception]);
        }
    }

}