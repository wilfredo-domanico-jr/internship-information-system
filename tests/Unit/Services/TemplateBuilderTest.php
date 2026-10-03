<?php

use App\Services\Excel\SpreadsheetReader;
use App\Services\Excel\TemplateBuilder;
use App\Support\ImportColumns;

it('builds a template whose headers round-trip through the reader', function () {
    $path = (new TemplateBuilder)->build(ImportColumns::INTERNS, ImportColumns::EXAMPLES['interns'], 'Interns');

    expect($path)->toEndWith('.xlsx')->and(file_exists($path))->toBeTrue();

    $rows = (new SpreadsheetReader)->read($path);
    expect($rows)->toHaveCount(1)
        ->and(array_keys($rows[0]))->toBe([...ImportColumns::INTERNS, '_row'])
        ->and($rows[0]['email'])->toBe(ImportColumns::EXAMPLES['interns'][3]);
});
