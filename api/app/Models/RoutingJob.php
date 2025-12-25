<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RoutingJob extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'status',
        'original_filename',
        'stored_path',
        'output_json_path',
        'output_csv_path',
        'error_message',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $routingJob) {
            if (empty($routingJob->id)) {
                $routingJob->id = (string) Str::uuid();
            }
        });
    }
}
