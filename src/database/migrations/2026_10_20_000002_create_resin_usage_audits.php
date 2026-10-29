<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * レジン使用記録の監査履歴（誰がいつ何を変えたか）を保存するテーブルを作るマイグレーション
 * （マイグレーション = DBの構造変更をコードで管理する仕組み）。
 * 記録を削除しても履歴が残るよう、resin_usage_records への外部キーは持たず usage_date だけを保存する。
 * changed_by_name は、ユーザーが削除されても誰が操作したか分かるように名前を直接残す。
 */
class CreateResinUsageAudits extends Migration
{
    /** テーブルを作成する */
    public function up()
    {
        Schema::create('resin_usage_audits', function (Blueprint $table) {
            $table->id();
            $table->string('action'); // 操作の種類（updated / deleted など）
            $table->date('usage_date'); // 変更された記録の日付
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('changed_by_name');
            $table->json('before_data')->nullable(); // 変更前の内容
            $table->json('after_data')->nullable(); // 変更後の内容（削除時は空）
            $table->timestamps();

            // 日付ごとの履歴を新しい順に探すための索引（検索を速くする仕組み）
            $table->index(['usage_date', 'created_at']);
        });
    }

    /** テーブルを削除して元に戻す */
    public function down()
    {
        Schema::dropIfExists('resin_usage_audits');
    }
}
