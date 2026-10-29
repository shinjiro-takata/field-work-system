<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 最初のレシピテーブル（原料1行=1レシピの旧構造）を作成する。
 * 後続の 2026_04_13 のマイグレーションで削除され、作り直されている。
 */
class CreateRecipesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->onDelete('cascade'); // どのカテゴリか
            $table->string('material_name'); // 原料名
            $table->double('weight'); // 基準の重さ
            $table->unsignedBigInteger('created_by'); // 作成者ID
            $table->unsignedBigInteger('updated_by'); // 更新者ID

            // 作成者・更新者が削除されてもデータは残るように設定（安全策）
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
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
        Schema::dropIfExists('recipes');
    }
}
