<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateResinUsageTables extends Migration
{
    public function up()
    {
        Schema::create('resin_usage_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->foreignId('material_one_id')->nullable()->constrained('materials')->nullOnDelete();
            $table->foreignId('material_two_id')->nullable()->constrained('materials')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('resin_usage_records', function (Blueprint $table) {
            $table->id();
            $table->date('usage_date')->unique();
            $table->string('operator_name');
            $table->string('supervisor_name')->nullable();
            $table->dateTime('supervisor_confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('resin_usage_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resin_usage_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->decimal('opening_stock', 12, 3);
            $table->decimal('used_quantity', 12, 3)->default(0);
            $table->decimal('replenished_quantity', 12, 3)->default(0);
            $table->decimal('calculated_stock', 12, 3);
            $table->decimal('actual_stock', 12, 3);
            $table->decimal('adjustment_quantity', 12, 3)->default(0);
            $table->string('adjustment_reason')->nullable();
            $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('closing_stock', 12, 3);
            $table->timestamps();

            $table->unique(['resin_usage_record_id', 'material_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('resin_usage_items');
        Schema::dropIfExists('resin_usage_records');
        Schema::dropIfExists('resin_usage_settings');
    }
}
