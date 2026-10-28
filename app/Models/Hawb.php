<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Hawb extends Model
{
    protected $table = 'hawbs';

    protected $fillable = [
        'opdate',
        'hawb',
        'mawb',
        'dest',
        'desti',
        'fltno',
        'fltdate',
        'forward',
        'factory',
        'seller',
        'carrier',
        'carriername',
        'num',
        'gw',
        'cw',
        'cbm',
        'paymt',
        'arranged',
        'remark',
        'depar',
        'consignee',
        'notify',
        'shipper',
        'curr',
        'nvd',
        'ncv',
        'package',
        'rclass',
        'special',
        'cgodescp',
        'agentabbr',
        'regtime',
    ];
}
