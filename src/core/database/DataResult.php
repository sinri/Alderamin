<?php
/**
 * Created by PhpStorm.
 * User: sinri
 * Date: 2019-03-29
 * Time: 23:42
 */

namespace sinri\Alderamin\core\database;


use sinri\Alderamin\core\Alderamin;
use sinri\ark\core\ArkLogger;

class DataResult
{
    /**
     * @var \mysqli_result
     */
    protected $dataResult;
    /**
     * @var ArkLogger
     */
    protected $logger;

    /**
     * DataResult constructor.
     * @param \mysqli_result $dataResult
     * @param ArkLogger $logger
     */
    public function __construct($dataResult, $logger)
    {
        $this->dataResult = $dataResult;
        $this->logger = $logger;
    }

    /**
     * @param int $resultType
     * @return array|bool
     */
    public function readRows($resultType = MYSQLI_ASSOC)
    {
        if (!$this->dataResult) {
            $this->logger->error(__METHOD__ . ' Cannot fetch raw result and false would be returned.');
            return false;
        }
        $rows = [];
        while (true) {
            $row = $this->dataResult->fetch_array($resultType);
            $this->logger->debug(__METHOD__ . '@' . __LINE__ . " row fetched", ['row' => $row]);
            if ($row === null) {
                break;
            }
            $rows[] = $row;
        }
        $this->dataResult->close();
        return $rows;
    }

    /**
     * @param string|int $key
     * @param int $resultType
     * @return array|bool
     */
    public function readColumns($key, $resultType = MYSQLI_ASSOC)
    {
        if (!$this->dataResult) {
            $this->logger->error(__METHOD__ . ' Cannot fetch raw result and false would be returned.');
            return false;
        }
        $rows = [];
        while (true) {
            $row = $this->dataResult->fetch_array($resultType);
            $this->logger->debug(__METHOD__ . '@' . __LINE__ . " row fetched", ['row' => $row]);
            if ($row === null) {
                break;
            }
            $rows[] = $row[$key];
        }
        $this->dataResult->close();
        return $rows;
    }

    /**
     * @param int $resultType
     * @return array|bool
     */
    public function readRow($resultType = MYSQLI_ASSOC)
    {
        if (!$this->dataResult) {
            $this->logger->error(__METHOD__ . ' Cannot fetch raw result and false would be returned.');
            return false;
        }
        $row = false;
        while (true) {
            $row = $this->dataResult->fetch_array($resultType);
            $this->logger->debug(__METHOD__ . '@' . __LINE__ . " row fetched", ['row' => $row]);
            if ($row === null) {
                $this->logger->error(__METHOD__ . ' Cannot fetch row from raw result and false would be returned.');
                return false;
            }
            break;
        }
        $this->dataResult->close();
        return $row;
    }

    /**
     * @return array|bool
     */
    public function readCell()
    {
        if (!$this->dataResult) {
            $this->logger->error(__METHOD__ . ' Cannot fetch raw result and false would be returned.');
            return false;
        }
        $row = false;
        while (true) {
            $row = $this->dataResult->fetch_array(MYSQLI_NUM);
            $this->logger->debug(__METHOD__ . '@' . __LINE__ . " row fetched", ['row' => $row]);
            if ($row === null) {
                $this->logger->error(__METHOD__ . ' Cannot fetch row from raw result and false would be returned.');
                return false;
            }
            break;
        }
        $this->dataResult->close();
        return $row[0];
    }

    /**
     * @param $csvFilePath
     * @param bool|string[] $containTitleRow 为FALSE时不写标题行，常用于APPEND模式；如果为TRUE则使用结果集中每行的ALIAS名称；如果为非空字符串数组就以此为标题行内容。
     * @param null|string $csvCharset
     * @param null|string $resultCharset
     * @param bool $isAppend
     * @param int $chunkSize
     * @return bool
     */
    public function outputToCSV($csvFilePath, $containTitleRow, $csvCharset = null, $resultCharset = null, $isAppend = false, $chunkSize = 500): bool
    {
        $file = fopen($csvFilePath, ($isAppend ? "a+" : "w+"));

        $rowIndex = 0;

        if ($containTitleRow === true) {
            $fields = $this->dataResult->fetch_fields();

            if ($fields != false) {
                $names = [];
                foreach ($fields as $field) {
                    $names[] = $field->name;
                }
                Alderamin::switchCharset($names, $csvCharset, $resultCharset);
                $written = fputcsv($file, $names);

                $this->logger->debug(__METHOD__ . '@' . __LINE__ . " title row written", ['written' => $written]);
                $rowIndex++;
            }
        } elseif (is_array($containTitleRow) && !empty($containTitleRow)) {
            Alderamin::switchCharset($containTitleRow, $csvCharset, $resultCharset);
            $written = fputcsv($file, $containTitleRow);

            $this->logger->debug(__METHOD__ . '@' . __LINE__ . " title row written", ['written' => $written]);
            $rowIndex++;
        }

        $chunk = [];

        while (true) {
            $row = $this->dataResult->fetch_array(MYSQLI_NUM);
            $this->logger->debug(__METHOD__ . '@' . __LINE__ . " row fetched", ['row' => $row]);

            if ($row !== null) {
                // convert char encode if needed
                Alderamin::switchCharset($row, $csvCharset, $resultCharset);
                $chunk[] = $row;
                if (count($chunk) >= $chunkSize) {
                    $this->flushDataToCSV($file, $chunk, $rowIndex);
                }
            } else {
                $this->flushDataToCSV($file, $chunk, $rowIndex);
                break;
            }
        }
        fclose($file);
        $this->dataResult->close();
        $this->logger->logInline(PHP_EOL);
        $this->logger->info(__METHOD__ . '@' . __LINE__ . " Totally $rowIndex rows written");
        return true;
    }

    private function flushDataToCSV($file, &$rows, &$rowIndex)
    {
        $totalWritten = 0;
        foreach ($rows as $row) {
            $written = fputcsv($file, $row);
            if ($written === false) {
                $this->logger->warning(__METHOD__ . '@' . __LINE__ . " write row failed", ["row" => $row]);
            }
            $totalWritten += $written;
            $rowIndex++;
        }

        $rows = [];

        $this->logger->logInline("↓" . $rowIndex);
        $this->logger->debug(__METHOD__ . '@' . __LINE__ . " rows up to no.{$rowIndex} written", ['total_written_bytes' => $totalWritten]);
    }

    /**
     * NOTE: the result data set should ensure the field names the same with the target table field names
     *
     * @param DatabaseWriter $writer
     * @param $schema
     * @param $table
     * @param int $chunkSize
     * @return bool
     * @throws \Exception
     */
    public function insertIntoTable($writer, $schema, $table, $chunkSize = 500)
    {
        return $writer->insertIntoTable($this->dataResult, $schema, $table, $chunkSize);
    }

    /**
     * NOTE: the result data set should ensure the field names the same with the target table field names
     *
     * @param DatabaseWriter $writer
     * @param $schema
     * @param $table
     * @param int $chunkSize
     * @return bool
     * @throws \Exception
     */
    public function replaceIntoTable($writer, $schema, $table, $chunkSize = 500)
    {
        return $writer->replaceIntoTable($this->dataResult, $schema, $table, $chunkSize);
    }


}