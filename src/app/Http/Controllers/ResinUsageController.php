<?php

namespace App\Http\Controllers;

use App\Models\ResinUsageAudit;
use App\Models\ResinUsageRecord;
use App\Models\ResinUsageSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResinUsageController extends Controller
{
    public function index()
    {
        $records = ResinUsageRecord::with('items.material')
            ->orderByDesc('usage_date')
            ->paginate(20);
        $audits = ResinUsageAudit::orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20, ['*'], 'audit_page');
        $setting = $this->configuredSetting();

        return view('resin-usage.history', compact('records', 'setting', 'audits'));
    }

    public function create(Request $request)
    {
        $setting = $this->requireSetting();
        if (!$setting) {
            return redirect()->route('resin-usage.index')
                ->with('status', '使用する2種類のレジンを管理者が設定してください。');
        }

        $date = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ])['date'] ?? now()->toDateString();
        $record = ResinUsageRecord::whereDate('usage_date', $date)->first();
        if ($record) {
            return redirect()->route('resin-usage.history')
                ->with('status', 'この日付の記録はすでに登録されています。修正する場合は履歴から選択してください。');
        }

        $materials = $this->configuredMaterials($setting);
        $usage = $this->usageForDate($date, $materials->pluck('id')->all());
        $previousStocks = $this->previousStocks($date, $materials);
        $initialStocks = $this->initialStocks($setting);
        $openingStocks = array_replace($initialStocks, $previousStocks);
        $initialStockMaterials = array_diff(array_keys($initialStocks), array_keys($previousStocks));
        $operatorName = old('operator_name', auth()->user()->name);

        return view('resin-usage.form', compact('date', 'materials', 'usage', 'openingStocks', 'initialStockMaterials', 'operatorName'))
            ->with('record', null);
    }

    public function store(Request $request)
    {
        $setting = $this->requireSetting();
        if (!$setting) {
            return redirect()->route('resin-usage.index')
                ->with('status', '使用する2種類のレジンを管理者が設定してください。');
        }

        $materials = $this->configuredMaterials($setting);
        $date = $request->validate([
            'usage_date' => ['required', 'date_format:Y-m-d'],
        ])['usage_date'];
        $usage = $this->usageForDate($date, $materials->pluck('id')->all());
        $openingStocks = array_replace(
            $this->initialStocks($setting),
            $this->previousStocks($date, $materials)
        );
        $validated = $this->validateRecord($request, $materials, $openingStocks, $usage);

        if (ResinUsageRecord::whereDate('usage_date', $date)->exists()) {
            throw ValidationException::withMessages(['usage_date' => 'この日付の記録はすでに登録されています。']);
        }

        $latestDate = ResinUsageRecord::max('usage_date');
        if ($latestDate && $date < $latestDate) {
            throw ValidationException::withMessages(['usage_date' => '過去の日付に追加する場合は、履歴から既存記録を修正してください。']);
        }

        DB::transaction(function () use ($validated, $materials, $date, $openingStocks, $usage) {
            $record = ResinUsageRecord::create([
                'usage_date' => $date,
                'operator_name' => $validated['operator_name'],
                'supervisor_name' => $this->confirmedSupervisorName($validated),
                'supervisor_confirmed_at' => $this->confirmationTime($validated),
            ]);

            foreach ($materials as $material) {
                $item = $this->itemValues(
                    $material->id,
                    $openingStocks[$material->id] ?? $validated['items'][$material->id]['opening_stock'],
                    $usage[$material->id] ?? 0,
                    $validated['items'][$material->id]
                );
                $record->items()->create($item);
            }
        });

        return redirect()->route('resin-usage.history')->with('status', 'レジン使用量を登録しました。');
    }

    public function edit(ResinUsageRecord $record)
    {
        $setting = $this->requireSetting();
        if (!$setting) {
            return redirect()->route('resin-usage.index')
                ->with('status', '使用する2種類のレジンを管理者が設定してください。');
        }

        $record->load('items.material');
        $materials = $record->items->pluck('material')->filter()->values();
        $date = $record->usage_date->toDateString();
        $usage = $this->usageForDate($date, $materials->pluck('id')->all());
        $openingStocks = $this->previousStocks($date, $materials, $record->id);
        foreach ($record->items as $item) {
            $openingStocks[$item->material_id] = $openingStocks[$item->material_id] ?? (float) $item->opening_stock;
        }
        $initialStockMaterials = [];
        $operatorName = old('operator_name', $record->operator_name);

        return view('resin-usage.form', compact('date', 'materials', 'usage', 'openingStocks', 'initialStockMaterials', 'operatorName', 'record'));
    }

    public function update(Request $request, ResinUsageRecord $record)
    {
        $setting = $this->requireSetting();
        if (!$setting) {
            return redirect()->route('resin-usage.index')
                ->with('status', '使用する2種類のレジンを管理者が設定してください。');
        }

        $record->load('items.material');
        $materials = $record->items->pluck('material')->filter()->values();
        $date = $record->usage_date->toDateString();
        $usage = $this->usageForDate($date, $materials->pluck('id')->all());
        $openingStocks = $this->previousStocks($date, $materials, $record->id);
        foreach ($record->items as $item) {
            $openingStocks[$item->material_id] = $openingStocks[$item->material_id] ?? (float) $item->opening_stock;
        }
        $validated = $this->validateRecord($request, $materials, $openingStocks, $usage);
        $before = $this->recordSnapshot($record);

        DB::transaction(function () use ($record, $validated, $materials, $date, $openingStocks, $usage, $before) {
            $record->update([
                'operator_name' => $validated['operator_name'],
                'supervisor_name' => $this->confirmedSupervisorName($validated),
                'supervisor_confirmed_at' => $this->confirmationTime($validated),
            ]);

            foreach ($materials as $material) {
                $item = $record->items()->firstOrNew(['material_id' => $material->id]);
                $item->fill($this->itemValues(
                    $material->id,
                    $openingStocks[$material->id] ?? $validated['items'][$material->id]['opening_stock'],
                    $usage[$material->id] ?? 0,
                    $validated['items'][$material->id]
                ))->save();
            }

            $this->recalculateFollowingRecords($record);
            $record->load('items.material');
            $this->writeAudit('updated', $record->usage_date, $before, $this->recordSnapshot($record));
        });

        return redirect()->route('resin-usage.history')->with('status', 'レジン使用量を修正しました。');
    }

    public function destroy(ResinUsageRecord $record)
    {
        DB::transaction(function () use ($record) {
            $record->load('items.material');
            $before = $this->recordSnapshot($record);
            $usageDate = $record->usage_date->toDateString();
            $materialIds = $record->items->pluck('material_id')->all();
            $record->delete();
            $this->writeAudit('deleted', $usageDate, $before, null);
            $this->recalculateAfterDeletion($usageDate, $materialIds, $before);
        });

        return redirect()->route('resin-usage.history')->with('status', 'レジン使用量の記録を削除しました。');
    }

    private function validateRecord(Request $request, $materials, array $openingStocks, array $usage): array
    {
        $rules = [
            'operator_name' => ['required', 'string', 'max:255'],
            'supervisor_confirmed' => ['nullable', 'boolean'],
            'supervisor_name' => ['nullable', 'required_if:supervisor_confirmed,1', 'string', 'max:255'],
            'items' => ['required', 'array'],
        ];

        foreach ($materials as $material) {
            $rules['items.' . $material->id . '.replenished_quantity'] = ['required', 'numeric', 'min:0', 'max:999999999'];
            $rules['items.' . $material->id . '.actual_stock'] = ['required', 'numeric', 'min:0', 'max:999999999'];
            $rules['items.' . $material->id . '.adjustment_reason'] = ['nullable', 'string', 'max:255'];
            if (!array_key_exists($material->id, $openingStocks)) {
                $rules['items.' . $material->id . '.opening_stock'] = ['required', 'numeric', 'min:0', 'max:999999999'];
            }
        }

        $validated = $request->validate($rules);
        $expectedItemIds = $materials->pluck('id')->all();
        $submittedItemIds = array_keys($validated['items']);
        sort($expectedItemIds, SORT_NUMERIC);
        sort($submittedItemIds, SORT_NUMERIC);
        if ($submittedItemIds !== $expectedItemIds) {
            throw ValidationException::withMessages([
                'items' => '設定済みの2種類のレジンについて入力してください。',
            ]);
        }

        foreach ($materials as $material) {
            $item = $validated['items'][$material->id];
            $opening = $openingStocks[$material->id] ?? (float) $item['opening_stock'];
            $calculated = round($opening + (float) $item['replenished_quantity'] - (float) ($usage[$material->id] ?? 0), 3);
            $difference = round((float) $item['actual_stock'] - $calculated, 3);
            if ($difference != 0.0 && empty($item['adjustment_reason'])) {
                throw ValidationException::withMessages([
                    'items.' . $material->id . '.adjustment_reason' => '計算在庫と実在庫に差がある場合は、差異の理由を入力してください。',
                ]);
            }
        }

        return $validated;
    }

    private function itemValues($materialId, $opening, $used, array $input): array
    {
        $opening = round((float) $opening, 3);
        $used = round((float) $used, 3);
        $replenished = round((float) $input['replenished_quantity'], 3);
        $calculated = round($opening + $replenished - $used, 3);
        $actual = round((float) $input['actual_stock'], 3);
        $adjustment = round($actual - $calculated, 3);

        return [
            'material_id' => $materialId,
            'opening_stock' => $opening,
            'used_quantity' => $used,
            'replenished_quantity' => $replenished,
            'calculated_stock' => $calculated,
            'actual_stock' => $actual,
            'adjustment_quantity' => $adjustment,
            'adjustment_reason' => $adjustment == 0.0 ? null : $input['adjustment_reason'],
            'reconciled_by' => $adjustment == 0.0 ? null : auth()->id(),
            'closing_stock' => $actual,
        ];
    }

    private function recalculateFollowingRecords(ResinUsageRecord $record): void
    {
        $previousStocks = [];
        foreach ($record->items()->get() as $item) {
            $previousStocks[$item->material_id] = (float) $item->closing_stock;
        }

        $followingRecords = ResinUsageRecord::with('items')
            ->whereDate('usage_date', '>', $record->usage_date->toDateString())
            ->orderBy('usage_date')
            ->get();
        $trackedMaterialIds = $record->items->pluck('material_id')
            ->merge($followingRecords->flatMap(function ($followingRecord) {
                return $followingRecord->items->pluck('material_id');
            }))
            ->unique()
            ->values()
            ->all();

        foreach ($followingRecords as $followingRecord) {
            $itemsByMaterial = $followingRecord->items->keyBy('material_id');
            $before = $this->recordSnapshot($followingRecord);
            $usage = $this->usageForDate(
                $followingRecord->usage_date->toDateString(),
                $trackedMaterialIds
            );
            foreach ($trackedMaterialIds as $materialId) {
                if (!$itemsByMaterial->has($materialId)) {
                    unset($previousStocks[$materialId]);
                    continue;
                }

                $item = $itemsByMaterial->get($materialId);
                $opening = $previousStocks[$materialId] ?? (float) $item->opening_stock;
                $used = round((float) ($usage[$materialId] ?? 0), 3);
                $calculated = round($opening + (float) $item->replenished_quantity - $used, 3);
                $actual = (float) $item->actual_stock;
                $adjustment = round($actual - $calculated, 3);
                $adjustmentChanged = $adjustment != (float) $item->adjustment_quantity;

                $item->update([
                    'opening_stock' => $opening,
                    'used_quantity' => $used,
                    'calculated_stock' => $calculated,
                    'adjustment_quantity' => $adjustment,
                    'adjustment_reason' => $adjustment == 0.0
                        ? null
                        : ($adjustmentChanged
                            ? '前の記録の修正に伴う再計算'
                            : ($item->adjustment_reason ?: '前の記録の修正に伴う再計算')),
                    'reconciled_by' => $adjustment == 0.0
                        ? null
                        : ($adjustmentChanged ? auth()->id() : ($item->reconciled_by ?: auth()->id())),
                ]);
                $previousStocks[$materialId] = $actual;
            }
            $followingRecord->load('items.material');
            $after = $this->recordSnapshot($followingRecord);
            if ($before !== $after) {
                $this->writeAudit('updated', $followingRecord->usage_date, $before, $after);
            }
        }
    }

    private function recalculateAfterDeletion(string $deletedDate, array $materialIds, array $deletedSnapshot): void
    {
        $followingRecords = ResinUsageRecord::with('items.material')
            ->whereDate('usage_date', '>', $deletedDate)
            ->orderBy('usage_date')
            ->get();
        $trackedMaterialIds = array_values(array_unique(array_merge(
            $materialIds,
            $followingRecords->flatMap(function ($record) {
                return $record->items->pluck('material_id')->all();
            })->all()
        )));
        $previousStocks = $this->stocksBeforeDate($deletedDate, $trackedMaterialIds);
        foreach ($deletedSnapshot['items'] as $item) {
            if (!array_key_exists($item['material_id'] ?? null, $previousStocks)) {
                $previousStocks[$item['material_id']] = $item['opening_stock'];
            }
        }

        foreach ($followingRecords as $followingRecord) {
            $before = $this->recordSnapshot($followingRecord);
            $usage = $this->usageForDate($followingRecord->usage_date->toDateString(), $trackedMaterialIds);
            $itemsByMaterial = $followingRecord->items->keyBy('material_id');
            foreach ($trackedMaterialIds as $materialId) {
                if (!$itemsByMaterial->has($materialId)) {
                    unset($previousStocks[$materialId]);
                }
            }
            foreach ($followingRecord->items as $item) {
                $opening = $previousStocks[$item->material_id] ?? (float) $item->opening_stock;
                $used = round((float) ($usage[$item->material_id] ?? 0), 3);
                $calculated = round($opening + (float) $item->replenished_quantity - $used, 3);
                $actual = (float) $item->actual_stock;
                $adjustment = round($actual - $calculated, 3);
                $adjustmentChanged = $adjustment != (float) $item->adjustment_quantity;
                $item->update([
                    'opening_stock' => $opening,
                    'used_quantity' => $used,
                    'calculated_stock' => $calculated,
                    'adjustment_quantity' => $adjustment,
                    'adjustment_reason' => $adjustment == 0.0
                        ? null
                        : ($adjustmentChanged ? '前の記録の削除に伴う再計算' : ($item->adjustment_reason ?: '前の記録の削除に伴う再計算')),
                    'reconciled_by' => $adjustment == 0.0
                        ? null
                        : ($adjustmentChanged ? auth()->id() : ($item->reconciled_by ?: auth()->id())),
                ]);
                $previousStocks[$item->material_id] = $actual;
            }

            $followingRecord->load('items.material');
            $after = $this->recordSnapshot($followingRecord);
            if ($before !== $after) {
                $this->writeAudit('updated', $followingRecord->usage_date, $before, $after);
            }
        }
    }

    private function stocksBeforeDate(string $date, array $materialIds): array
    {
        $stocks = [];
        $items = DB::table('resin_usage_items')
            ->join('resin_usage_records', 'resin_usage_records.id', '=', 'resin_usage_items.resin_usage_record_id')
            ->whereDate('resin_usage_records.usage_date', '<', $date)
            ->whereIn('resin_usage_items.material_id', $materialIds)
            ->orderByDesc('resin_usage_records.usage_date')
            ->get(['resin_usage_items.material_id', 'resin_usage_items.closing_stock']);

        foreach ($items as $item) {
            if (!array_key_exists($item->material_id, $stocks)) {
                $stocks[$item->material_id] = (float) $item->closing_stock;
            }
        }

        return $stocks;
    }

    private function recordSnapshot(ResinUsageRecord $record): array
    {
        $record->loadMissing('items.material');

        return [
            'usage_date' => $record->usage_date->toDateString(),
            'operator_name' => $record->operator_name,
            'supervisor_name' => $record->supervisor_name,
            'supervisor_confirmed_at' => optional($record->supervisor_confirmed_at)->toDateTimeString(),
            'items' => $record->items->map(function ($item) {
                return [
                    'material_id' => $item->material_id,
                    'material' => optional($item->material)->name,
                    'unit' => optional($item->material)->unit,
                    'opening_stock' => (float) $item->opening_stock,
                    'used_quantity' => (float) $item->used_quantity,
                    'replenished_quantity' => (float) $item->replenished_quantity,
                    'calculated_stock' => (float) $item->calculated_stock,
                    'actual_stock' => (float) $item->actual_stock,
                    'adjustment_quantity' => (float) $item->adjustment_quantity,
                    'adjustment_reason' => $item->adjustment_reason,
                ];
            })->values()->all(),
        ];
    }

    private function writeAudit(string $action, $date, ?array $before, ?array $after): void
    {
        ResinUsageAudit::create([
            'action' => $action,
            'usage_date' => $date,
            'changed_by' => auth()->id(),
            'changed_by_name' => auth()->user()->name,
            'before_data' => $before,
            'after_data' => $after,
        ]);
    }

    private function usageForDate(string $date, array $materialIds): array
    {
        return DB::table('production_log_materials')
            ->join('production_logs', 'production_logs.id', '=', 'production_log_materials.production_log_id')
            ->whereDate('production_logs.weighed_at', $date)
            ->whereIn('production_log_materials.material_id', $materialIds)
            ->select('production_log_materials.material_id')
            ->selectRaw('SUM(production_log_materials.actual_quantity) as total_quantity')
            ->groupBy('production_log_materials.material_id')
            ->pluck('total_quantity', 'material_id')
            ->map(function ($quantity) {
                return (float) $quantity;
            })
            ->all();
    }

    private function previousStocks(string $date, $materials, $excludeRecordId = null): array
    {
        $query = DB::table('resin_usage_items')
            ->join('resin_usage_records', 'resin_usage_records.id', '=', 'resin_usage_items.resin_usage_record_id')
            ->whereDate('usage_date', '<', $date)
            ->whereIn('resin_usage_items.material_id', $materials->pluck('id'))
            ->orderByDesc('resin_usage_records.usage_date');
        if ($excludeRecordId) {
            $query->where('resin_usage_records.id', '!=', $excludeRecordId);
        }
        $stocks = [];
        foreach ($query->get([
            'resin_usage_items.material_id',
            'resin_usage_items.closing_stock',
        ]) as $item) {
            if (!array_key_exists($item->material_id, $stocks)) {
                $stocks[$item->material_id] = (float) $item->closing_stock;
            }
        }

        return $stocks;
    }

    private function configuredSetting()
    {
        $setting = ResinUsageSetting::with(['materialOne', 'materialTwo'])->find(1);
        if (!$setting
            || !$setting->materialOne
            || !$setting->materialTwo
            || is_null($setting->material_one_initial_stock)
            || is_null($setting->material_two_initial_stock)) {
            return null;
        }

        return $setting;
    }

    private function requireSetting()
    {
        return $this->configuredSetting();
    }

    private function configuredMaterials(ResinUsageSetting $setting)
    {
        return collect([$setting->materialOne, $setting->materialTwo])->filter()->values();
    }

    private function initialStocks(ResinUsageSetting $setting): array
    {
        return [
            $setting->material_one_id => (float) $setting->material_one_initial_stock,
            $setting->material_two_id => (float) $setting->material_two_initial_stock,
        ];
    }

    private function confirmedSupervisorName(array $validated)
    {
        return !empty($validated['supervisor_confirmed']) ? $validated['supervisor_name'] : null;
    }

    private function confirmationTime(array $validated)
    {
        return !empty($validated['supervisor_confirmed']) ? now() : null;
    }
}
