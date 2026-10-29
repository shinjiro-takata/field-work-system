<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 新構造の計量ログテーブル production_logs を作成する（誰が・どのレシピで・備考。材料ごとの量は production_log_materials に持つ）。
 */
class CreateProductionLogsTableV2 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('production_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained(); // どのユーザーか
            $table->foreignId('recipe_id')->constrained(); // どのレシピか
            $table->mediumText('notes')->nullable(); // メモ
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
        Schema::dropIfExists('production_logs');
    }
}
