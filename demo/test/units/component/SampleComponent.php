<?php
/**
 * Created by PhpStorm.
 * User: sinri
 * Date: 2019-03-31
 * Time: 21:43
 */

namespace sinri\Alderamin\test\units\component;


use sinri\Alderamin\core\unit\ComponentUnit;

class SampleComponent extends ComponentUnit
{

    /**
     * @return string
     */
    public function getName(): string
    {
        return "SAMPLE-COMPONENT";
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function run(): bool
    {
        $this->obtainLockForOnce(__METHOD__ . "-run", $this->callerReportId);

        $this->getWriter()->ensureTableExists(
            "test",
            "t2",
            $this->getQueryTemplate("create_table_t2")
        );

        $this->queryAndReplaceIntoTargetTable(
            ["update"],
            "test",
            "t2",
            "Update Value Sum Table"
        );

        sleep(10);

        $this->releaseLock(__METHOD__ . '-run');

        return true;
    }

    /**
     * @return string
     */
    public function getCode(): string
    {
        return "SampleComponent";
    }
}