<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Recipe;
use App\Models\Material;
use App\Http\Requests\StoreRecipeRequest;
use App\Http\Requests\UpdateRecipeRequest;

/**
 * レシピ（材料と基準量・許容範囲の組み合わせ）の一覧・詳細・登録・編集・削除を担当するコントローラ。
 */
class RecipeController extends Controller
{
    /** レシピ一覧を表示する。 */
    public function index()
    {
        $recipes = Recipe::with('materials')->get();
        return view('recipes.index', compact('recipes'));
    }

    /** レシピ詳細（使う材料の一覧つき）を表示する。 */
    public function show(Recipe $recipe)
    {
        $recipe->load('materials');
        return view('recipes.show', compact('recipe'));
    }

    /** レシピ新規登録画面を表示する。選択肢として全材料を渡す。 */
    public function create()
    {
        $materials = Material::all();
        return view('recipes.create', compact('materials'));
    }

    /**
     * レシピを新規登録する。
     * フォームで「新しい材料」が入力されていれば、材料も同時に登録してレシピに含める。
     */
    public function store(StoreRecipeRequest $request)
    {
        $recipe = Recipe::create($request->validated());

        // attach = 中間テーブル（ピボットテーブル: レシピと材料を結ぶ表）に行を追加する
        $recipe->materials()->attach($this->buildMaterialPivotData($request));
        return redirect()->route('recipes.index');
    }

    /**
     * レシピ編集画面を表示する。
     * 画面の入力欄に初期値を入れるため、材料ごとの基準量・許容範囲を ID をキーにした配列で渡す。
     */
    public function edit(Recipe $recipe)
    {
        $materials = Material::all();
        // このレシピに紐付く材料ID一覧（checkbox の checked 判定用）
        $recipeMaterialIds = $recipe->materials->pluck('id')->toArray();
        // このレシピの各材料ごとの数量（input value 埋め込み用）
        $recipeMaterials = $recipe->materials->pluck('pivot.quantity', 'id')->toArray();
        // このレシピの各材料ごとの許容範囲（input value 埋め込み用）
        $recipeTolerance = $recipe->materials->pluck('pivot.tolerance', 'id')->toArray();

        return view('recipes.edit', compact('recipe', 'materials', 'recipeMaterialIds', 'recipeMaterials', 'recipeTolerance'));
    }

    /**
     * レシピを更新する。「新しい材料」が入力されていれば材料も同時に登録してレシピに含める。
     */
    public function update(UpdateRecipeRequest $request, Recipe $recipe)
    {
        $recipe->update($request->validated());

        // sync = 中間テーブルを渡した内容と同じになるよう、追加・更新・削除をまとめて行う
        // （チェックを外された材料はレシピから外れる）
        $recipe->materials()->sync($this->buildMaterialPivotData($request));

        return redirect()->route('recipes.index');
    }

    /** レシピを削除する。 */
    public function destroy(Recipe $recipe)
    {
        $recipe->delete();
        return redirect()->route('recipes.index');
    }

    /**
     * フォーム入力から、中間テーブルに保存するデータ [材料ID => ['quantity' => 基準量, 'tolerance' => 許容範囲]] を作る。
     * 「新しい材料」（名前と単位が両方入力されたもの）があれば、材料マスタに登録（既存なら再利用）して含める。
     * 新材料の基準量が未入力なら 0、許容範囲が未入力なら null として扱う。
     */
    private function buildMaterialPivotData(Request $request): array
    {
        $materialIds = $request->material_ids ?? [];
        $quantities = $request->materials ?? [];
        $tolerances = $request->tolerances ?? [];

        if ($request->new_material_name && $request->new_material_unit) {
            // firstOrCreate = 同じ名前・単位の材料があればそれを使い、なければ新規作成する
            $newMaterial = Material::firstOrCreate(
                ['name' => $request->new_material_name, 'unit' => $request->new_material_unit],
                ['name' => $request->new_material_name, 'unit' => $request->new_material_unit]
            );

            $materialIds[] = $newMaterial->id;
            $quantities[$newMaterial->id] = $request->new_material_quantity ?? 0;
            if ($request->new_material_tolerance !== null) {
                $tolerances[$newMaterial->id] = $request->new_material_tolerance;
            }
        }

        // 基準量が入力されている材料だけを保存対象にする
        $pivotData = [];
        foreach ($materialIds as $materialId) {
            if (isset($quantities[$materialId])) {
                $pivotData[$materialId] = [
                    'quantity' => $quantities[$materialId],
                    'tolerance' => $tolerances[$materialId] ?? null,
                ];
            }
        }

        return $pivotData;
    }
}
