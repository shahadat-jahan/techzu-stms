<?php

namespace App\Models;

use Database\Factories\ImportErrorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['import_id', 'row_number', 'raw_data', 'error_message'])]
class ImportError extends Model
{
    /** @use HasFactory<ImportErrorFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'row_number' => 'integer',
            'raw_data' => 'array',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }
}
