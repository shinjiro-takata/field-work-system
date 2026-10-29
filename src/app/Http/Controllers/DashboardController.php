<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * ダッシュボード（トップの案内画面）を表示するコントローラ。
 */
class DashboardController extends Controller
{
    /** トップ画面（index.blade.php）を表示する。 */
    public function index()
    {
        return view('index');
    }
}
