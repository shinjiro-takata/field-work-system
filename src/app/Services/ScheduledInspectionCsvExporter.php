<?php

namespace App\Services;

use App\Models\InspectionRecord;
use App\Support\InspectionTypes;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 週次・月次・定期点検の記録をCSV（表計算ソフトで扱える表形式）に書き出す。
 */
class ScheduledInspectionCsvExporter
{
    /**
     * 指定した種別の保存済み結果を、Excelで開けるCSV（表計算ソフト用の表形式）としてダウンロードする。
     */
    public function download(string $type): StreamedResponse
    {
        $records = InspectionRecord::ofType($type)->orderBy('inspection_date')->get();
        $items = InspectionTypes::itemsFor($type);
        // 油の補充・交換項目では、点検項目名と結果の列は不要。
        $hasOil = collect($items)->contains(fn(array $item): bool => ($item['type'] ?? null) === 'oil');

        return response()->streamDownload(function () use ($records, $items, $hasOil): void {
            $output = fopen('php://output', 'w');
            if ($output === false) {
                throw new \RuntimeException('CSV出力先を開けませんでした。');
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $this->headers($hasOil));

            foreach ($records as $record) {
                foreach ($items as $id => $item) {
                    $result = $record->results[$id] ?? [];
                    $status = $this->statusLabel($result);
                    $action = \App\Support\PeriodicChecklist::actionLabels()[$result['action'] ?? ''] ?? '';
                    $row = [
                        $record->inspection_date->format('Y-m-d'),
                        $record->inspector,
                        $item['group'],
                        $item['location'],
                        ...($hasOil ? [] : [$item['item']]),
                        $item['method'],
                        ...($hasOil ? [] : [$status]),
                        $action,
                        $result['value'] ?? '',
                        isset($result['value']) ? ($item['unit'] ?? '') : '',
                        $record->note ?? '',
                        $record->last_edited_by ?? '',
                    ];

                    fputcsv(
                        $output,
                        array_map(fn(string $value): string => $this->sanitizeForSpreadsheets($value), $row)
                    );
                }
            }

            fclose($output);
        }, $type . '-inspections-' . now()->format('Ymd') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * CSVの見出し行を作る。油の補充・交換だけの場合は不要な列を省く。
     *
     * @return list<string>
     */
    private function headers(bool $hasOil): array
    {
        return [
            '点検日',
            '点検担当者',
            '点検名称',
            '点検箇所',
            ...($hasOil ? [] : ['点検項目']),
            '方法',
            ...($hasOil ? [] : ['結果']),
            '作業',
            '測定値',
            '単位',
            '全体メモ',
            '最終編集者',
        ];
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

        return isset($result['value']) ? '数値記録' : '未点検';
    }

    /**
     * 入力内容がExcelで数式として実行されないよう、危険な先頭文字を無害化する。
     */
    private function sanitizeForSpreadsheets(string $value): string
    {
        return preg_match('/^[=\+\-@\t\r]/', $value) === 1
            ? "'" . $value
            : $value;
    }
}
