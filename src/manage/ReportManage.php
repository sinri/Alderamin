<?php
/**
 * Created by PhpStorm.
 * User: sinri
 * Date: 2019-03-30
 * Time: 15:32
 */

namespace sinri\Alderamin\manage;


use sinri\Alderamin\core\Alderamin;
use sinri\Alderamin\core\model\AttributeReportViewModel;
use sinri\Alderamin\core\model\KillRequestModel;
use sinri\Alderamin\core\model\LockModel;
use sinri\Alderamin\core\model\ReportAttributeModel;
use sinri\Alderamin\core\model\ReportModel;
use sinri\Alderamin\core\unit\ReportAgent;
use sinri\Alderamin\core\unit\ReportPermission;
use sinri\Alderamin\core\unit\ReportUnit;
use sinri\ark\core\ArkHelper;
use sinri\ark\core\ArkLogger;
use sinri\ark\database\model\ArkSQLCondition;

class ReportManage
{
    /**
     * @var ArkLogger
     */
    protected $logger;
    /**
     * @var AttributeReportViewModel
     */
    protected $attributeReportViewModel;
    /**
     * @var ReportModel
     */
    protected $reportModel;
    /**
     * @var KillRequestModel
     */
    protected $killRequestModel;
    /**
     * @var ReportAttributeModel
     */
    protected $reportAttributeModel;
    /**
     * @var LockModel
     */
    protected $lockModel;

    public function __construct()
    {
        $this->logger = ArkLogger::makeSilentLogger();
        $this->killRequestModel = new KillRequestModel();
        $this->reportModel = new ReportModel();
        $this->attributeReportViewModel = new AttributeReportViewModel();
        $this->reportAttributeModel = new ReportAttributeModel();
        $this->lockModel = new LockModel();
    }

    /**
     * @return ArkLogger
     */
    public function getLogger(): ArkLogger
    {
        return $this->logger;
    }

    /**
     * @param ArkLogger $logger
     */
    public function setLogger(ArkLogger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param string[] $reportCodeOptions
     * @param null|string $applyUser
     * @param null|string[]|string $statusOptions
     * @param null|string $keyword
     * @param null|array $attribute
     * @param int $page
     * @param int $pageSize
     * @param int $total
     * @return array
     */
    public function getReportList($reportCodeOptions, $applyUser = null, $statusOptions = null, $keyword = null, $attribute = null, $page = 1, $pageSize = 10, &$total = 0)
    {
        $conditions = [];
        if (!empty($applyUser)) $conditions['apply_user'] = $applyUser;
        if (!empty($statusOptions)) $conditions['status'] = $statusOptions;
        $conditions['report_code'] = $reportCodeOptions;// this is not optional
        if (!empty($keyword)) $conditions['report_title'] = ArkSQLCondition::makeStringContainsText("report_title", $keyword);

        if (!empty($attribute)) {
            $conditions['key'] = $attribute['key'];
            $conditions['value'] = $attribute['value'];
            $conditions['attribute_type'] = $attribute['type'];
        }

        $this->logger->debug(__METHOD__ . '@' . __LINE__ . " debug 0", ['conditions' => $conditions]);

        $listInView = $this->attributeReportViewModel->selectRowsForFieldsWithSort(["report_id"], $conditions, "report_id desc", $pageSize, ($page - 1) * $pageSize, null, ['report_id']);
        $total = $this->attributeReportViewModel->selectRowsForCount($conditions, 'report_id', true);

        $this->logger->info(__METHOD__ . '@' . __LINE__ . " debug 1", ["list in view count" => count($listInView), 'total' => $total]);

        $list = [];
        foreach ($listInView as $item) {
            $this->logger->info(__METHOD__ . '@' . __LINE__ . " debug 2", ['item' => $item]);
            $row = $this->reportModel->selectRow(['report_id' => $item['report_id']]);

            $row['kill_request_list'] = $this->killRequestModel->selectRowsWithSort(['report_id' => $item['report_id']], "request_time desc");

            $list[] = $row;
        }

        return $list;
    }

    /**
     * @param string $reportCode
     * @param string $reportTitle
     * @param string $applyUser
     * @param array $parameters
     * @param int $priority
     * @return bool|string
     * @throws \Exception
     */
    public function newReport($reportCode, $reportTitle, $applyUser, $parameters = [], $priority = 10)
    {
        ArkHelper::quickNotEmptyAssert("Field Empty", $reportCode, is_array($parameters), $applyUser, is_numeric($priority));

        $reportId = $this->reportModel->insert(
            [
                "report_code" => $reportCode,
                "report_title" => $reportTitle,
                "parameters" => json_encode($parameters),
                "apply_user" => $applyUser,
                "status" => ReportModel::STATUS_ENQUEUED,
                "priority" => $priority,
                "apply_time" => ReportModel::now(),
                'enqueue_time' => ReportModel::now(),
                "archive_status" => ReportModel::ARCHIVE_STATUS_PENDING,
            ]
        );
        ArkHelper::quickNotEmptyAssert("Cannot create new report request", $reportId);

        $batchInsertData = [];
        foreach ($parameters as $key => $value) {
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
        $attrIds = $this->reportAttributeModel->batchInsert($batchInsertData);
        $this->logger->debug(__METHOD__ . '@' . __LINE__, ['report_id' => $reportId, 'attr_ids' => $attrIds]);

        return $reportId;
    }

    /**
     * @param $reportId
     * @return boolean
     */
    public function enqueueReport($reportId)
    {
        $afx = $this->reportModel->update(
            ['report_id' => $reportId, "status" => ReportModel::STATUS_APPLIED],
            ['status' => ReportModel::STATUS_ENQUEUED, 'enqueue_time' => ReportModel::now()]
        );
        return !!$afx;
    }

    /**
     * @param $reportId
     * @return boolean
     * @throws \Exception
     */
    public function cancelReport($reportId)
    {
        $afx = $this->reportModel->update(
            ['report_id' => $reportId, "status" => ReportModel::STATUS_APPLIED],
            ['status' => ReportModel::STATUS_CANCELLED, 'enqueue_time' => ReportModel::now()]
        );
        return !!$afx;
    }

    /**
     * @param array $permissions
     * @return array|false
     */
    public function availableReportTypes($permissions)
    {
        $this->logger->debug(__METHOD__ . " 用户的权限", ['permissions' => $permissions]);

        $list = glob(__DIR__ . '/../../report/builders/*Builder.php');
        $reportTypeList = [];
        foreach ($list as $item) {
            preg_match('/([A-Za-z0-9]+)Builder\.php$/', $item, $matches);
            $reportCode = $matches[1];

            $reportBuilder = ReportAgent::reportBuilderFactory($reportCode, 0);

            $hasPermission = count($reportBuilder->getReportPermissions()) === 0;
            $this->logger->info(__METHOD__ . " 是否是公开报表", ['is_public' => $hasPermission, 'reportCode' => $reportCode, 'class' => $reportBuilder->getCode(), "need" => $reportBuilder->getReportPermissions()]);
            if (!$hasPermission) {
                $intersect = array_intersect($reportBuilder->getReportPermissions(), $permissions);
                $hasPermission = count($intersect) > 0;
                $this->logger->info(__METHOD__ . " 是否有权限交集", ['has_permission' => $hasPermission, 'intersect' => $intersect, 'reportCode' => $reportCode, 'class' => $reportBuilder->getCode(), 'need' => $reportBuilder->getReportPermissions()]);
            }
            if ($hasPermission) {
                $reportTypeList[] = [
                    "code" => $reportBuilder->getCode(),
                    "name" => $reportBuilder->getName(),
                    "parameters" => $reportBuilder->getParameterDefinitions(),
                    "category" => $reportBuilder->getReportCategory()->getCategoryMeta(),
                    "permissions" => $reportBuilder->getReportPermissions(),
                ];
            }
        }

        return $reportTypeList;
    }

    /**
     * @return ReportPermission[]
     */
    public function availableReportPermissions()
    {
        return ReportPermission::getPermissionList();
    }

    /**
     * @param null|int $reportId
     * @param null|int $lockCode
     * @param int $page
     * @param int $pageSize
     * @param int $total
     * @return array|bool
     */
    public function seeLocks($reportId = null, $lockCode = null, $page = 1, $pageSize = 20, &$total = 0)
    {
        $conditions = [];
        if ($reportId !== null) {
            $conditions['report_id'] = $reportId;
        }
        if ($lockCode !== null) {
            $conditions['lock_code'] = $lockCode;
        }

        $total = $this->lockModel->selectRowsForCount($conditions);
        $locks = $this->lockModel->selectRowsWithSort($conditions, "lock_id asc", $pageSize, $pageSize * ($page - 1));

        return $locks;
    }

    /**
     * @param int $reportId
     * @return boolean
     * @throws \Exception
     */
    public function unlockForDeadReport($reportId)
    {
        $reportRow = $this->reportModel->selectRow(['report_id' => $reportId]);
        ArkHelper::quickNotEmptyAssert("No such report", $reportRow);
        ArkHelper::quickNotEmptyAssert("Status is not ERROR", $reportRow['status'] == ReportModel::STATUS_ERROR);

        $afx = $this->lockModel->delete(['lock_by_report_id' => $reportId]);

        return !!$afx;
    }

    /**
     * @param int $reportId
     * @return bool|string
     * @throws \Exception
     */
    public function createKillReportRequest($reportId)
    {
        $reportRow = $this->reportModel->selectRow(['report_id' => $reportId]);
        ArkHelper::quickNotEmptyAssert("No such report", $reportRow);
        ArkHelper::quickNotEmptyAssert("Status is not RUNNING", $reportRow['status'] == ReportModel::STATUS_RUNNING);

        $requestId = $this->killRequestModel->insert([
            'report_id' => $reportRow['report_id'],
            'request_time' => KillRequestModel::now(),
            'status' => KillRequestModel::STATUS_PENDING,
        ]);

        return $requestId;
    }

    /**
     * @param null|string[] $statusOptions
     * @param int $page
     * @param int $pageSize
     * @param int $total
     * @return array|bool
     */
    public function getKillReportRequestList($statusOptions = null, $page = 1, $pageSize = 10, &$total = 0)
    {
        $conditions = [];
        if ($statusOptions != null) $conditions['status'] = $statusOptions;

        $list = $this->killRequestModel->selectRowsWithSort($conditions, 'request_time desc', $pageSize, ($page - 1) * $pageSize);
        $total = $this->killRequestModel->selectRowsForCount($conditions);

        return $list;
    }

    /**
     * @param $reportId
     * @param null $downloadName
     * @return string
     * @throws \Exception 404 for never there, 301 for moved away
     */
    public function getLocalExcelForDownload($reportId, &$downloadName = null)
    {
        $row = $this->reportModel->selectRow(['report_id' => $reportId]);
        if (empty($row)) {
            throw new \Exception("This report does not exist", 404);
        }

        $excelFile = ReportUnit::getExcelStorage() . DIRECTORY_SEPARATOR . $reportId . ".xlsx";
        if (!file_exists($excelFile)) {
            throw new \Exception("This file is not there", 301);
        }

        $downloadName = $reportId;
        if (!empty($row['report_title'])) {
            $downloadName .= "-" . $row['report_title'];
        } else {
            $downloadName .= "-" . $row['report_code'];
        }
        $downloadName .= "-" . $row['apply_user'];

        // $this->_getOutputHandler()->downloadFileIndirectly($excelFile, null, $downloadName);

        return $excelFile;
    }

    /**
     * @param $reportId
     * @return string
     * @throws \Exception
     */
    public function getLocalReportLogForDownload($reportId)
    {
        $base = Alderamin::getConfig()->getLogDirPath();
        $list = glob($base . DIRECTORY_SEPARATOR . 'log-build_' . $reportId . '-*');

        if (empty($list)) {
            throw new \Exception("No such file", 404);
        }

        // $this->_getOutputHandler()->downloadFileIndirectly($list[0], "text/plain", "report-builder-" . $reportId . ".log");

        return $list[0];
    }

}