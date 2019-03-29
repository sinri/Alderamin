<?php
/**
 * Created by PhpStorm.
 * User: sinri
 * Date: 2019-03-30
 * Time: 00:29
 */

namespace sinri\Alderamin\core\unit;


use Box\Spout\Common\Helper\GlobalFunctionsHelper;
use Box\Spout\Writer\XLSX\Writer;
use Exception;
use Psr\Log\LogLevel;
use sinri\Alderamin\core\Alderamin;
use sinri\Alderamin\core\excel\ExcelMeta;
use sinri\Alderamin\core\excel\ExcelSheetMeta;
use sinri\Alderamin\core\model\LockModel;
use sinri\Alderamin\core\model\ReportAttributeModel;
use sinri\Alderamin\core\model\ReportModel;
use sinri\ark\core\ArkLogger;
use sinri\ark\email\ArkSMTPMailer;

abstract class ReportUnit extends BaseUnit
{
    /**
     * @var string
     */
    protected $storageDirectory;

    /**
     * @var int
     */
    protected $reportId;
    /**
     * @var string
     */
    protected $reportTitle;
    /**
     * @var ExcelMeta
     */
    protected $excelMeta;
    /**
     * @var array
     */
    protected $attributes;

    protected $excelFilePath;
    protected $logFilePath;
    /**
     * @var string
     */
    protected $feedback;

    /**
     * PolarisReportBuilder constructor.
     * @param int $reportId
     * @throws Exception
     */
    public function __construct($reportId)
    {
        $this->logger = ArkLogger::makeSilentLogger();

        $this->excelMeta = new ExcelMeta();

        if ($reportId) {
            $this->loadReportById($reportId);
            $this->prepareStorage();
        }
    }

    /**
     * @param $reportId
     * @throws Exception
     */
    protected function loadReportById($reportId)
    {
        $reportRow = (new ReportModel())->selectRow(['report_id' => $reportId]);
        if (empty($reportRow)) {
            throw new Exception("Cannot fetch report with id " . json_encode($reportId));
        }

        if ($reportRow['status'] !== ReportModel::STATUS_ENQUEUED) {
            throw new Exception("Report is not enqueued now, id " . json_encode($reportId));
        }

        $this->reportId = $reportId;
        $this->reportTitle = $reportRow['report_title'];
        $this->parameters = empty($reportRow['parameters']) ? [] : json_decode($reportRow['parameters'], true);
        $this->parameters["reportId"] = $this->reportId;
        if ($this->getCode() != $reportRow['report_code']) {
            throw new Exception("Report Code does not match the builder");
        }

        $this->logger = Alderamin::getLogger("build-" . intval($reportId, 10));
        $this->logFilePath = Alderamin::readConfig(['log', 'path'], __DIR__ . '/../log') . DIRECTORY_SEPARATOR . "log-" . "build_" . intval($reportId, 10) . "-" . date('Y-m-d') . ".log";

        $attributeRows = (new ReportAttributeModel())->selectRows(['report_id' => $reportId], null, null, ["key", "value", "type"]);
        $this->attributes = [];
        if (!empty($attributeRows)) foreach ($attributeRows as $attributeRow) {
            if ($attributeRow['type'] == ReportAttributeModel::TYPE_SCALAR) {
                $this->attributes[$attributeRow['key']] = $attributeRow['value'];
            } elseif ($attributeRow['type'] == ReportAttributeModel::TYPE_ARRAY) {
                if (!isset($this->attributes[$attributeRow['key']])) {
                    $this->attributes[$attributeRow['key']] = [];
                }
                $this->attributes[$attributeRow['key']][] = $attributeRow['value'];
            }
        }
    }

    /**
     * Set up the storage directory of generated CSV files
     * It relies on the polaris config
     * @throws Exception
     */
    protected function prepareStorage()
    {
        $store = Alderamin::readConfig(['core', 'report-store']);
        if (empty($store)) {
            $this->logger->error("REPORT STORE EMPTY");
            throw new Exception("CSV Store Not Configured");
        }
        $taskKey = uniqid($this->reportId . "-");

        $dir = $store . DIRECTORY_SEPARATOR . $taskKey;

        mkdir($dir, 0777, true);

        if (!file_exists($dir)) {
            $this->logger->error("Cannot ensure report store: " . $dir);
        }

        $this->logger->notice(__FUNCTION__ . '@' . __LINE__ . " Ready, report key is " . $taskKey);

        $this->storageDirectory = $dir;
    }

    /**
     * @return array
     */
    abstract public function getParameterDefinitions(): array;

    /**
     * @return ReportCategory
     */
    abstract public function getReportCategory(): ReportCategory;

    /**
     * 在这里放权限，空的数组的话说明没有权限限制，有的话就是或的关系
     * @return string[]
     */
    abstract public function getReportPermissions(): array;

    /**
     * @param $name
     * @param null $folder
     * @param string $type
     * @return false|array
     * @throws Exception
     */
    public function getSheetMetaFromPolarisStore($name, $folder = null, $type = "report")
    {
        if ($folder === null) {
            $folder = $this->getCode();
        }

        $sqlPath = Alderamin::readConfig(['core', 'sql-store'], '/dummy');
        $sqlPath .= DIRECTORY_SEPARATOR . $type;
        $sqlPath .= DIRECTORY_SEPARATOR . $folder;
        $sqlPath .= DIRECTORY_SEPARATOR . $name . ".json";

        if (!file_exists($sqlPath)) {
            $error = "Cannot find json file: " . $sqlPath;
            $this->logger->error($error);
            throw new \Exception($error);
        }

        $template = file_get_contents($sqlPath);

        return json_decode($template, true);
    }

    /**
     * @return string
     */
    public function getFeedback(): string
    {
        return $this->feedback;
    }

    /**
     * Update the content of feedback,
     * and return the final result.
     *
     * @return bool
     */
    public final function run(): bool
    {
        try {
            $this->logger->notice("Register Shutdown Handler...");
            $logger = $this->logger;
            register_shutdown_function(function () use ($logger) {
                $error = error_get_last();
                if ($error["type"] == E_ERROR) {
                    $logger->error("Shutdown with error!", $error);
                }
            });

            $this->logger->notice(__METHOD__ . '@' . __LINE__ . " Start Build Report " . $this->getName(), ["report_id" => $this->reportId, "report_code" => $this->getCode()]);

            $this->logger->info("PARAMETERS", $this->parameters);
            $this->feedback = "愿上主的加护与此报表同在";

            $this->logger->notice("---- Fetch Meta ----");
            $this->runFetchMeta();
            $this->logger->notice("---- Prepare Data ----");
            $this->runPrepareData();
            $this->logger->notice("---- Make Sheets ----");
            $this->runMakeSheets();
            $this->showMadeSheets();
            $this->logger->notice("---- Generate Excel ----");
            $this->runGenerateExcel();
            $this->logger->notice("---- Clean Temporary Data ----");
            $this->runCleanTemporaryData();
            $this->logger->notice("---- Free All Report Related Locks ----");
            $this->freeAllReportRelatedLocks();
            $this->logger->notice("---- Send Emails ----");
            $this->sendEmails();

            $this->logger->notice(__METHOD__ . '@' . __LINE__ . " Report " . $this->getName() . " Built.", ["report_id" => $this->reportId, "report_code" => $this->getCode()]);
            return true;
        } catch (\Exception $exception) {
            $this->logger->error(__METHOD__ . '@' . __LINE__ . " Report " . $this->getName() . " Building Met Exception: " . $exception->getMessage(), ['trace' => $exception->getTrace()]);
            $this->feedback = "Failed. " . $exception->getMessage();
            return false;
        }
    }

    abstract protected function runFetchMeta();

    abstract protected function runPrepareData();

    abstract protected function runMakeSheets();

    protected function showMadeSheets()
    {
        $sheets = glob($this->storageDirectory . DIRECTORY_SEPARATOR . '*.csv');
        $this->logger->notice("Seek the generated CSV files");
        if ($sheets) foreach ($sheets as $sheet) {
            $this->logger->notice("> " . $sheet);
        }
    }

    /**
     * @throws Exception
     */
    protected function runGenerateExcel()
    {
        $excelFile = self::getExcelStorage() . DIRECTORY_SEPARATOR . $this->reportId . ".xlsx";
        $this->logger->notice("Packaging into XLSX...");
        $this->mergeSheetsToExcel($this->excelMeta, $excelFile);
        if (file_exists($excelFile)) {
            $this->logger->notice("Excel File Packaged: " . $excelFile);
            $this->feedback = "Done. Excel Generated: " . $excelFile;
            $this->excelFilePath = $excelFile;
        } else {
            $this->feedback = "No Excel Generated.";
        }
    }

    /**
     * @return string
     * @throws Exception
     */
    public static function getExcelStorage()
    {
        $store = Alderamin::readConfig(['core', 'report-store']);
        if (empty($store)) {
            throw new Exception("Excel Store Not Configured");
        }
        $taskKey = "excel";
        $dir = $store . DIRECTORY_SEPARATOR . $taskKey;
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }

        if (!file_exists($dir)) {
            throw new Exception("Cannot ensure report store: " . $dir);
        }

        return $dir;
    }

    /**
     * @param ExcelMeta $excelMeta ["SHEET_NAME"=>["sheet_csv"=>"CSV_PATH","sheet_name"=>"XX"]]
     * @param string $targetFile
     * @throws \Box\Spout\Common\Exception\IOException
     * @throws \Box\Spout\Common\Exception\SpoutException
     * @throws \Box\Spout\Writer\Exception\InvalidSheetNameException
     * @throws \Box\Spout\Writer\Exception\WriterAlreadyOpenedException
     * @throws \Box\Spout\Writer\Exception\WriterNotOpenedException
     */
    public function mergeSheetsToExcel($excelMeta, $targetFile)
    {
        $sheetMetaList = $excelMeta->getSheetMetaList();
        if (empty($sheetMetaList)) {
            $this->logger->warning(__METHOD__ . " 没有发现可用的工作薄原始档，停止制作XLSX");
            return;
        }

        $writer = self::buildExcelWriter();
        $writer->setShouldUseInlineStrings(false); // will use shared strings
        //$writer->setTempFolder($customTempFolderPath);
        $writer->openToFile($targetFile);

        $isFirstSheet = true;
        foreach ($sheetMetaList as $sheetMeta) {
            if (!$isFirstSheet) {
                $sheet = $writer->addNewSheetAndMakeItCurrent();
            } else {
                $sheet = $writer->getCurrentSheet();
            }
            $sheet->setName($sheetMeta->getSheetName());
            $file = fopen($sheetMeta->getSheetCSV(), "r");
            while (true) {
                $row = fgetcsv($file);
                if (!$row) break;

                // Make the cells be numeric if they originally were
                foreach ($row as $key => $value) {
                    if (strlen($value) < 12 && is_numeric($value)) {
                        $row[$key] = $value * 1;
                    }
                }

                $writer->addRow($row);
            }
            fclose($file);
            $isFirstSheet = false;
        }

        $writer->close();
    }

    /**
     * @return Writer
     */
    protected static function buildExcelWriter()
    {
        $writer = new Writer();
        $writer->setGlobalFunctionsHelper(new GlobalFunctionsHelper());
        return $writer;
    }

    protected function runCleanTemporaryData()
    {
        // commonly do nothing, but you may need it.
        // note, it won't be executed if exception occurred before
        $this->logger->info("No Temporary Data to Clean");
    }

    /**
     * 释放掉所有本REPORT引发的锁。一般用于锁出错需要全身而退的时候。
     * 政策更新：默认将执行本清锁政策，如果需要残留锁，可以重载此方法。
     */
    protected function freeAllReportRelatedLocks()
    {
        $afx = (new LockModel())->delete(['lock_by_report_id' => $this->reportId]);
        $this->logger->notice(__METHOD__ . " executed", ['afx' => $afx]);
    }

    protected function sendEmails()
    {
        try {
            if (!isset($this->attributes['emails'])) {
                $this->logger->info("No emails provided in attributes");
                return false;
            }

            $attachments = [];
            if ($this->excelFilePath) $attachments[] = $this->excelFilePath;
            $attachments[] = $this->logFilePath;

            $emails = $this->attributes['emails'];
            if (!is_array($emails)) {
                $emails = [$emails];
            }

            $this->logger->info("Emails provided in attributes", ['emails' => $emails]);

            $mailer = new ArkSMTPMailer(Alderamin::readConfig(['email']));
            $mailer->prepare();
            foreach ($emails as $email) {
                $mailer->addReceiver($email);
            }

            $html = "<h1>Polaris Regular Report Delivery Service</h1>" .
                "<h2>" . $this->reportId . '-' . $this->reportTitle . "</h2>" .
                "<p>Completed on " . date('Y-m-d H:i:s') . "</p>";
            $html .= '<h3>Parameters</h3>';
            foreach ($this->parameters as $key => $value) {
                $html .= "<p>" . $key . " : " . json_encode($value) . "</p>";
            }
            $html .= '<h3>Attributes</h3>';
            foreach ($this->attributes as $key => $value) {
                if (is_array($value)) {
                    $html .= "<p>" . $key . " : " . implode(", ", $value) . "</p>";
                } else {
                    $html .= "<p>" . $key . " : " . $value . "</p>";
                }
            }

            if (!empty($attachments)) {
                foreach ($attachments as $attachment) {
                    $mailer->addAttachment($attachment);
                }
            }

            $sent = $mailer->setSubject($this->reportId . '-' . $this->reportTitle . " - Polaris")
                ->setHTMLBody($html)
                ->addCCAddress("ljni@leqee.com", "SingleDog")
                ->finallySend($sendingError);
            if ($sent) {
                $this->logger->notice("Mail Sent", ['sent' => $sent, 'error' => $sendingError, 'emails' => $emails]);
            }
            $this->logger->error("Sending Failed", ['sent' => $sent, 'error' => $sendingError, 'emails' => $emails]);
            return $sent;
        } catch (Exception $exception) {
            $this->logger->error(__METHOD__ . " Send Email Exception: " . $exception->getMessage(), ['exception' => $exception]);
            return false;
        }
    }

    /**
     * @param $sheetName
     * @param $partNames
     * @param bool $titleRowOption
     * @throws Exception
     */
    protected function simulateUnionAll($sheetName, $partNames, $titleRowOption = true)
    {
        foreach ($partNames as $partName) {
            $this->logger->notice(__FUNCTION__ . '@' . __LINE__ . " Begin to execute sheet part", ["sheet" => $sheetName, "part" => $partName]);
            $done = $this->makeSheet($sheetName . '-' . $partName, $sheetName, true, $titleRowOption);
            $this->logger->log(
                $done ? LogLevel::NOTICE : LogLevel::ERROR,
                __FUNCTION__ . '@' . __LINE__ . " Tried to read [$sheetName::$partName] and output",
                ["done" => $done, "sheet" => $sheetName, "part" => $partName]
            );
            // title only once
            $titleRowOption = false;
        }
    }

    /**
     * @param $templateName
     * @param $csvName
     * @param bool $isAppend
     * @param bool $titleRowOption
     * @param int $chunkSize
     * @return bool
     * @throws Exception
     */
    protected function makeSheet($templateName, $csvName, $isAppend = false, $titleRowOption = true, $chunkSize = 500)
    {
        $template = $this->getQueryTemplate($templateName);
        $done = $this->getReader()
            ->executeForRawResult($template)
            ->outputToCSV($this->storageDirectory . DIRECTORY_SEPARATOR . $csvName . ".csv", $titleRowOption, null, null, $isAppend, $chunkSize);
        return $done;
    }

    /**
     * Fetch the template from archive,
     * You can turn off the common replace option.
     *
     * @param string $name
     * @param bool $runCommonReplace
     * @param null|string $folder if null, `$this->reportName` would be used
     * @return false|string
     * @throws \Exception
     */
    public function getQueryTemplate($name, $runCommonReplace = true, $folder = null)
    {
        if ($folder === null) {
            $folder = $this->getCode();
        }
        return $this->fetchQueryTemplateFromSqlStore($name, $folder, "report", $runCommonReplace);
    }

    /**
     * @param string[] $sheetCodeList
     * @throws Exception
     */
    protected function createSimpleSheetsWithCodes($sheetCodeList)
    {
        $this->logger->notice(__METHOD__ . '@' . __LINE__ . " MAKE SHEET START");
        foreach ($sheetCodeList as $sheetCode) {
            $sheetMeta = new ExcelSheetMeta(
                $this->getCode(),
                $sheetCode,
                $this->storageDirectory . DIRECTORY_SEPARATOR . $sheetCode . ".csv"
            );
            //$done = $this->makeSheet($this->reader, $sheetCode, $sheetCode, false, $sheetMeta->getTitleRow());
            $done = $this->makeSheet($sheetCode, $sheetCode, false, $sheetMeta->getTitleRow());
            if ($done) {
                $this->logger->notice("MAKE SHEET DONE", ["done" => $done]);
                $this->excelMeta->appendSheetMeta($sheetMeta);
            } else {
                $this->logger->error("MAKE SHEET FAILED", ["done" => $done]);
                //throw new \Exception(__METHOD__ . " Cannot Make Sheet with Code: " . $sheetCode . " Error: " . $this->getClusterDB()->getInstanceOfMySQLi()->error);
                throw new \Exception(__METHOD__ . " Cannot Make Sheet with Code: " . $sheetCode . " Error: " . $this->getReader()->getErrorString());
            }
        }
    }
}