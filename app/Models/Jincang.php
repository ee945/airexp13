<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Jincang extends Model
{
    protected $table = 'jincang';

    protected $fillable = [
        'regdate',
        'jcno',
        'dest',
        'fltdate',
        'client',
        'forward',
        'factory',
        'carrier',
        'delivery',
        'cargodata',
        'remark',
        // 原库 status 列 NOT NULL 且无默认值，旧实现靠 MySQL 非严格模式自动补 0。
        // MySQL 8 严格模式下必须显式写入，故纳入 fillable。
        'status',
    ];
}
