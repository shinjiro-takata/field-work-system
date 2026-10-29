<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * レシピと材料を結ぶ中間テーブル（ピボットテーブル）recipe_materials を作成する。
 * 後続の 2026_04_16 のマイグレーションで削除され、作り直されている。
 */
class CreateRecipeMaterialsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('recipe_materials', function (Blueprint $table) {
            $table->primary(['recipe_id', 'material_id']); // 複合主キー
            $table->foreignId('recipe_id')->constrained()->onDelete('cascade'); // レシピID
            $table->foreignId('material_id')->constrained()->onDelete('cascade'); // 材料ID
            $table->decimal('quantity', 8, 2); // 使用量
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
        Schema::dropIfExists('recipe_materials');
    }
}
