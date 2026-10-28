<?php

namespace App\Http\Controllers;

use App\Models\Hawb;
use App\Models\Jincang;
use App\Models\Mawb;

class HomeController extends Controller
{
    /**
     * 首页仪表盘（重构进行中，先给出与原站相同的数据口径）。
     */
    public function index()
    {
        $stats = [
            'hawb' => Hawb::count(),
            'mawb' => Mawb::count(),
            'jincang' => Jincang::count(),
        ];

        return view(theme('home'), compact('stats'));
    }
}
