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
     * @return ReportPermission[]
     */
    public static function getPermissionList()
    {
        if (self::$permissionDefinitions === null) {
            self::$permissionDefinitions = [
                new ReportPermission("report_permission_demo", "一个测试用的示例", "就是个示例"),
                new ReportPermission("report_permission_dev", "报表调试权限", "开发人员为所欲为用"),
            ];
        }
        return self::$permissionDefinitions;
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