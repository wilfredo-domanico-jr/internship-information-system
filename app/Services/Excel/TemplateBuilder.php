<?php

namespace App\Services\Excel;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class TemplateBuilder
{
    /**
     * @param  array<int, string>  $headers
     * @param  array<int, string>  $example  one example row, same order as $headers
     * @return string absolute path of a temporary .xlsx file
     */
    public function build(array $headers, array $example, string $sheetTitle): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($sheetTitle, 0, 31));
        $sheet->fromArray([$headers, $example], null, 'A1');
        $sheet->getStyle('A1:'.$sheet->getHighestColumn().'1')->getFont()->setBold(true);
        foreach (range('A', $sheet->getHighestColumn()) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $path = tempnam(sys_get_temp_dir(), 'wiis-template').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }
}
