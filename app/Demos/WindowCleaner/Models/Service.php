<?php

namespace App\Demos\WindowCleaner\Models;

use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $table = 'wc_services';

    protected $guarded = [];

    protected static function newFactory(): ServiceFactory
    {
        return ServiceFactory::new();
    }
}
