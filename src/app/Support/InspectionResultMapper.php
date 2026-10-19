<?php

namespace App\Support;

class InspectionResultMapper
{
    /**
     * 既知の点検項目だけを、データベース保存用の結果配列へ変換する。
     *
     * @param array<string, array{status?: 'ok'|'ng'|null, pressure?: int|float|string|null, value?: int|float|string|null, action?: string|null}> $submitted
     * @param array<string, array<string, mixed>>|null $items
     * @return array<string, mixed>
     */
    public function map(array $submitted, ?array $items = null): array
    {
        $results = [];
        $items = $items ?? EquipmentChecklist::items();

        foreach ($items as $id => $item) {
            $value = $submitted[$id] ?? [];

            if (($item['type'] ?? null) === 'pressure') {
                if (isset($value['pressure']) && $value['pressure'] !== '') {
                    $results[$id] = ['pressure' => (string) $value['pressure']];
                }
            } elseif (($item['type'] ?? null) === 'oil') {
                // 選択肢が1つだけの機器は、その作業を自動で採用する。
                $actions = $item['actions'] ?? [];
                $action = $value['action'] ?? (count($actions) === 1 ? $actions[0] : null);
                if (isset($value['value']) && $value['value'] !== '' && in_array($action, $actions, true)) {
                    $results[$id] = ['action' => $action, 'value' => (string) $value['value']];
                }
            } elseif (($item['type'] ?? null) === 'numeric') {
                if (isset($value['value']) && $value['value'] !== '') {
                    $results[$id] = ['value' => (string) $value['value']];
                }
            } elseif (isset($value['status']) && in_array($value['status'], ['ok', 'ng'], true)) {
                $results[$id] = ['status' => $value['status']];
            }
        }

        return $results;
    }
}
