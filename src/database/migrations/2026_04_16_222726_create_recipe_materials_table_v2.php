<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * recipe_materials を作り直す。基準量 quantity を小数第3位まで持てるようにした（decimal(8, 3)）。
 */
class CreateRecipeMaterialsTableV2 extends Migration
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
            $table->decimal('quantity', 8, 3); // 使用量
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
