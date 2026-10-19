<?php

namespace App\Http\Controllers;

use App\Support\InspectionTypes;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * 点検種別の一覧を表示するホーム画面。
     */
    public function index(): View
    {
        return view('home', ['types' => InspectionTypes::all()]);
    }

    /**
     * 項目が未登録の点検種別に対する準備中画面を表示する。
     */
    public function comingSoon(string $type): View
    {
        return view('inspections.coming-soon', ['type' => InspectionTypes::find($type)]);
    }
}
