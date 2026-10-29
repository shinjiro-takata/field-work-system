<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 旧構造の recipes テーブルを削除する（作り直しのための前段）。
 * 削除前に、production_logs からの外部キー制約（他テーブルとの紐付けルール）を外している。
 */
class DropRecipesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('production_logs', function (Blueprint $table) {
            $table->dropForeign('production_logs_recipe_id_foreign');
        });

        Schema::dropIfExists('recipes');

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
       
    }
}
