<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInspectionEditLogsTable extends Migration
{
    /**
     * 編集者と変更内容を含む、点検記録の編集履歴テーブルを作成する。
     */
    public function up(): void
    {
        Schema::create('inspection_edit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_record_id')->constrained()->onDelete('cascade');
            $table->string('editor_name', 80);
            $table->json('changes');
            $table->timestamp('created_at');
            $table->index(['inspection_record_id', 'created_at']);
        });
    }

    /**
     * マイグレーションを戻す際に編集履歴テーブルを削除する。
     */
    public function down(): void
    {
        Schema::dropIfExists('inspection_edit_logs');
    }
}
