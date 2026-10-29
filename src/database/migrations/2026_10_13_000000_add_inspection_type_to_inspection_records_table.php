<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddInspectionTypeToInspectionRecordsTable extends Migration
{
    /**
     * 点検種別カラムを追加し、種別ごとの日付一意制約に変更する。
     */
    public function up(): void
    {
        // 日常・週次など複数の種別を同じテーブルに保存できるようにする。
        Schema::table('inspection_records', function (Blueprint $table) {
            // 点検種別。既存の記録は日常点検として扱う。
            $table->string('inspection_type', 30)->default('daily')->after('id');
            // 日付だけの重複制限を外し、種別ごとの重複制限に置き換える。
            $table->dropUnique('inspection_records_inspection_date_unique');
            // 同じ種別・同じ日付の記録だけを重複不可にする。
            $table->unique(['inspection_type', 'inspection_date']);
        });
    }

    /**
     * マイグレーションを戻す際、一意制約を戻してカラムを削除する。
     */
    public function down(): void
    {
        // 種別ごとの制限を外し、変更前の制約と構造に戻す。
        Schema::table('inspection_records', function (Blueprint $table) {
            // 種別と日付の組み合わせに対する制約を外す。
            $table->dropUnique(['inspection_type', 'inspection_date']);
            // 変更前と同じく、日付を重複不可にする。
            $table->unique('inspection_date');
            // 追加した点検種別カラムを削除する。
            $table->dropColumn('inspection_type');
        });
    }
}
