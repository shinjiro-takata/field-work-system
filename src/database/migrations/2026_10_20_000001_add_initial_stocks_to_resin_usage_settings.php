<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * レジン設定テーブルに、2種類の材料それぞれの初期在庫カラムを追加するマイグレーション
 * （マイグレーション = DBの構造変更をコードで管理する仕組み）。
 * 初期在庫は、過去の記録が無い最初の日の「前回在庫」として使う。既存行があるため nullable（空を許可）。
 */
class AddInitialStocksToResinUsageSettings extends Migration
{
    /** カラムを追加する */
    public function up()
    {
        Schema::table('resin_usage_settings', function (Blueprint $table) {
            $table->decimal('material_one_initial_stock', 12, 3)->nullable();
            $table->decimal('material_two_initial_stock', 12, 3)->nullable();
        });
    }

    /** 追加したカラムを削除して元に戻す */
    public function down()
    {
        Schema::table('resin_usage_settings', function (Blueprint $table) {
            $table->dropColumn(['material_one_initial_stock', 'material_two_initial_stock']);
        });
    }
}
