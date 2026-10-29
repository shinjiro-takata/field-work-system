<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * レジン使用記録（1日1件）を表すモデル。
 * 担当者名と責任者の確認情報を持ち、材料ごとの明細は items にぶら下がる。
 */
class ResinUsageRecord extends Model
{
    // 一括保存（create / update など）を許可するカラム
    protected $fillable = [
        'usage_date',
        'operator_name',
        'supervisor_name',
        'supervisor_confirmed_at',
    ];

    // 日付として扱う（Carbon オブジェクトに自動変換される）カラム
    protected $dates = ['usage_date', 'supervisor_confirmed_at'];

    /** この日の材料ごとの明細（1対多） */
    public function items()
    {
        return $this->hasMany(ResinUsageItem::class);
    }
}
