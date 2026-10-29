<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * recipe_materials に、材料ごとの許容範囲 tolerance（基準量からの ± の幅）を追加する。
 * 未設定（null）の場合は許容範囲のチェックを行わない。
 */
class AddToleranceToRecipeMaterialsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('recipe_materials', function (Blueprint $table) {
            $table->decimal('tolerance', 8, 3)->nullable()->after('quantity')->comment('許容範囲（相対値、±で上下同じ）');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('recipe_materials', function (Blueprint $table) {
            $table->dropColumn('tolerance');
        });
    }
}
