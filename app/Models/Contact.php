<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $table = 'contacts';

    protected $fillable = [
        'name',
        'gender',
        'code',
        'company',
        'title',
        'mobile',
        'tel',
        'fax',
        'mail',
        'im',
        'address',
        'remark',
    ];
}
