<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MaterialController;
use Laravel\Fortify\Http\Controllers\RegisteredUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\WeighingLogController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\LogoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InspectionController;
use App\Http\Controllers\ScheduledInspectionController;
use App\Http\Controllers\ResinUsageController;
use App\Http\Controllers\ResinUsageSettingController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// ログアウト
Route::post('/logout', [LogoutController::class, 'destroy'])->name('logout')->middleware('auth');

// ログイン（auth）していて、かつ管理者（can:admin-only）であること
Route::middleware(['auth', 'can:admin-only'])->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);

    Route::resource('users', UserManagementController::class)->except(['show']);

    // 材料管理
    Route::resource('materials', MaterialController::class)->except(['show']);

    Route::get('/resin-usage/settings', [ResinUsageSettingController::class, 'edit'])->name('resin-usage.settings.edit');
    Route::put('/resin-usage/settings', [ResinUsageSettingController::class, 'update'])->name('resin-usage.settings.update');

    // 管理者のみレシピを管理（作成・編集・削除）
    Route::get('/recipes/create', [RecipeController::class, 'create'])->name('recipes.create');
    Route::post('/recipes', [RecipeController::class, 'store'])->name('recipes.store');
    Route::get('/recipes/{recipe}/edit', [RecipeController::class, 'edit'])->name('recipes.edit');
    Route::put('/recipes/{recipe}', [RecipeController::class, 'update'])->name('recipes.update');
    Route::delete('/recipes/{recipe}', [RecipeController::class, 'destroy'])->name('recipes.destroy');
});

// ログイン（auth）している人だけがアクセスできるように上書き
Route::middleware(['auth'])->group(function () {
    // 各アプリを選ぶホーム画面。
    Route::view('/', 'portal')->name('portal');
    Route::view('/resin-usage', 'resin-usage.index')->name('resin-usage.index');
    Route::get('/resin-usage/history', [ResinUsageController::class, 'index'])->name('resin-usage.history');
    Route::get('/resin-usage/create', [ResinUsageController::class, 'create'])->name('resin-usage.create');
    Route::post('/resin-usage', [ResinUsageController::class, 'store'])->name('resin-usage.store');
    Route::get('/resin-usage/{record}/edit', [ResinUsageController::class, 'edit'])->name('resin-usage.edit');
    Route::put('/resin-usage/{record}', [ResinUsageController::class, 'update'])->name('resin-usage.update');
    Route::delete('/resin-usage/{record}', [ResinUsageController::class, 'destroy'])->name('resin-usage.destroy');
    Route::get('/index', [DashboardController::class, 'index'])->name('index');
    Route::get('/logs/export', [WeighingLogController::class, 'export'])->name('logs.export');
    Route::resource('logs', WeighingLogController::class);

    // すべての認証ユーザーがレシピ確認のみ可能
    Route::get('/recipes', [RecipeController::class, 'index'])->name('recipes.index');
    Route::get('/recipes/{recipe}', [RecipeController::class, 'show'])->name('recipes.show');
});

// 設備点検（ログイン必須）。URLは /equipment 配下にまとめる。
Route::middleware(['auth'])->prefix('equipment')->group(function () {
    // 各点検へ移動するためのホーム画面。
    Route::get('/', [HomeController::class, 'index'])->name('home');

    // 週次・月次・定期点検の入力、保存、履歴、CSV出力、詳細、編集。点検種別はtypeで切り替える。
    foreach (['weekly', 'monthly', 'periodic'] as $type) {
        Route::get("/{$type}", [ScheduledInspectionController::class, 'create'])->defaults('type', $type)->name("inspections.{$type}.create");
        Route::post("/{$type}/inspections", [ScheduledInspectionController::class, 'store'])->defaults('type', $type)->name("inspections.{$type}.store");
        Route::get("/{$type}/inspections", [ScheduledInspectionController::class, 'index'])->defaults('type', $type)->name("inspections.{$type}.index");
        Route::get("/{$type}/inspections/export", [ScheduledInspectionController::class, 'export'])->defaults('type', $type)->name("inspections.{$type}.export");
        Route::get("/{$type}/inspections/{inspection}", [ScheduledInspectionController::class, 'show'])->defaults('type', $type)->name("inspections.{$type}.show");
        Route::get("/{$type}/inspections/{inspection}/edit", [ScheduledInspectionController::class, 'edit'])->defaults('type', $type)->name("inspections.{$type}.edit");
        Route::put("/{$type}/inspections/{inspection}", [ScheduledInspectionController::class, 'update'])->defaults('type', $type)->name("inspections.{$type}.update");
    }

    // 日常点検の入力画面と新しい点検記録の登録。
    Route::get('/daily', [InspectionController::class, 'create'])->name('inspections.create');
    Route::post('/inspections', [InspectionController::class, 'store'])->name('inspections.store');

    // 閲覧専用の点検履歴、CSV出力、記録詳細。
    Route::get('/inspections', [InspectionController::class, 'history'])->name('inspections.index');
    Route::get('/inspections/export', [InspectionController::class, 'export'])->name('inspections.export');
    Route::get('/inspections/{inspection}', [InspectionController::class, 'show'])->name('inspections.show');

    // 編集画面を経由した場合に限り、点検記録の変更を許可する。
    Route::get('/inspections/{inspection}/edit', [InspectionController::class, 'edit'])->name('inspections.edit');
    Route::put('/inspections/{inspection}', [InspectionController::class, 'update'])->name('inspections.update');
});
