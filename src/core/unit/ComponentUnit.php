<?php
/**
 * Created by PhpStorm.
 * User: sinri
 * Date: 2019-03-30
 * Time: 00:23
 */

namespace sinri\Alderamin\core\unit;


use sinri\ark\core\ArkLogger;

abstract class ComponentUnit extends BaseUnit
{
    /**
     * @var int The ID of the report which use this component, so called caller report id
     */
    protected $callerReportId;

    /**
     * ComponentUnit constructor.
     * @param int $callerReportId
     * @param ArkLogger $logger
     * @param array $parameters
     */
    public function __construct($callerReportId, $logger, $parameters = [])
    {
        $this->logger = $logger;

        $this->callerReportId = $callerReportId;

        if (!is_array($parameters)) $parameters = [];
        $parameters['reportId'] = $callerReportId;
        $this->parameters = $parameters;
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
        return $this->fetchQueryTemplateFromSqlStore($name, $folder, "component", $runCommonReplace);
    }

    /**
     * @param string $componentClass AnyComponentUnit::class
     * @param array $parameters
     * @return boolean
     * @throws \Exception
     */
    protected function callComponent($componentClass, $parameters = [])
    {
        $component = (new $componentClass($this->callerReportId, $this->logger, $parameters));
        if (is_subclass_of($component, self::class))
            return call_user_func_array([$component, 'run'], []);
        else
            throw new \Exception("Component Illegal");
    }
}