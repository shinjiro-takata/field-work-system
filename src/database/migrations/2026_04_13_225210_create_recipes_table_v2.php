<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 新構造のレシピテーブル recipes を作成する（レシピ名のみを持ち、材料は recipe_materials で紐付ける）。
 */
class CreateRecipesTableV2 extends Migration
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
            $table->string('name')->unique(); // レシピの名前
            $table->unsignedBigInteger('created_by')->nullable(); // 作成者ID
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null'); // 作成者ID
            $table->unsignedBigInteger('updated_by')->nullable(); // 更新者ID
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null'); // 更新者ID
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
