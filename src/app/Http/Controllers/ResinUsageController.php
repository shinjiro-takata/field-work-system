<?php

namespace App\Http\Controllers;

use App\Models\ResinUsageAudit;
use App\Models\ResinUsageRecord;
use App\Models\ResinUsageSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * レジン（2種類の材料）の日次使用量・在庫を管理するコントローラ。
 *
 * 在庫の考え方:
 *   前回在庫（opening_stock） + 補充数（replenished_quantity） - 使用量（used_quantity） = 計算在庫（calculated_stock）
 *   実在庫（actual_stock、実際に数えた量） - 計算在庫 = 差異（adjustment_quantity）
 * 差異がある日は理由の入力を必須とし、その日の実在庫が翌日の前回在庫（closing_stock）になる。
 * 使用量は生産ログ（production_logs）の実績から自動集計する。
 */
class ResinUsageController extends Controller
{
    /** 設定が未完了のときに画面へ表示するメッセージ */
    private const SETTING_REQUIRED_MESSAGE = '使用する2種類のレジンを管理者が設定してください。';

    /**
     * 使用量の履歴一覧と、変更履歴（監査ログ）一覧を表示する。
     * どちらも20件ずつのページ分割（ページネーション）で、変更履歴のページ番号は audit_page で受け取る。
     */
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

    /**
     * 新規登録フォームを表示する。
     * 日付はクエリの date（Y-m-d 形式）で指定でき、省略時は今日。
     * 設定が未完了、またはその日の記録が登録済みなら、メッセージ付きで別画面へ戻す。
     */
    public function create(Request $request)
    {
        $setting = $this->requireSetting();
        if (!$setting) {
            return $this->redirectSettingRequired();
        }

        // バリデーション（入力値のチェック）。date は未指定でもよい。
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
        // 前日までの記録があればその実在庫を、無ければ設定画面の初期在庫を前回在庫として使う。
        $openingStocks = array_replace($initialStocks, $previousStocks);
        // 初期在庫から始まる（=過去の記録が無い）材料の一覧。画面でその旨を表示するために渡す。
        $initialStockMaterials = array_diff(array_keys($initialStocks), array_keys($previousStocks));
        $operatorName = old('operator_name', auth()->user()->name);

        return view('resin-usage.form', compact('date', 'materials', 'usage', 'openingStocks', 'initialStockMaterials', 'operatorName'))
            ->with('record', null);
    }

    /**
     * 新規登録を保存する。
     * 入力チェック後、その日の記録（親）と材料ごとの明細（子）を1つのトランザクションで保存する。
     * 過去日付への追加は後続の記録の在庫計算がずれるため禁止している（修正は履歴から行う）。
     */
    public function store(Request $request)
    {
        $setting = $this->requireSetting();
        if (!$setting) {
            return $this->redirectSettingRequired();
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

        // トランザクション: 途中で失敗したら全部取り消し、記録だけ・明細だけが残る中途半端な状態を防ぐ。
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

    /**
     * 既存記録の修正フォームを表示する。
     * 修正対象の記録に保存済みの材料を対象にする（設定が後から変わっても、その日の材料を編集できる）。
     */
    public function edit(ResinUsageRecord $record)
    {
        $setting = $this->requireSetting();
        if (!$setting) {
            return $this->redirectSettingRequired();
        }

        $record->load('items.material');
        $materials = $record->items->pluck('material')->filter()->values();
        $date = $record->usage_date->toDateString();
        $usage = $this->usageForDate($date, $materials->pluck('id')->all());
        $openingStocks = $this->openingStocksForExistingRecord($record, $materials);
        $initialStockMaterials = [];
        $operatorName = old('operator_name', $record->operator_name);

        return view('resin-usage.form', compact('date', 'materials', 'usage', 'openingStocks', 'initialStockMaterials', 'operatorName', 'record'));
    }

    /**
     * 既存記録を更新する。
     * 更新後は、これより後の日付の記録も在庫（前回在庫など）を再計算し、
     * 「誰がいつ何を変えたか」を監査履歴（resin_usage_audits）に残す。
     * 修正前の内容を先に控えておき（$before）、修正後と並べて保存する。
     */
    public function update(Request $request, ResinUsageRecord $record)
    {
        $setting = $this->requireSetting();
        if (!$setting) {
            return $this->redirectSettingRequired();
        }

        $record->load('items.material');
        $materials = $record->items->pluck('material')->filter()->values();
        $date = $record->usage_date->toDateString();
        $usage = $this->usageForDate($date, $materials->pluck('id')->all());
        $openingStocks = $this->openingStocksForExistingRecord($record, $materials);
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

    /**
     * 記録を削除する。
     * 削除した日の後ろにある記録は前回在庫が変わるため再計算し、削除したこと自体も監査履歴に残す。
     * 削除すると中身が消えるので、削除前に内容を控えて（スナップショット）監査履歴へ保存する。
     */
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

    /** 設定が未完了のとき、メッセージ付きでトップ（resin-usage.index）へ戻す。 */
    private function redirectSettingRequired()
    {
        return redirect()->route('resin-usage.index')
            ->with('status', self::SETTING_REQUIRED_MESSAGE);
    }

    /**
     * 修正・削除対象の既存記録について、材料ごとの「前回在庫」を求める。
     * その記録より前の日付の実在庫を優先し、無ければ記録に保存済みの前回在庫を使う。
     *
     * @return array<int, float> 材料ID => 前回在庫
     */
    private function openingStocksForExistingRecord(ResinUsageRecord $record, $materials): array
    {
        $openingStocks = $this->previousStocks($record->usage_date->toDateString(), $materials, $record->id);
        foreach ($record->items as $item) {
            $openingStocks[$item->material_id] = $openingStocks[$item->material_id] ?? (float) $item->opening_stock;
        }

        return $openingStocks;
    }

    /**
     * 入力内容の検証（バリデーション）を行い、検証済みの値を返す。
     * 検証内容: 必須・数値範囲、送信された材料が設定の材料と一致すること、
     * 計算在庫と実在庫に差異があるときは差異の理由が入力されていること。
     *
     * @param  \Illuminate\Support\Collection $materials 対象の材料
     * @param  array $openingStocks 材料ID => 前回在庫（無い材料は画面から入力させる）
     * @param  array $usage 材料ID => その日の使用量
     */
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
            // 前回在庫が自動で決まらない材料だけ、画面からの入力を必須にする。
            if (!array_key_exists($material->id, $openingStocks)) {
                $rules['items.' . $material->id . '.opening_stock'] = ['required', 'numeric', 'min:0', 'max:999999999'];
            }
        }

        $validated = $request->validate($rules);
        $expectedItemIds = $materials->pluck('id')->all();
        $submittedItemIds = array_keys($validated['items']);
        // 材料IDの順序に関係なく比較するため、並べ替えてから突き合わせる。
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
            // 小数の誤差で「差異あり」と誤判定しないよう、小数第3位で丸めて比較する。
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

    /**
     * 材料1つ分の明細（resin_usage_items）に保存する値を組み立てる。
     * 計算在庫 = 前回在庫 + 補充数 - 使用量、差異 = 実在庫 - 計算在庫。
     * 差異が0のときは理由・確認者は不要なので null にする。
     * closing_stock（締め在庫）は実在庫と同じ値で、翌日以降の前回在庫になる。
     *
     * @param int $materialId 材料ID
     * @param float|string $opening 前回在庫
     * @param float|string $used その日の使用量
     * @param array $input 画面入力（replenished_quantity, actual_stock, adjustment_reason）
     */
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

    /**
     * 記録を修正した後、それより後の日付の記録を順に再計算する。
     * なぜ必要か: 修正した日の実在庫は翌日以降の「前回在庫」になるため、
     * 放置すると後ろの記録の計算在庫・差異が実態とずれてしまう。
     */
    private function recalculateFollowingRecords(ResinUsageRecord $record): void
    {
        // 修正した記録の締め在庫（実在庫）が、直後の記録の前回在庫になる。
        $previousStocks = [];
        foreach ($record->items()->get() as $item) {
            $previousStocks[$item->material_id] = (float) $item->closing_stock;
        }

        $followingRecords = ResinUsageRecord::with('items.material')
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

        $this->recalculateRecords($followingRecords, $trackedMaterialIds, $previousStocks, '前の記録の修正に伴う再計算');
    }

    /**
     * 記録を削除した後、それより後の日付の記録を順に再計算する。
     * 削除した日の実在庫は使えなくなるので、削除日より前の最新の実在庫から計算し直す。
     * 前の記録が1件も無い材料は、削除された記録に保存されていた前回在庫から始める。
     *
     * @param string $deletedDate 削除した記録の日付（Y-m-d）
     * @param array $materialIds 削除した記録に含まれていた材料ID
     * @param array $deletedSnapshot 削除前に控えた記録の内容（recordSnapshot の戻り値）
     */
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

        $this->recalculateRecords($followingRecords, $trackedMaterialIds, $previousStocks, '前の記録の削除に伴う再計算');
    }

    /**
     * 日付の古い順に並んだ記録を順番に再計算する共通処理（修正後・削除後の両方で使う）。
     * 各記録について、前回在庫・使用量・計算在庫・差異を計算し直して保存し、
     * 内容が変わった記録だけ監査履歴（updated）に書き込む。
     *
     * @param \Illuminate\Support\Collection $followingRecords 再計算する記録（日付の昇順）
     * @param array $trackedMaterialIds 再計算の対象となる材料ID
     * @param array $previousStocks 材料ID => 直前の記録の実在庫（処理しながら更新される）
     * @param string $recalculationReason 差異が新しく生じた場合に自動入力する理由
     */
    private function recalculateRecords($followingRecords, array $trackedMaterialIds, array $previousStocks, string $recalculationReason): void
    {
        foreach ($followingRecords as $followingRecord) {
            $before = $this->recordSnapshot($followingRecord);
            $usage = $this->usageForDate($followingRecord->usage_date->toDateString(), $trackedMaterialIds);

            // この日に記録が無い材料は在庫が途切れるので、前日までの在庫を引き継がない。
            $materialIdsInRecord = $followingRecord->items->pluck('material_id')->all();
            foreach ($trackedMaterialIds as $materialId) {
                if (!in_array($materialId, $materialIdsInRecord)) {
                    unset($previousStocks[$materialId]);
                }
            }

            foreach ($followingRecord->items as $item) {
                $opening = $previousStocks[$item->material_id] ?? (float) $item->opening_stock;
                $used = round((float) ($usage[$item->material_id] ?? 0), 3);
                $calculated = round($opening + (float) $item->replenished_quantity - $used, 3);
                $actual = (float) $item->actual_stock;
                $adjustment = round($actual - $calculated, 3);

                $item->update([
                    'opening_stock' => $opening,
                    'used_quantity' => $used,
                    'calculated_stock' => $calculated,
                    'adjustment_quantity' => $adjustment,
                    'adjustment_reason' => $this->recalculatedAdjustmentReason($item, $adjustment, $recalculationReason),
                    'reconciled_by' => $this->recalculatedReconciler($item, $adjustment),
                ]);
                // 実在庫は変えず、次の記録の前回在庫として引き継ぐ。
                $previousStocks[$item->material_id] = $actual;
            }

            $followingRecord->load('items.material');
            $after = $this->recordSnapshot($followingRecord);
            if ($before !== $after) {
                $this->writeAudit('updated', $followingRecord->usage_date, $before, $after);
            }
        }
    }

    /**
     * 再計算後の「差異の理由」を決める。
     * 差異なし → null / 差異の数値が変わった → 自動の理由 / 変わらない → 元の理由を残す（空なら自動の理由）。
     */
    private function recalculatedAdjustmentReason($item, float $newAdjustment, string $recalculationReason): ?string
    {
        if ($newAdjustment == 0.0) {
            return null;
        }

        $adjustmentChanged = $newAdjustment != (float) $item->adjustment_quantity;
        if ($adjustmentChanged) {
            return $recalculationReason;
        }

        return $item->adjustment_reason ?: $recalculationReason;
    }

    /**
     * 再計算後の「差異を確認したユーザーID」を決める。
     * 差異なし → null / 差異が変わった → 今操作しているユーザー / 変わらない → 元の確認者を残す（空なら操作ユーザー）。
     */
    private function recalculatedReconciler($item, float $newAdjustment)
    {
        if ($newAdjustment == 0.0) {
            return null;
        }

        $adjustmentChanged = $newAdjustment != (float) $item->adjustment_quantity;
        if ($adjustmentChanged) {
            return auth()->id();
        }

        return $item->reconciled_by ?: auth()->id();
    }

    /**
     * 指定日より前で、材料ごとの最新の締め在庫（実在庫）を返す。
     * 日付の新しい順に読み、材料ごとに最初に見つかった（=最新の）値だけを採用する。
     *
     * @return array<int, float> 材料ID => 在庫
     */
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

    /**
     * 記録の現在の内容を配列に写し取る（スナップショット）。
     * 監査履歴の before_data / after_data に保存する形式で、材料名・単位も一緒に残す
     * （後から材料名が変わっても、当時の内容が分かるようにするため）。
     */
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

    /**
     * 監査履歴（誰がいつ何を変えたか）を1件保存する。
     * 操作したユーザーのIDと名前、操作の種類（updated / deleted）、変更前後の内容を残す。
     * 削除時は after が null、作成日時は自動で入る。
     */
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

    /**
     * 指定日の材料ごとの使用量を、生産ログの計量実績（actual_quantity）の合計から集計する。
     *
     * @return array<int, float> 材料ID => 使用量
     */
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

    /**
     * 指定日より前の記録から、材料ごとの最新の締め在庫を返す（新規登録・修正時の前回在庫）。
     * $excludeRecordId を渡すと、その記録（修正中の記録自身）は除いて探す。
     *
     * @return array<int, float> 材料ID => 在庫
     */
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

    /**
     * 利用可能なレジン設定を返す。2種類の材料と初期在庫が揃っていなければ null（未設定扱い）。
     */
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

    /** 設定が必須の画面で使う。configuredSetting と同じ結果を返す。 */
    private function requireSetting()
    {
        return $this->configuredSetting();
    }

    /** 設定された2種類の材料（Material）をコレクションで返す。 */
    private function configuredMaterials(ResinUsageSetting $setting)
    {
        return collect([$setting->materialOne, $setting->materialTwo])->filter()->values();
    }

    /**
     * 設定画面で登録した初期在庫を返す。過去の記録が無い最初の日の前回在庫として使う。
     *
     * @return array<int, float> 材料ID => 初期在庫
     */
    private function initialStocks(ResinUsageSetting $setting): array
    {
        return [
            $setting->material_one_id => (float) $setting->material_one_initial_stock,
            $setting->material_two_id => (float) $setting->material_two_initial_stock,
        ];
    }

    /** 責任者の確認チェックがあるときだけ責任者名を返す（無ければ null）。 */
    private function confirmedSupervisorName(array $validated)
    {
        return !empty($validated['supervisor_confirmed']) ? $validated['supervisor_name'] : null;
    }

    /** 責任者の確認チェックがあるときだけ現在時刻（確認日時）を返す（無ければ null）。 */
    private function confirmationTime(array $validated)
    {
        return !empty($validated['supervisor_confirmed']) ? now() : null;
    }
}
