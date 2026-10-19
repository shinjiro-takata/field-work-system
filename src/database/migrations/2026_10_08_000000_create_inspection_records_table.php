<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInspectionRecordsTable extends Migration
{
    /**
     * 点検日ごとに点検記録を1件保存するテーブルを作成する。
     */
    public function up(): void
    {
        Schema::create('inspection_records', function (Blueprint $table) {
            $table->id();
            $table->date('inspection_date')->unique();
            $table->string('inspector', 80);
            $table->text('note')->nullable();
            $table->json('results');
            $table->string('last_edited_by', 80)->nullable();
            $table->timestamps();
        });
    }

    /**
     * マイグレーションを戻す際に点検記録テーブルを削除する。
     */
    public function down(): void
    {
        Schema::dropIfExists('inspection_records');
    }
}
