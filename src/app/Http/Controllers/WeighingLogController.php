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

class WeighingLogController extends Controller
{
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

    public function store(StoreWeighingLogRequest $request)
    {
        $recipe = Recipe::with('materials')->findOrFail($request->recipe_id);
        $extras = $this->extraMaterials($request->input('extra_materials', []), $recipe->materials->pluck('id')->all());

        DB::transaction(function () use ($request, $extras) {
            $log = ProductionLog::create([
                'user_id' => auth()->id(),
                'recipe_id' => $request->recipe_id,
                'weighed_at' => $request->weighed_at,
                'notes' => $request->notes,
            ]);

            $materials = [];
            foreach ($request->materials as $material_id => $quantity) {
                $materials[$material_id] = ['actual_quantity' => $quantity];
            }
            $log->materials()->attach($materials + $extras);
        });

        return redirect()->route('logs.index');
    }

    // レシピ外の材料を attach/sync 用の配列に変換する。レシピ内の材料は通常欄で入力させる。
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

    public function update(Request $request, ProductionLog $log)
    {
        $log->load('recipe.materials', 'materials');
        $recipeMaterialIds = $log->recipe->materials->pluck('id')->all();
        $materialIds = $log->materials->pluck('id')->intersect($recipeMaterialIds)->values()->all();

        $request->merge(['extra_materials' => array_values(array_filter((array) $request->input('extra_materials', []), function ($row) {
            return ($row['material_id'] ?? '') !== '' || ($row['quantity'] ?? '') !== '';
        }))]);

        $rules = [
            'weighed_at' => 'required|date_format:Y-m-d\TH:i',
            'notes' => [Rule::requiredIf(!empty($request->input('extra_materials'))), 'nullable', 'string'],
            'materials' => 'required|array',
            'extra_materials' => 'nullable|array',
            'extra_materials.*.material_id' => 'required|exists:materials,id|distinct',
            'extra_materials.*.quantity' => 'required|numeric|min:0|max:999999.99',
        ];
        foreach ($materialIds as $materialId) {
            $rules['materials.' . $materialId] = 'required|numeric|min:0|max:999999.99';
        }
        $validated = $request->validate($rules, [
            'notes.required' => 'レシピ外の材料を使用した場合は、備考に理由を入力してください。',
        ]);
        $extras = $this->extraMaterials($validated['extra_materials'] ?? [], $recipeMaterialIds);

        $before = $this->snapshot($log);

        DB::transaction(function () use ($log, $validated, $materialIds, $before, $extras) {
            $log->update([
                'weighed_at' => $validated['weighed_at'],
                'notes' => $validated['notes'] ?? null,
            ]);

            $sync = [];
            foreach ($materialIds as $materialId) {
                $sync[$materialId] = ['actual_quantity' => $validated['materials'][$materialId]];
            }
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

        // 許容範囲判定ロジック
        $logs = $logs->map(function ($log) {
            $material_tolerances = [];
            $off_recipe = [];

            foreach ($log->materials as $material) {
                $recipeMaterial = $log->recipe->materials->where('id', $material->id)->first();
                $actualQuantity = $material->pivot->actual_quantity;
                $isOutOfTolerance = false;
                $off_recipe[$material->id] = !$recipeMaterial;

                if ($actualQuantity && $recipeMaterial && $recipeMaterial->pivot->tolerance > 0) {
                    $standard = $recipeMaterial->pivot->quantity;
                    $tolerance = $recipeMaterial->pivot->tolerance;
                    $minValue = $standard - $tolerance;
                    $maxValue = $standard + $tolerance;
                    $isOutOfTolerance = $actualQuantity < $minValue || $actualQuantity > $maxValue;
                }

                $material_tolerances[$material->id] = $isOutOfTolerance || !$recipeMaterial;
            }

            $log->material_tolerances = $material_tolerances;
            $log->off_recipe_materials = $off_recipe;
            return $log;
        });

        $recipes = Recipe::all();
        $allMaterials = Material::all();

        return view('logs.index', compact('logs', 'recipes', 'allMaterials'));
    }

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
