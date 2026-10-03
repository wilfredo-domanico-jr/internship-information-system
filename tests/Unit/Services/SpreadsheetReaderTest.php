<?php

use App\Services\Excel\SpreadsheetReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function writeSheet(array $rows): string
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1');
    $base = tempnam(sys_get_temp_dir(), 'wiis');
    $path = $base.'.xlsx';
    rename($base, $path);
    (new Xlsx($spreadsheet))->save($path);

    return $path;
}

it('normalizes headers, trims values, skips blank rows and numbers rows', function () {
    $path = writeSheet([
        ['First Name ', 'EMAIL', 'Student Number'],
        [' Maria ', 'maria@example.com', '21-0001'],
        ['', '', ''],
        ['Pedro', ' pedro@example.com', null],
    ]);

    $rows = (new SpreadsheetReader)->read($path);

    expect($rows)->toHaveCount(2)
        ->and($rows[0])->toBe(['first_name' => 'Maria', 'email' => 'maria@example.com', 'student_number' => '21-0001', '_row' => 2])
        ->and($rows[1])->toBe(['first_name' => 'Pedro', 'email' => 'pedro@example.com', 'student_number' => null, '_row' => 4]);
});

it('returns an empty collection for a header-only sheet', function () {
    expect((new SpreadsheetReader)->read(writeSheet([['first_name', 'email']])))->toBeEmpty();
});

it('returns formatted strings for time and numeric cells', function () {
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([['a', 'b', 'c'], ['x', 8 / 24, 42]], null, 'A1');
    $sheet->getStyle('B2')->getNumberFormat()->setFormatCode('h:mm');
    $base = tempnam(sys_get_temp_dir(), 'wiis');
    $path = $base.'.xlsx';
    rename($base, $path);
    (new Xlsx($spreadsheet))->save($path);

    $row = (new SpreadsheetReader)->read($path)[0];

    expect($row['b'])->toBe('8:00')->and($row['c'])->toBe('42');
});
