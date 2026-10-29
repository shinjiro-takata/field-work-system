<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 計量ログの削除履歴テーブル production_log_deletions を作成する。
 * 削除したログ本体は消えるため、外部キーは持たず、削除時点の内容を JSON で丸ごと保存する。
 * deleted_by_name は、アカウントが削除されても誰が削除したか分かるように名前を直接残す。
 */
class CreateProductionLogDeletionsTable extends Migration
{
    public function up()
    {
        Schema::create('production_log_deletions', function (Blueprint $table) {
            $table->id();
            // 削除した人のアカウントが消えた場合は null にする（履歴自体は残す）
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('deleted_by_name');
            $table->string('recipe_name');
            $table->string('registered_by_name')->nullable(); // 元のログを登録した人
            $table->dateTime('weighed_at');
            $table->json('deleted_data'); // 削除前の日時・備考・材料と実測量
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('production_log_deletions');
    }
}
