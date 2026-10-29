<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 計量ログの修正履歴テーブル production_log_edit_logs を作成する。
 * 修正者の名前を別途 editor_name に残すのは、アカウントが削除されても履歴で誰が修正したか分かるようにするため。
 * before_data / after_data には修正前後の内容を JSON 形式で保存する。
 */
class CreateProductionLogEditLogsTable extends Migration
{
    public function up()
    {
        Schema::create('production_log_edit_logs', function (Blueprint $table) {
            $table->id();
            // 親のログが削除されたら履歴も一緒に削除する
            $table->foreignId('production_log_id')->constrained()->cascadeOnDelete();
            // 修正者のアカウントが削除された場合は null にする（履歴自体は残す）
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
