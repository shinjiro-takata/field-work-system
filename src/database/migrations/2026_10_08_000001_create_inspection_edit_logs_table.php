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
        // 点検記録を編集したときの履歴を保存する。
        Schema::create('inspection_edit_logs', function (Blueprint $table) {
            // 編集履歴を識別するID。
            $table->id();
            // 対象の点検記録。記録が削除されたら履歴も削除する。
            $table->foreignId('inspection_record_id')->constrained()->onDelete('cascade');
            // 編集を行った人の名前。
            $table->string('editor_name', 80);
            // 変更前後の値をJSON形式で保存する。
            $table->json('changes');
            // 編集が行われた日時。
            $table->timestamp('created_at');
            // 記録ごとの履歴を日時順に探しやすくする索引。
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
