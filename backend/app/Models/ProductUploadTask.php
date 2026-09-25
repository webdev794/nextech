<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One spreadsheet uploaded through Products -> Add products via upload, with its per-row results. */
class ProductUploadTask extends Model
{
    protected $fillable = ['shop_id', 'file_name', 'file_path', 'status', 'records', 'error_records', 'rows', 'results'];

    protected $hidden = ['rows', 'file_path'];

    protected function casts(): array
    {
        return ['rows' => 'array', 'results' => 'array', 'records' => 'integer', 'error_records' => 'integer'];
    }
}
