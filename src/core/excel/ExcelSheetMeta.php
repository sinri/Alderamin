<?php
/**
 * Created by PhpStorm.
 * User: sinri
 * Date: 2019-03-29
 * Time: 23:57
 */

namespace sinri\Alderamin\core\excel;


use sinri\Alderamin\core\Alderamin;
use sinri\ark\core\ArkHelper;

class ExcelSheetMeta
{
    /**
     * @var string
     */
    protected $sheetName;
    /**
     * @var string PATH of CSV
     */
    protected $sheetCSV;
    /**
     * @var string[]
     */
    protected $titleRow;

    /**
     * PolarExcelSheetMeta constructor.
     * @param $reportCode
     * @param $sheetCode
     * @param $sheetCSV
     * @throws \Exception
     */
    public function __construct($reportCode, $sheetCode, $sheetCSV)
    {
        $meta = self::getSheetMetaFromPolarisStore($sheetCode, $reportCode);
        $this->titleRow = ArkHelper::readTarget($meta, ['title_row'], null);
        if ($this->titleRow === null) {
            // 如果meta里不指定title_row的话就用sql的fields
            $this->titleRow = true;
        }
        $this->sheetName = ArkHelper::readTarget($meta, ['sheet_name'], $sheetCode);
        $this->sheetCSV = $sheetCSV;
    }

    /**
     * @param string $code Sheet Code
     * @param string $folder Report Code
     * @param string $type
     * @return false|array
     * @throws \Exception
     */
    public static function getSheetMetaFromPolarisStore($code, $folder, $type = "report")
    {
        $sqlPath = Alderamin::getConfig()->getSqlStore();
        $sqlPath .= DIRECTORY_SEPARATOR . $type;
        $sqlPath .= DIRECTORY_SEPARATOR . $folder;
        $sqlPath .= DIRECTORY_SEPARATOR . $code . ".json";

        if (!file_exists($sqlPath)) {
            $error = "Cannot find json file: " . $sqlPath;
            //$this->logger->error($error);
            throw new \Exception($error);
        }

        $template = file_get_contents($sqlPath);

        return json_decode($template, true);
    }

    /**
     * @return string
     */
    public function getSheetName(): string
    {
        return $this->sheetName;
    }

    /**
     * @param string $sheetName
     */
    public function setSheetName(string $sheetName)
    {
        $this->sheetName = $sheetName;
    }

    /**
     * @return string
     */
    public function getSheetCSV(): string
    {
        return $this->sheetCSV;
    }

    /**
     * @param string $sheetCSV
     */
    public function setSheetCSV(string $sheetCSV)
    {
        $this->sheetCSV = $sheetCSV;
    }

    /**
     * @return string[]
     */
    public function getTitleRow(): array
    {
        return $this->titleRow;
    }

    /**
     * @param string[] $titleRow
     */
    public function setTitleRow(array $titleRow)
    {
        $this->titleRow = $titleRow;
    }
}