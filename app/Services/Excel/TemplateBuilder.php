<?php

namespace App\Services\Excel;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
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
        $sheet->getStyle('A2:'.$sheet->getHighestColumn().'1000')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        foreach (range('A', $sheet->getHighestColumn()) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $base = tempnam(sys_get_temp_dir(), 'wiis-template');
        $path = $base.'.xlsx';
        rename($base, $path);
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }
}
