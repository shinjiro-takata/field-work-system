<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateResinUsageAudits extends Migration
{
    public function up()
    {
        Schema::create('resin_usage_audits', function (Blueprint $table) {
            $table->id();
            $table->string('action');
            $table->date('usage_date');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('changed_by_name');
            $table->json('before_data')->nullable();
            $table->json('after_data')->nullable();
            $table->timestamps();

            $table->index(['usage_date', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('resin_usage_audits');
    }
}
