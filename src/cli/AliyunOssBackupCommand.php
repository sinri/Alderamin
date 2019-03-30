<?php
/**
 * Created by PhpStorm.
 * User: sinri
 * Date: 2019-03-30
 * Time: 17:41
 */

namespace sinri\Alderamin\cli;


use OSS\Core\OssException;
use OSS\OssClient;
use sinri\Alderamin\core\Alderamin;
use sinri\Alderamin\core\configuration\AliyunOssConfig;
use sinri\Alderamin\core\model\ReportModel;
use sinri\Alderamin\core\unit\ReportUnit;
use sinri\ark\cli\ArkCliProgram;

abstract class AliyunOssBackupCommand extends ArkCliProgram
{
    /**
     * @var AliyunOssConfig
     */
    private $ossConfig;
    /**
     * @var OssClient
     */
    private $ossClient;

    /**
     * PolarisToOssCommand constructor.
     * @throws OssException
     */
    public function __construct()
    {
        parent::__construct();

        $this->logger = Alderamin::getLogger("PolarisToOss");
        $this->ossConfig = $this->defineAliyunOssConfig();
        $this->ossClient = $this->getOssClient();
        $this->logger->info(__CLASS__ . " Initialized");
    }

    /**
     * @return AliyunOssConfig
     */
    abstract public function defineAliyunOssConfig();

    /**
     * @return OssClient
     * @throws \OSS\Core\OssException
     */
    public function getOssClient()
    {
        $accessKeyId = $this->ossConfig->getAkId();
        $accessKeySecret = $this->ossConfig->getAkSecret();
        $endpoint = $this->ossConfig->getEndpoint();

        return new OssClient($accessKeyId, $accessKeySecret, $endpoint);
    }

    public function actionDefault()
    {
        parent::actionDefault();
    }

    public function actionSendPendingReportToOss()
    {
        $this->logger->info("actionSendPendingReportToOss go");

        $model = (new ReportModel());

        $rows = $model->selectRowsWithSort(
            [
                'status' => ReportModel::STATUS_DONE,
                'archive_status' => ReportModel::ARCHIVE_STATUS_PENDING,
            ],
            'finish_time asc, report_id asc',
            1,
            0
        );
        if (empty($rows)) {
            $this->logger->info("好闲啊");
            return;
        }
        $this->logger->info("Got rows", ['rows' => $rows]);

        $row = $rows[0];
        $report_id = $row['report_id'];

        $afx = $model->update(
            ['report_id' => $report_id, 'archive_status' => ReportModel::ARCHIVE_STATUS_PENDING],
            ['archive_status' => ReportModel::ARCHIVE_STATUS_SENDING]
        );
        if (empty($afx)) {
            $this->logger->error("Cannot update report archive status to SENDING, die", ['report_id' => $report_id]);
            return;
        }
        $this->logger->info("Updated Report Archive Status", ['report_id' => $report_id, 'afx' => $afx]);

        try {
            $excelFile = ReportUnit::getExcelStorage() . DIRECTORY_SEPARATOR . $report_id . ".xlsx";
            $this->logger->info("Ready to upload", ['report_id' => $report_id, 'file' => $excelFile]);
            $uploaded = $this->uploadFileToOss($excelFile, "polaris_report/" . $report_id . ".xlsx");
            if (!$uploaded) throw new \Exception("Upload Error for Report " . $report_id);

            $this->logger->notice("Report Excel Result Upload Over", ['report_id' => $report_id, 'file' => $excelFile]);

            $afx = $model->update(
                ['report_id' => $report_id, 'archive_status' => ReportModel::ARCHIVE_STATUS_SENDING],
                ['archive_status' => ReportModel::ARCHIVE_STATUS_DONE]
            );
            $this->logger->info("Updated Report Archive Status to DONE", ['report_id' => $report_id, 'afx' => $afx]);

        } catch (\Exception $e) {
            $this->logger->error("Emmm, error! " . $e->getMessage(), ['exception' => $e]);

            $afx = $model->update(
                ['report_id' => $report_id, 'archive_status' => ReportModel::ARCHIVE_STATUS_SENDING],
                ['archive_status' => ReportModel::ARCHIVE_STATUS_ERROR]
            );
            $this->logger->info("Updated Report Archive Status to ERROR", ['report_id' => $report_id, 'afx' => $afx]);

        }

    }

    private function uploadFileToOss($localFilePath, $targetOssObject)
    {
        try {
            $this->ossClient->uploadFile($this->ossConfig->getBucket(), $targetOssObject, $localFilePath);
            return true;
        } catch (\Exception $e) {
            $this->logger->error(__METHOD__ . '@' . __LINE__ . " 不知为什么上传失败了: " . $e->getMessage(), ['exception' => $e]);
            return false;
        }
    }
}