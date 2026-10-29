<?php

namespace App\Services;

use App\Models\InspectionRecord;
use App\Support\EquipmentChecklist;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 日常点検の記録をCSV（表計算ソフトで扱える表形式）に書き出す。
 */
class InspectionCsvExporter
{
    /**
     * 保存済みの点検結果を、Excelで開けるCSV（表計算ソフト用の表形式）としてダウンロードする。
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
                    $status = $this->statusLabel($result);
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
     * 保存された値から、CSVに表示する点検状態の文言を選ぶ。
     *
     * @param array<string, mixed> $result
     */
    private function statusLabel(array $result): string
    {
        if (($result['status'] ?? null) === 'ok') {
            return '良好';
        }

        if (($result['status'] ?? null) === 'ng') {
            return '要対応';
        }

        return isset($result['pressure']) ? '数値記録' : '未点検';
    }

    /**
     * 入力内容が表計算ソフトで数式として実行されないよう、危険な先頭文字を無害化する。
     */
    private function safeTextCell(?string $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }
}
