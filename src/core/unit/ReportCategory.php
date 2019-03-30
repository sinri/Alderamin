<?php
/**
 * Created by PhpStorm.
 * User: sinri
 * Date: 2019-03-30
 * Time: 00:31
 */

namespace sinri\Alderamin\core\unit;


class ReportCategory
{
    protected $categoryCode;
    protected $categoryName;

    public function __construct($categoryCode, $categoryName)
    {
        $this->categoryCode = $categoryCode;
        $this->categoryName = $categoryName;
    }

    /**
     * @return string
     */
    public function getCategoryCode()
    {
        return $this->categoryCode;
    }

    /**
     * @return string
     */
    public function getCategoryName()
    {
        return $this->categoryName;
    }

    /**
     * @return array
     */
    public function getCategoryMeta()
    {
        return [
            "code" => $this->categoryCode,
            "name" => $this->categoryName,
        ];
    }
}