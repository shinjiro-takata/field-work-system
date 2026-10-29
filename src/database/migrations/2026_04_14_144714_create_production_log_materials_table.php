<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 計量ログと材料を結ぶ中間テーブル（ピボットテーブル）production_log_materials を作成する。
 * 材料ごとの実測量 actual_quantity をここに保存する。
 */
class CreateProductionLogMaterialsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('production_log_materials', function (Blueprint $table) {
            $table->primary(['production_log_id', 'material_id']); // 複合主キー
            $table->foreignId('production_log_id')->constrained(); // どの生産ログか
            $table->foreignId('material_id')->constrained(); // どの材料か
            $table->decimal('actual_quantity', 8, 2); // 使用量
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('production_log_materials');
    }
}
