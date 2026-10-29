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
        // 点検記録本体を保存する。点検日は重複登録できないよう一意にする。
        Schema::create('inspection_records', function (Blueprint $table) {
            // 点検記録を識別するID。
            $table->id();
            // 点検日。初期状態では同じ日付の記録を1件に制限する。
            $table->date('inspection_date')->unique();
            // 点検を担当した人の名前。
            $table->string('inspector', 80);
            // 申し送りなどを記録する任意のメモ。
            $table->text('note')->nullable();
            // 各チェック項目の結果をJSON形式で保存する。
            $table->json('results');
            // 最後に編集した人。編集前は空欄。
            $table->string('last_edited_by', 80)->nullable();
            // 作成日時と最終更新日時。
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
