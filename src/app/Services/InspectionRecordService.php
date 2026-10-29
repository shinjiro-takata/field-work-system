<?php

namespace App\Services;

use App\Models\InspectionRecord;
use App\Support\InspectionTypes;
use Illuminate\Support\Facades\DB;

/**
 * 点検記録の作成・更新をまとめ、画面から受け取った保存処理を担当するサービスクラス。
 * サービスクラスは、複数の保存手順をまとめて呼び出すためのクラス。
 */
class InspectionRecordService
{
    /**
     * 検証済みデータから新しい点検記録を作成する。
     *
     * @param array{
     *     inspection_type?: string,
     *     inspection_date: string,
     *     inspector: string,
     *     note?: string|null,
     *     results: array<string, mixed>
     * } $data
     */
    public function create(array $data): InspectionRecord
    {
        return InspectionRecord::create([
            'inspection_type' => $data['inspection_type'] ?? 'daily',
            'inspection_date' => $data['inspection_date'],
            'inspector' => $data['inspector'],
            'note' => $data['note'] ?? null,
            'results' => $data['results'],
        ]);
    }

    /**
     * トランザクション（複数のDB操作をまとめ、失敗時はすべて取り消す仕組み）で記録と編集履歴を保存する。
     *
     * @param array{
     *     editor_name: string,
     *     note?: string|null,
     *     results: array<string, array{status: 'ok'|'ng'}|array{pressure: numeric-string}>
     * } $data
     */
    public function update(InspectionRecord $inspection, array $data): InspectionRecord
    {
        return DB::transaction(function () use ($inspection, $data): InspectionRecord {
            $record = InspectionRecord::whereKey($inspection->getKey())->lockForUpdate()->firstOrFail();
            $newValues = [
                'note' => $data['note'] ?? null,
                'results' => $data['results'],
            ];
            $changes = $this->changedFields($record, $newValues);
            $editor = trim($data['editor_name']);

            $record->fill($newValues);
            $record->last_edited_by = $editor;
            $record->save();

            $record->editLogs()->create([
                'editor_name' => $editor,
                'changes' => $changes,
                'created_at' => now(),
            ]);

            return $record;
        });
    }

    /**
     * 更新前と更新後を比べ、変わった項目だけを編集履歴に残す。
     *
     * @param array{
     *     note: string|null,
     *     results: array<string, mixed>
     * } $newValues
     * @return array<string, mixed>
     */
    private function changedFields(InspectionRecord $record, array $newValues): array
    {
        $changes = [];

        foreach ($newValues as $field => $newValue) {
            $oldValue = $record->{$field};
            if ($field === 'results') {
                $oldValue = $oldValue ?: [];
                if ($oldValue != $newValue) {
                    $changes[$field] = $this->resultChanges($record, $oldValue, $newValue);
                }
            } elseif ($oldValue !== $newValue) {
                $changes[$field] = ['変更前' => $oldValue, '変更後' => $newValue];
            }
        }

        return $changes;
    }

    /**
     * 点検項目ごとの変更前後を比較し、画面で読みやすい編集履歴に整える。
     *
     * @param array<string, mixed> $oldResults
     * @param array<string, mixed> $newResults
     * @return array<string, array{変更前: string, 変更後: string}>
     */
    private function resultChanges(InspectionRecord $record, array $oldResults, array $newResults): array
    {
        $items = InspectionTypes::itemsFor($record->inspection_type);

        $changes = [];
        foreach ($items as $id => $item) {
            $oldValue = $oldResults[$id] ?? null;
            $newValue = $newResults[$id] ?? null;
            if ($oldValue != $newValue) {
                $unit = $item['unit'] ?? (($item['type'] ?? null) === 'pressure' ? 'MPa' : '');
                $label = $item['group'] . ' / ' . $item['location'];
                if (($item['type'] ?? null) !== 'oil') {
                    $label .= ' / ' . $item['item'];
                }
                $changes[$label] = [
                    '変更前' => $this->describeResult($oldValue, $unit),
                    '変更後' => $this->describeResult($newValue, $unit),
                ];
            }
        }

        return $changes;
    }

    /**
     * 1項目の点検結果を、編集履歴に表示する文字列に変換する。
     *
     * @param array<string, mixed>|null $result
     */
    private function describeResult(?array $result, string $unit = ''): string
    {
        if (!$result) {
            return '未入力';
        }

        if (isset($result['pressure'])) {
            return $result['pressure'] . ' MPa';
        }

        if (isset($result['value'])) {
            $action = \App\Support\PeriodicChecklist::actionLabels()[$result['action'] ?? ''] ?? null;

            return ($action ? $action . ' ' : '') . $result['value'] . ($unit ? ' ' . $unit : '');
        }

        return ($result['status'] ?? null) === 'ok' ? '良好' : '要対応';
    }
}
