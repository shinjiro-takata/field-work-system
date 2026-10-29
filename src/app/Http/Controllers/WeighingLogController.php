<?php

namespace App\Http\Controllers;

use App\Exports\ProductionLogsExport;
use Illuminate\Http\Request;
use App\Models\Recipe;
use App\Models\ProductionLog;
use App\Http\Requests\StoreWeighingLogRequest;
use App\Models\Material;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

/**
 * 計量ログ（材料を実際に量った記録）の登録・修正・一覧・Excel出力を担当するコントローラ。
 */
class WeighingLogController extends Controller
{
    /**
     * 計量ログの新規登録画面を表示する。
     * レシピが選ばれていれば、そのレシピの材料入力欄と「レシピ外材料」の選択肢も渡す。
     */
    public function create()
    {
        $selectedRecipeId = request('recipe_id');
        $recipes = Recipe::all();
        $recipe = null;
        $defaultDateTime = now()->format('Y-m-d\TH:i');

        if ($selectedRecipeId) {
            $recipe = Recipe::with('materials')->find($selectedRecipeId);
        }

        return view('logs.create', [
            'recipes' => $recipes,
            'recipe' => $recipe,
            'selectedRecipeId' => $selectedRecipeId,
            'defaultDateTime' => $defaultDateTime,
            'selectableMaterials' => $recipe
                ? Material::orderBy('name')->get()->whereNotIn('id', $recipe->materials->pluck('id'))->values()
                : collect(),
        ]);
    }

    /**
     * 計量ログを保存する。
     * StoreWeighingLogRequest（フォームリクエスト = 入力チェック専用クラス）で
     * バリデーション（入力値の検査）を通った値だけがここに届く。
     */
    public function store(StoreWeighingLogRequest $request)
    {
        $recipe = Recipe::with('materials')->findOrFail($request->recipe_id);
        // レシピ外の追加材料（レシピに含まれる材料が混ざっていればエラー）
        $extras = $this->extraMaterials($request->input('extra_materials', []), $recipe->materials->pluck('id')->all());

        // トランザクション: ログ本体と材料の保存を「全部成功するか、全部取り消すか」の1まとまりにする
        DB::transaction(function () use ($request, $extras) {
            $log = ProductionLog::create([
                'user_id' => auth()->id(),
                'recipe_id' => $request->recipe_id,
                'weighed_at' => $request->weighed_at,
                'notes' => $request->notes,
            ]);

            // 中間テーブル（ピボットテーブル: ログと材料を結ぶ表）に保存する形 [材料ID => ['actual_quantity' => 実測量]]
            $materials = [];
            foreach ($request->materials as $material_id => $quantity) {
                $materials[$material_id] = ['actual_quantity' => $quantity];
            }
            // attach = 中間テーブルに行を追加する
            $log->materials()->attach($materials + $extras);
        });

        return redirect()->route('logs.index');
    }

    /**
     * 「レシピ外の材料」の入力行を、中間テーブル保存用の配列 [材料ID => ['actual_quantity' => 量]] に変換する。
     * レシピに含まれる材料が指定されていたら、通常の入力欄を使わせるためエラーにする。
     */
    private function extraMaterials(array $rows, array $recipeMaterialIds): array
    {
        $extras = [];
        foreach ($rows as $index => $row) {
            if (in_array((int) $row['material_id'], $recipeMaterialIds, true)) {
                throw ValidationException::withMessages([
                    'extra_materials.' . $index . '.material_id' => 'レシピに含まれる材料は、通常の入力欄に入力してください。',
                ]);
            }
            $extras[(int) $row['material_id']] = ['actual_quantity' => $row['quantity']];
        }

        return $extras;
    }

    /**
     * 計量ログの修正画面を表示する。修正履歴も新しい順に渡す。
     */
    public function edit(ProductionLog $log)
    {
        $log->load('recipe.materials', 'materials', 'editLogs');
        $recipeMaterials = $log->recipe->materials->keyBy('id');

        return view('logs.edit', [
            'log' => $log,
            'recipeMaterials' => $recipeMaterials,
            'extraSelections' => $log->materials->whereNotIn('id', $recipeMaterials->keys())->values(),
            'selectableMaterials' => Material::orderBy('name')->get()->whereNotIn('id', $recipeMaterials->keys())->values(),
            'editLogs' => $log->editLogs()->orderByDesc('created_at')->orderByDesc('id')->get(),
        ]);
    }

    /**
     * 計量ログを修正する。
     * 内容が変わった場合のみ、修正前後の内容を修正履歴（ProductionLogEditLog）に残す。
     */
    public function update(Request $request, ProductionLog $log)
    {
        $log->load('recipe.materials', 'materials');
        $recipeMaterialIds = $log->recipe->materials->pluck('id')->all();
        // このログに記録済みの「レシピ内材料」のID（通常入力欄の対象）
        $materialIds = $log->materials->pluck('id')->intersect($recipeMaterialIds)->values()->all();

        // 画面の空欄行（材料も量も未入力）は無視して、入力のある行だけをレシピ外材料として扱う
        $extraRows = array_filter((array) $request->input('extra_materials', []), function ($row) {
            return ($row['material_id'] ?? '') !== '' || ($row['quantity'] ?? '') !== '';
        });
        $request->merge(['extra_materials' => array_values($extraRows)]);

        // バリデーションルール: レシピ外材料があるときだけ備考を必須にする
        $rules = [
            'weighed_at' => 'required|date_format:Y-m-d\TH:i',
            'notes' => [Rule::requiredIf(!empty($request->input('extra_materials'))), 'nullable', 'string'],
            'materials' => 'required|array',
            'extra_materials' => 'nullable|array',
            'extra_materials.*.material_id' => 'required|exists:materials,id|distinct',
            'extra_materials.*.quantity' => 'required|numeric|min:0|max:999999.99',
        ];
        // レシピ内の各材料の実測量は必須
        foreach ($materialIds as $materialId) {
            $rules['materials.' . $materialId] = 'required|numeric|min:0|max:999999.99';
        }
        $validated = $request->validate($rules, [
            'notes.required' => 'レシピ外の材料を使用した場合は、備考に理由を入力してください。',
        ]);
        $extras = $this->extraMaterials($validated['extra_materials'] ?? [], $recipeMaterialIds);

        // 修正前の内容を保存しておき、修正後と比べて変更の有無を判断する
        $before = $this->snapshot($log);

        // トランザクション: ログ更新・材料更新・履歴保存を1まとまりにする（途中で失敗したら全部取り消し）
        DB::transaction(function () use ($log, $validated, $materialIds, $before, $extras) {
            $log->update([
                'weighed_at' => $validated['weighed_at'],
                'notes' => $validated['notes'] ?? null,
            ]);

            $sync = [];
            foreach ($materialIds as $materialId) {
                $sync[$materialId] = ['actual_quantity' => $validated['materials'][$materialId]];
            }
            // sync = 中間テーブルを渡した内容と同じになるよう、追加・更新・削除をまとめて行う
            // （画面から消されたレシピ外材料はここで削除される）
            $log->materials()->sync($sync + $extras);

            $log->load('materials');
            $after = $this->snapshot($log);

            if ($before !== $after) {
                $log->editLogs()->create([
                    'editor_id' => auth()->id(),
                    'editor_name' => auth()->user()->name,
                    'before_data' => $before,
                    'after_data' => $after,
                ]);
            }
        });

        return redirect()->route('logs.index')->with('status', '計量ログを修正しました。');
    }

    /**
     * 修正履歴の比較・保存用に、ログの現在の内容（日時・備考・材料と実測量）を配列にまとめる。
     */
    private function snapshot(ProductionLog $log): array
    {
        return [
            'weighed_at' => Carbon::parse($log->weighed_at)->format('Y-m-d H:i'),
            'notes' => $log->notes,
            'materials' => $log->materials->sortBy('id')->map(function ($material) {
                return [
                    'material_id' => $material->id,
                    'name' => $material->name,
                    'unit' => $material->unit,
                    'actual_quantity' => round((float) $material->pivot->actual_quantity, 3),
                ];
            })->values()->all(),
        ];
    }

    /**
     * 計量ログの一覧を表示する。日付範囲・レシピで絞り込み、材料ごとに許容範囲外かどうかも判定する。
     */
    public function index()
    {
        $logs = ProductionLog::query()
            ->with('recipe', 'user', 'materials')
            ->withCount('editLogs')
            ->when(request('start_date'), function ($q) {
                $q->whereDate('weighed_at', '>=', request('start_date'));
            })
            ->when(request('end_date'), function ($q) {
                $q->whereDate('weighed_at', '<=', request('end_date'));
            })
            ->when(request('recipe_id'), function ($q) {
                $q->where('recipe_id', request('recipe_id'));
            })
            ->get();

        // 各ログに、画面表示用の判定結果（要注意・レシピ外）を付け足す
        $logs = $logs->map(function ($log) {
            [$materialTolerances, $offRecipeMaterials] = $this->judgeMaterials($log);
            $log->material_tolerances = $materialTolerances;
            $log->off_recipe_materials = $offRecipeMaterials;
            return $log;
        });

        $recipes = Recipe::all();
        $allMaterials = Material::all();

        return view('logs.index', compact('logs', 'recipes', 'allMaterials'));
    }

    /**
     * 1件のログについて、材料ごとの判定結果を返す。
     * 戻り値: [要注意フラグの配列, レシピ外フラグの配列]（どちらも 材料ID => true/false）
     *  - 要注意: レシピ外の材料、または実測量が「基準量 ± 許容量」の範囲外
     *  - レシピ外: そのログのレシピに含まれない材料
     */
    private function judgeMaterials(ProductionLog $log): array
    {
        $needsAttention = [];
        $offRecipe = [];

        foreach ($log->materials as $material) {
            $recipeMaterial = $log->recipe->materials->where('id', $material->id)->first();
            $isOffRecipe = !$recipeMaterial;
            $isOutOfTolerance = false;

            // 許容量が設定されている（0より大きい）材料だけ範囲チェックする
            $actualQuantity = $material->pivot->actual_quantity;
            if ($actualQuantity && $recipeMaterial && $recipeMaterial->pivot->tolerance > 0) {
                $standard = $recipeMaterial->pivot->quantity;
                $tolerance = $recipeMaterial->pivot->tolerance;
                $isOutOfTolerance = $actualQuantity < $standard - $tolerance
                    || $actualQuantity > $standard + $tolerance;
            }

            $offRecipe[$material->id] = $isOffRecipe;
            $needsAttention[$material->id] = $isOutOfTolerance || $isOffRecipe;
        }

        return [$needsAttention, $offRecipe];
    }

    /**
     * 絞り込み条件（日付範囲・レシピ）に合う計量ログをExcelファイルとしてダウンロードさせる。
     */
    public function export(Request $request)
    {
        $logs = ProductionLog::query()
            ->with('recipe', 'user', 'materials')
            ->when($request->start_date, function ($q) use ($request) {
                $q->whereDate('weighed_at', '>=', $request->start_date);
            })
            ->when($request->end_date, function ($q) use ($request) {
                $q->whereDate('weighed_at', '<=', $request->end_date);
            })
            ->when($request->recipe_id, function ($q) use ($request) {
                $q->where('recipe_id', $request->recipe_id);
            })
            ->get();

        $allMaterials = Material::all();
        $fileName = 'production_logs_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new ProductionLogsExport($logs, $allMaterials), $fileName);
    }
}
