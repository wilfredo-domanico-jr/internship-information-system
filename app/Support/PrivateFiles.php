<?php

namespace App\Support;

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;

class PrivateFiles
{
    /**
     * kind => [model class, attribute holding the path, policy ability checked against the record].
     *
     * @return array<string, array{0: class-string<Model>, 1: string, 2: string}>
     */
    public static function registry(): array
    {
        return [
            'company-permit' => [Company::class, 'permit_path', 'viewDocuments'],
            'company-moa' => [Company::class, 'moa_path', 'viewDocuments'],
        ];
    }

    /** @return array{0: class-string<Model>, 1: string, 2: string} */
    public static function resolve(string $kind): array
    {
        return static::registry()[$kind] ?? abort(404);
    }
}
