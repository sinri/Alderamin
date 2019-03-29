<?php
/**
 * Created by PhpStorm.
 * User: sinri
 * Date: 2019-03-30
 * Time: 00:46
 */

require_once __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set("Asia/Shanghai");

echo "Now " . date('Y-m-d H:i:s') . PHP_EOL;

if (!\sinri\ark\core\ArkHelper::isCLI()) {
    exit;
}

$logger = \sinri\Alderamin\core\Alderamin::getLogger("cronRunner");
$reportModel = new \sinri\Alderamin\core\model\ReportModel();
$killModel = new \sinri\Alderamin\core\model\KillRequestModel();

try {
    $rows = $killModel->selectRows(['status' => \sinri\Alderamin\core\model\KillRequestModel::STATUS_PENDING]);
    if (!empty($rows)) {
        foreach ($rows as $row) {
            $report_id = $row['report_id'];

            $report = $reportModel->selectRow(['report_id' => $report_id]);
            if (empty($report)) {
                $killModel->whenKillerFailed($row['id'], 'Report Not Exists');
                continue;
            }
            if ($report['status'] != \sinri\Alderamin\core\model\ReportModel::STATUS_RUNNING) {
                $killModel->whenKillerFailed($row['id'], 'Report Not Running');
                continue;
            }
            exec("ps aux|grep cronRunner.php|grep " . intval($report['pid']) . "|grep -v grep", $output, $returnVar);
            $logger->info("Check PID " . $report['pid'], ['output' => $output, 'return' => $returnVar]);
            if (empty($output) || $returnVar != 0) {
                $killModel->whenKillerFailed($row['id'], 'PID not found. RETURN ' . $returnVar);
                continue;
            }
            exec("kill -9 " . intval($report['pid']), $output, $returnVar);
            $logger->info("Kill PID " . $report['pid'], ['output' => $output, 'return' => $returnVar]);
            $killModel->whenKillerDone($row['id'], "Killed. Return " . $returnVar);
        }
    }
} catch (Exception $exception) {
    $logger->error("Cron Killer Failed: " . $exception->getMessage(), ["exception" => $exception]);
}

try {
    // check running alive
    $runningTasks = $reportModel->selectRows([
        'status' => \sinri\Alderamin\core\model\ReportModel::STATUS_RUNNING
    ]);
    if (!empty($runningTasks)) {
        foreach ($runningTasks as $runningTask) {
            $report_id = $runningTask['report_id'];
            $pid = $runningTask['pid'];
            exec("ps " . escapeshellarg($pid), $output, $returnVar);
            if ($returnVar != 0) {
                $logger->warning("Report {$report_id} with PID {$pid} Dismissed.");
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
                    $logger->info("Report {$report_id} Shutdown Done", ["afx" => $afx]);
                } else {
                    $logger->error("Report {$report_id} Shutdown Failed", ["afx" => $afx]);
                }
            }
        }

        if (count($runningTasks) >= \sinri\Alderamin\core\Alderamin::readConfig(['polaris', 'cron-max'], 5)) {
            $logger->error("Cron Busy");
            exit;
        }
    }

//    if (
//        $reportModel->selectRowsForCount([
//            'status' => \sinri\polaris\model\PolarisReportModel::STATUS_RUNNING
//        ]) >= \sinri\polaris\utils\PolarisUtils::readConfig(['polaris', 'cron-max'], 5)
//    ) {
//        $logger->error("Cron Busy");
//        exit;
//    }

    // seek one to deal
    $rows = $reportModel->selectRowsWithSort(
        [
            "status" => \sinri\Alderamin\core\model\ReportModel::STATUS_ENQUEUED,
        ],
        "priority desc, apply_time asc",
        100,
        0
    );
    if (empty($rows)) {
        $logger->info("No enqueued report requests.");
        exit;
    }

    foreach ($rows as $row) {
        // check if same code already running
        $reportCode = $row['report_code'];
        if (
            $reportModel->selectRowsForCount([
                'status' => \sinri\Alderamin\core\model\ReportModel::STATUS_RUNNING,
                'report_code' => $row['report_code'],
            ]) > 0
        ) {
            $logger->warning("Checked Report [{$row['report_id']}], Code [{$row['report_code']}], Conflict Found");
            continue;
        }

        $logger->notice("Send report {$row['report_id']} to agent");
        (new \sinri\Alderamin\core\unit\ReportAgent($logger))->dealReportById($row['report_id']);

        break;
    }
} catch (Exception $exception) {
    $logger->error("Cron Failed: " . $exception->getMessage(), ["exception" => $exception]);
}