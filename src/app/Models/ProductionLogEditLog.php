<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 計量ログの修正履歴（誰がいつ何をどう変えたか）を表すモデル。テーブル: production_log_edit_logs
 */
class ProductionLogEditLog extends Model
{
    // まとめて保存してよい項目。before_data / after_data は修正前後の内容
    protected $fillable = [
        'production_log_id',
        'editor_id',
        'editor_name',
        'before_data',
        'after_data',
    ];

    // DBには JSON 文字列で保存し、読み出すときは配列に自動変換する
    protected $casts = [
        'before_data' => 'array',
        'after_data' => 'array',
    ];

    /** 修正元の計量ログ（多対1）。 */
    public function productionLog()
    {
        return $this->belongsTo(ProductionLog::class);
    }

    /**
     * 修正前後（before_data と after_data）を比べて、画面表示用の「変更内容」の文章を配列で返す。
     * 例: ['計量日時：2026-10-01 10:00 → 2026-10-01 11:00', '材料A：1.000 → 2.000 kg']
     */
    public function changeDescriptions(): array
    {
        $before = $this->before_data;
        $after = $this->after_data;
        $changes = [];

        if ($before['weighed_at'] !== $after['weighed_at']) {
            $changes[] = '計量日時：' . $before['weighed_at'] . ' → ' . $after['weighed_at'];
        }

        // 備考は空のとき「（なし）」と表示する
        $beforeNotes = $before['notes'] ?? '';
        $afterNotes = $after['notes'] ?? '';
        if ($beforeNotes !== $afterNotes) {
            $changes[] = '備考：' . ($beforeNotes !== '' ? $beforeNotes : '（なし）')
                . ' → ' . ($afterNotes !== '' ? $afterNotes : '（なし）');
        }

        // 材料は material_id をキーにして、修正前後を突き合わせる
        $beforeMaterials = collect($before['materials'])->keyBy('material_id');
        $afterMaterials = collect($after['materials'])->keyBy('material_id');

        // 修正後にある材料: 新しく追加されたか、量が変わったか
        foreach ($after['materials'] as $afterMaterial) {
            $beforeMaterial = $beforeMaterials->get($afterMaterial['material_id']);
            if (!$beforeMaterial) {
                $changes[] = $afterMaterial['name'] . '：追加 ' . number_format($afterMaterial['actual_quantity'], 3) . ' ' . $afterMaterial['unit'];
            } elseif ((float) $beforeMaterial['actual_quantity'] !== (float) $afterMaterial['actual_quantity']) {
                $changes[] = $afterMaterial['name'] . '：' . number_format($beforeMaterial['actual_quantity'], 3) . ' → ' . number_format($afterMaterial['actual_quantity'], 3) . ' ' . $afterMaterial['unit'];
            }
        }

        // 修正前にあって修正後に無い材料: 削除された
        foreach ($before['materials'] as $beforeMaterial) {
            if (!$afterMaterials->has($beforeMaterial['material_id'])) {
                $changes[] = $beforeMaterial['name'] . '：削除（' . number_format($beforeMaterial['actual_quantity'], 3) . ' ' . $beforeMaterial['unit'] . '）';
            }
        }

        return $changes;
    }
}
