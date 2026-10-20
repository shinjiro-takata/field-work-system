<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddInitialStocksToResinUsageSettings extends Migration
{
    public function up()
    {
        Schema::table('resin_usage_settings', function (Blueprint $table) {
            $table->decimal('material_one_initial_stock', 12, 3)->nullable();
            $table->decimal('material_two_initial_stock', 12, 3)->nullable();
        });
    }

    public function down()
    {
        Schema::table('resin_usage_settings', function (Blueprint $table) {
            $table->dropColumn(['material_one_initial_stock', 'material_two_initial_stock']);
        });
    }
}
