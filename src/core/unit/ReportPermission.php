<?php
/**
 * Created by PhpStorm.
 * User: sinri
 * Date: 2019-03-30
 * Time: 00:42
 */

namespace sinri\Alderamin\core\unit;


class ReportPermission
{
    private static $permissionDefinitions = null;

    /**
     * @return null
     */
    public static function getPermissionDefinitions()
    {
        return self::$permissionDefinitions;
    }

    /**
     * @param ReportPermission[] $permissionDefinitions
     */
    public static function setPermissionDefinitions($permissionDefinitions)
    {
        self::$permissionDefinitions = $permissionDefinitions;
    }
    public $permissionCode;
    public $permissionTitle;
    public $permissionMemo;

    /**
     * ReportPermission constructor.
     * @param string $code
     * @param string $title
     * @param string $memo
     */
    protected function __construct($code, $title, $memo = '')
    {
        $this->permissionCode = $code;
        $this->permissionTitle = $title;
        $this->permissionMemo = $memo;
    }

    /**
     * @param string $code
     * @param string $title
     * @param string $memo
     * @return ReportPermission
     */
    public static function createPermission($code, $title, $memo = '')
    {
        return new ReportPermission($code, $title, $memo);
    }

}