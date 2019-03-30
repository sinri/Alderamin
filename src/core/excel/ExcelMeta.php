<?php
/**
 * Created by PhpStorm.
 * User: sinri
 * Date: 2019-03-29
 * Time: 23:57
 */

namespace sinri\Alderamin\core\excel;


class ExcelMeta
{
    /**
     * @var ExcelSheetMeta[]
     */
    protected $sheetMetaList = [];

    public function __construct()
    {
        $this->sheetMetaList = [];
    }

    /**
     * @return ExcelSheetMeta[]
     */
    public function getSheetMetaList(): array
    {
        return $this->sheetMetaList;
    }

    /**
     * @param ExcelSheetMeta $sheetMeta
     */
    public function appendSheetMeta($sheetMeta)
    {
        $this->sheetMetaList[] = $sheetMeta;
    }

    public function clearSheetMeta()
    {
        $this->sheetMetaList = [];
    }
}