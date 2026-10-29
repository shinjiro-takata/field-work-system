<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * レジン使用管理に必要な3つのテーブルを作成するマイグレーション
 * （マイグレーション = DBの構造を変更する手順をコードで管理する仕組み）。
 *   - resin_usage_settings: 管理する2種類の材料の設定（1行のみ）
 *   - resin_usage_records : 日ごとの使用記録（1日1件）
 *   - resin_usage_items   : 記録に属する、材料ごとの在庫計算の明細
 */
class CreateResinUsageTables extends Migration
{
    /** テーブルを作成する（migrate 実行時に呼ばれる） */
    public function up()
    {
        // 材料や更新者が削除されても設定自体は残す（nullOnDelete: 参照先が消えたら null にする）
        Schema::create('resin_usage_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->foreignId('material_one_id')->nullable()->constrained('materials')->nullOnDelete();
            $table->foreignId('material_two_id')->nullable()->constrained('materials')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // usage_date の unique により、同じ日付の記録は1件しか作れない
        Schema::create('resin_usage_records', function (Blueprint $table) {
            $table->id();
            $table->date('usage_date')->unique();
            $table->string('operator_name');
            $table->string('supervisor_name')->nullable();
            $table->dateTime('supervisor_confirmed_at')->nullable();
            $table->timestamps();
        });

        // 数量は小数第3位まで。記録を消したら明細も消える（cascadeOnDelete）が、
        // 使われている材料は削除できない（restrictOnDelete）。
        // 在庫の関係: opening_stock + replenished_quantity - used_quantity = calculated_stock、
        // actual_stock - calculated_stock = adjustment_quantity（差異）、closing_stock は翌日の前回在庫になる。
        Schema::create('resin_usage_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resin_usage_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->decimal('opening_stock', 12, 3);
            $table->decimal('used_quantity', 12, 3)->default(0);
            $table->decimal('replenished_quantity', 12, 3)->default(0);
            $table->decimal('calculated_stock', 12, 3);
            $table->decimal('actual_stock', 12, 3);
            $table->decimal('adjustment_quantity', 12, 3)->default(0);
            $table->string('adjustment_reason')->nullable();
            $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('closing_stock', 12, 3);
            $table->timestamps();

            // 同じ記録に同じ材料の明細を2つ作れないようにする
            $table->unique(['resin_usage_record_id', 'material_id']);
        });
    }

    /** テーブルを削除して元に戻す（rollback 時に呼ばれる）。外部キーの子→親の順に消す。 */
    public function down()
    {
        Schema::dropIfExists('resin_usage_items');
        Schema::dropIfExists('resin_usage_records');
        Schema::dropIfExists('resin_usage_settings');
    }
}
