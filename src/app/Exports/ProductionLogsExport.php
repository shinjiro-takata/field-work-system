<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;

/**
 * 計量ログをExcel出力するためのクラス（FromArray = 行の配列をそのままシートにする方式）。
 * 出力形式: 1行目=見出し、続いてログ1件=1行、最終行=材料ごとの合計。
 */
class ProductionLogsExport implements FromArray
{
    private Collection $logs;
    private Collection $allMaterials;

    /**
     * @param Collection $logs         出力する計量ログ（材料・レシピを読み込み済みのもの）
     * @param Collection $allMaterials 列として並べる全材料
     */
    public function __construct(Collection $logs, Collection $allMaterials)
    {
        $this->logs = $logs;
        $this->allMaterials = $allMaterials;
    }

    /** シートの全行を二次元配列で返す。 */
    public function array(): array
    {
        $rows = [];

        // 見出し行: 固定3列 + 材料名の列
        $header = ['日時', 'レシピ', '備考'];
        foreach ($this->allMaterials as $material) {
            $header[] = $material->name;
        }
        $rows[] = $header;

        // ログ1件ごとの行。そのログで使っていない材料は 0 にする
        foreach ($this->logs as $log) {
            $row = [
                (string) $log->weighed_at,
                optional($log->recipe)->name,
                $log->notes,
            ];

            foreach ($this->allMaterials as $material) {
                $row[] = round($this->actualQuantity($log, $material), 3);
            }

            $rows[] = $row;
        }

        // 最終行: 材料ごとの実測量の合計
        $totalRow = ['合計', '', ''];
        foreach ($this->allMaterials as $material) {
            $total = $this->logs->sum(function ($log) use ($material) {
                return $this->actualQuantity($log, $material);
            });
            $totalRow[] = round($total, 3);
        }
        $rows[] = $totalRow;

        return $rows;
    }

    /** 指定したログでの、指定した材料の実測量を返す（そのログで使っていなければ 0）。 */
    private function actualQuantity($log, $material): float
    {
        $matchedMaterial = $log->materials->firstWhere('id', $material->id);

        return $matchedMaterial ? (float) $matchedMaterial->pivot->actual_quantity : 0;
    }
}
