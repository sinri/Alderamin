<?php
/**
 * Created by PhpStorm.
 * User: sinri
 * Date: 2019-03-30
 * Time: 22:34
 */

namespace sinri\Alderamin\test\units\report;


use sinri\Alderamin\core\unit\ReportCategory;
use sinri\Alderamin\core\unit\ReportUnit;
use sinri\Alderamin\test\category\UnknownCategory;
use sinri\Alderamin\test\permission\DevelopPermission;
use sinri\Alderamin\test\units\component\SampleComponent;

class SampleReport extends ReportUnit
{

    /**
     * @return string
     */
    public function getName(): string
    {
        return "SAMPLE-REPORT";
    }

    /**
     * @return string
     */
    public function getCode(): string
    {
        return "SampleReport";
    }

    /**
     * @return array
     */
    public function getParameterDefinitions(): array
    {
        return [
            "limitation" => self::PARAM_TYPE_DECIMAL,
        ];
    }

    /**
     * @return ReportCategory
     */
    public function getReportCategory(): ReportCategory
    {
        return UnknownCategory::getInstance();
    }

    /**
     * 在这里放权限，空的数组的话说明没有权限限制，有的话就是或的关系
     * @return string[]
     */
    public function getReportPermissions(): array
    {
        return [DevelopPermission::CODE];
    }

    protected function runFetchMeta()
    {
        $this->logger->info(__METHOD__ . "@" . __LINE__);
    }

    /**
     * @throws \Exception
     */
    protected function runPrepareData()
    {
        $this->logger->info(__METHOD__ . "@" . __LINE__);

        // ensure reality table
        $this->getWriter()->ensureTableExists(
            "test",
            "t1",
            $this->getQueryTemplate("create_table_t1")
        );

        // mock new data
        for ($i = 0; $i < 10; $i++) {
            $desc = uniqid();
            $name = substr($desc, 8, 5);
            $value = number_format(rand() / 100, 2, ".", "");

            $details = [];
            $this->getWriter()->insert(
                "insert into test.t1(`id`,`name`,`desc`,`value`) values(null,'{$name}','{$desc}',{$value})",
                "Dummy Data Creating",
                $details
            );
        }

        // real generate middle data
        $this->callComponent(SampleComponent::class, []);
    }

    /**
     * @throws \Exception
     */
    protected function runMakeSheets()
    {
        $this->logger->info(__METHOD__ . "@" . __LINE__);

        $sheetCodeList = [
            'sheet_1',
        ];
        $this->createSimpleSheetsWithCodes($sheetCodeList);
    }
}