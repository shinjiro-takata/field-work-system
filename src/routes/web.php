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
| このファイルに、ブラウザーから利用する画面や処理へのルートを定義します。
| ここで定義したルートは RouteServiceProvider により読み込まれます。
| web ミドルウェアグループが適用され、セッションや CSRF 対策などが有効になります。
|
*/

// ログイン中の利用者がログアウトするためのルートです。auth はログイン済みであることを確認します。
Route::post('/logout', [LogoutController::class, 'destroy'])->name('logout')->middleware('auth');

// 管理者専用の画面です。auth はログインを確認し、can:admin-only は管理者権限を確認します。
Route::middleware(['auth', 'can:admin-only'])->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);

    Route::resource('users', UserManagementController::class)->except(['show']);

    // 材料の登録・編集・削除を行う管理画面です。
    Route::resource('materials', MaterialController::class)->except(['show']);

    Route::get('/resin-usage/settings', [ResinUsageSettingController::class, 'edit'])->name('resin-usage.settings.edit');
    Route::put('/resin-usage/settings', [ResinUsageSettingController::class, 'update'])->name('resin-usage.settings.update');

    // レシピを新規作成・編集・削除する管理画面です。
    Route::get('/recipes/create', [RecipeController::class, 'create'])->name('recipes.create');
    Route::post('/recipes', [RecipeController::class, 'store'])->name('recipes.store');
    Route::get('/recipes/{recipe}/edit', [RecipeController::class, 'edit'])->name('recipes.edit');
    Route::put('/recipes/{recipe}', [RecipeController::class, 'update'])->name('recipes.update');
    Route::delete('/recipes/{recipe}', [RecipeController::class, 'destroy'])->name('recipes.destroy');
});

// ログイン必須の画面です。auth は未ログイン利用者をログイン画面へ案内します。
Route::middleware(['auth'])->group(function () {
    // 各アプリの入口、レジン使用量の入力・履歴・修正、および計量ログ画面です。
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

    // ログイン済みの利用者は、レシピを閲覧できます。
    Route::get('/recipes', [RecipeController::class, 'index'])->name('recipes.index');
    Route::get('/recipes/{recipe}', [RecipeController::class, 'show'])->name('recipes.show');
});

// 設備点検の画面です。ログインが必要で、URLの先頭を /equipment にそろえています。
Route::middleware(['auth'])->prefix('equipment')->group(function () {
    // 設備点検のメニュー画面です。
    Route::get('/', [HomeController::class, 'index'])->name('home');

    // 週次・月次・定期点検の入力、保存、履歴、CSV出力、詳細、編集画面です。点検種別は type で切り替えます。
    foreach (['weekly', 'monthly', 'periodic'] as $type) {
        Route::get("/{$type}", [ScheduledInspectionController::class, 'create'])->defaults('type', $type)->name("inspections.{$type}.create");
        Route::post("/{$type}/inspections", [ScheduledInspectionController::class, 'store'])->defaults('type', $type)->name("inspections.{$type}.store");
        Route::get("/{$type}/inspections", [ScheduledInspectionController::class, 'index'])->defaults('type', $type)->name("inspections.{$type}.index");
        Route::get("/{$type}/inspections/export", [ScheduledInspectionController::class, 'export'])->defaults('type', $type)->name("inspections.{$type}.export");
        Route::get("/{$type}/inspections/{inspection}", [ScheduledInspectionController::class, 'show'])->defaults('type', $type)->name("inspections.{$type}.show");
        Route::get("/{$type}/inspections/{inspection}/edit", [ScheduledInspectionController::class, 'edit'])->defaults('type', $type)->name("inspections.{$type}.edit");
        Route::put("/{$type}/inspections/{inspection}", [ScheduledInspectionController::class, 'update'])->defaults('type', $type)->name("inspections.{$type}.update");
    }

    // 日常点検の入力画面と、新しい点検記録の登録先です。
    Route::get('/daily', [InspectionController::class, 'create'])->name('inspections.create');
    Route::post('/inspections', [InspectionController::class, 'store'])->name('inspections.store');

    // 点検履歴の閲覧、CSV出力、個別記録の詳細画面です。
    Route::get('/inspections', [InspectionController::class, 'history'])->name('inspections.index');
    Route::get('/inspections/export', [InspectionController::class, 'export'])->name('inspections.export');
    Route::get('/inspections/{inspection}', [InspectionController::class, 'show'])->name('inspections.show');

    // 点検記録を編集する画面と、変更内容の保存先です。
    Route::get('/inspections/{inspection}/edit', [InspectionController::class, 'edit'])->name('inspections.edit');
    Route::put('/inspections/{inspection}', [InspectionController::class, 'update'])->name('inspections.update');
});
