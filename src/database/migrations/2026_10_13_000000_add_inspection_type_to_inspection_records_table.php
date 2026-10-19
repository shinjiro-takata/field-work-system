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
        Schema::table('inspection_records', function (Blueprint $table) {
            $table->string('inspection_type', 30)->default('daily')->after('id');
            $table->dropUnique('inspection_records_inspection_date_unique');
            $table->unique(['inspection_type', 'inspection_date']);
        });
    }

    /**
     * マイグレーションを戻す際、一意制約を戻してカラムを削除する。
     */
    public function down(): void
    {
        Schema::table('inspection_records', function (Blueprint $table) {
            $table->dropUnique(['inspection_type', 'inspection_date']);
            $table->unique('inspection_date');
            $table->dropColumn('inspection_type');
        });
    }
}
