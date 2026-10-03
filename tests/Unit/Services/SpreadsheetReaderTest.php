<?php

use App\Services\Excel\SpreadsheetReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function writeSheet(array $rows): string
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1');
    $path = tempnam(sys_get_temp_dir(), 'wiis').'.xlsx';
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
