<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * レジン管理の設定を表すモデル（1行のみ、id = 1）。
 * 管理する2種類の材料（one / two）とそれぞれの初期在庫、最後に更新したユーザーを持つ。
 */
class ResinUsageSetting extends Model
{
    // 一括保存（fill など）を許可するカラム
    protected $fillable = [
        'material_one_id',
        'material_two_id',
        'material_one_initial_stock',
        'material_two_initial_stock',
        'updated_by',
    ];

    /** 1つ目の材料 */
    public function materialOne()
    {
        return $this->belongsTo(Material::class, 'material_one_id');
    }

    /** 2つ目の材料 */
    public function materialTwo()
    {
        return $this->belongsTo(Material::class, 'material_two_id');
    }
}
