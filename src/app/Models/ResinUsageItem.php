<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * レジン使用記録の明細（1日・材料1種類ぶん）を表すモデル。
 * 前回在庫 + 補充数 - 使用量 = 計算在庫、実在庫 - 計算在庫 = 差異（adjustment_quantity）。
 */
class ResinUsageItem extends Model
{
    // 一括保存（create / fill など）を許可するカラム
    protected $fillable = [
        'material_id',
        'opening_stock',
        'used_quantity',
        'replenished_quantity',
        'calculated_stock',
        'actual_stock',
        'adjustment_quantity',
        'adjustment_reason',
        'reconciled_by',
        'closing_stock',
    ];

    /** この明細が属する日次記録（親） */
    public function record()
    {
        return $this->belongsTo(ResinUsageRecord::class, 'resin_usage_record_id');
    }

    /** この明細の対象の材料 */
    public function material()
    {
        return $this->belongsTo(Material::class);
    }
}
