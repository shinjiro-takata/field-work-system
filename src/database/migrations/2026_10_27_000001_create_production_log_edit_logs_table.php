<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductionLogEditLogsTable extends Migration
{
    public function up()
    {
        Schema::create('production_log_edit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_log_id')->constrained()->cascadeOnDelete();
            $table->foreignId('editor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('editor_name');
            $table->json('before_data');
            $table->json('after_data');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('production_log_edit_logs');
    }
}
