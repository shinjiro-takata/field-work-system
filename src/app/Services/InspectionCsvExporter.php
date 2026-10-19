<?php

namespace App\Services;

use App\Models\InspectionRecord;
use App\Support\EquipmentChecklist;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InspectionCsvExporter
{
    /**
     * 保存済みの点検結果を、Excelで開けるCSVとしてダウンロードする。
     */
    public function download(): StreamedResponse
    {
        $records = InspectionRecord::ofType('daily')->orderBy('inspection_date')->get();
        $items = EquipmentChecklist::items();

        return response()->streamDownload(function () use ($records, $items): void {
            $output = fopen('php://output', 'w');
            if ($output === false) {
                throw new \RuntimeException('CSV出力先を開けませんでした。');
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [
                '点検日',
                '点検担当者',
                '点検名称',
                '点検箇所',
                '点検項目',
                '方法',
                '結果',
                '圧力値（MPa）',
                '全体メモ',
                '最終編集者',
            ]);

            foreach ($records as $record) {
                foreach ($items as $id => $item) {
                    $result = $record->results[$id] ?? [];
                    $status = ($result['status'] ?? null) === 'ok'
                        ? '良好'
                        : (($result['status'] ?? null) === 'ng' ? '要対応' : (isset($result['pressure']) ? '数値記録' : '未点検'));
                    $row = [
                        $record->inspection_date->format('Y-m-d'),
                        $record->inspector,
                        $item['group'],
                        $item['location'],
                        $item['item'],
                        $item['method'],
                        $status,
                        $result['pressure'] ?? '',
                        $record->note,
                        $record->last_edited_by,
                    ];

                    foreach ([1, 2, 3, 4, 5, 6, 8, 9] as $textColumn) {
                        $row[$textColumn] = $this->safeTextCell($row[$textColumn]);
                    }

                    fputcsv($output, $row);
                }
            }

            fclose($output);
        }, '設備点検履歴_' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * 表計算ソフトでテキストが数式として実行されないようにする。
     */
    private function safeTextCell(?string $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }
}
