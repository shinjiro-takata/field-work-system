<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 計量ログの削除履歴（いつ・誰が・どの内容のログを削除したか）。
 * 削除したログ自体は消えるため、削除前の内容を deleted_data に JSON で保存している。
 */
class ProductionLogDeletion extends Model
{
    protected $fillable = [
        'deleted_by',
        'deleted_by_name',
        'recipe_name',
        'registered_by_name',
        'weighed_at',
        'deleted_data',
    ];

    protected $casts = [
        'weighed_at' => 'datetime',
        'deleted_data' => 'array',
    ];

    /**
     * 削除前の備考と材料ごとの実測量を、画面表示用の文章の配列にして返す。
     */
    public function contentDescriptions(): array
    {
        $lines = [];

        $notes = $this->deleted_data['notes'] ?? '';
        if ($notes !== '' && $notes !== null) {
            $lines[] = '備考：' . $notes;
        }

        foreach ($this->deleted_data['materials'] ?? [] as $material) {
            $lines[] = $material['name'] . '：' . number_format($material['actual_quantity'], 3) . ' ' . $material['unit'];
        }

        return $lines;
    }
}
