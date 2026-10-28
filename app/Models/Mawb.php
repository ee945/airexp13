<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mawb extends Model
{
    protected $table = 'mawbs';

    protected $fillable = [
        'mawb',
        'oversea',
        'dest',
        'desti',
        'depa',
        'depar',
        'shipper',
        'consignee',
        'agentabbr',
        'agentcode',
        'agentaccount',
        'carrier',
        'fltno',
        'fltdate',
        'special',
        'package',
        'num',
        'gw',
        'cw',
        'cbm',
        'rclass',
        'up',
        'freight',
        'awn',
        'myn',
        'scn',
        'myup',
        'scup',
        'aw',
        'my',
        'sc',
        'other',
        'amount',
        'cgodescp',
        'signature',
        'atplace',
        'operator',
        'opdate',
        'regtime',
    ];
}
