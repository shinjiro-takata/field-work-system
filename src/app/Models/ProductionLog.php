<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 計量ログ（材料を実際に量った1回分の記録）を表すモデル。テーブル: production_logs
 */
class ProductionLog extends Model
{
    use HasFactory;

    // まとめて保存（create / update）してよい項目
    protected $fillable = [
        'user_id',
        'recipe_id',
        'weighed_at',
        'notes',
    ];

    /** このログで使ったレシピ（多対1）。 */
    public function recipe()
    {
        return $this->belongsTo(Recipe::class);
    }

    /** このログを登録したユーザー（多対1）。 */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** このログの修正履歴（1対多）。 */
    public function editLogs()
    {
        return $this->hasMany(ProductionLogEditLog::class);
    }

    /**
     * このログで量った材料（多対多）。
     * 中間テーブル（ピボットテーブル）production_log_materials に、材料ごとの実測量 actual_quantity を持つ。
     */
    public function materials()
    {
        return $this->belongsToMany(Material::class, 'production_log_materials')->withPivot('actual_quantity');
    }
}
