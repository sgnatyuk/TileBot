<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TileRequest extends Model
{
    protected $fillable = [
        'telegram_user_id',
        'telegram_username',
        'file_id',
        'description',
        'full_result',
        'comment',
    ];
}
