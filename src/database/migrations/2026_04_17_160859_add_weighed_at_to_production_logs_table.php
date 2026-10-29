<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * production_logs に、実際に計量した日時 weighed_at を追加する。
 */
class AddWeighedAtToProductionLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('production_logs', function (Blueprint $table) {
            $table->datetime('weighed_at')->nullable()->after('recipe_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('production_logs', function (Blueprint $table) {
            $table->dropColumn('weighed_at');
        });
    }
}
