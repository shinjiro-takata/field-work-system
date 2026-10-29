<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Material;
use App\Models\ResinUsageItem;
use App\Http\Requests\StoreMaterialRequest;
use App\Http\Requests\UpdateMaterialRequest;

/**
 * 材料マスタ（材料名と単位）の一覧・登録・編集・削除を担当するコントローラ。
 */
class MaterialController extends Controller
{
    // ログイン必須にする（auth ミドルウェア = アクセス前の関門）。
    // 登録・更新時の「管理者のみ」の制限は各フォームリクエスト側で行う。
    public function __construct()
    {
        $this->middleware('auth');
    }

    /** 材料一覧を20件ずつ表示する。 */
    public function index()
    {
        $materials = Material::paginate(20);
        return view('materials.index', compact('materials'));
    }

    /** 材料の新規登録画面を表示する。 */
    public function create()
    {
        return view('materials.create');
    }

    /** 材料を登録する（入力チェックは StoreMaterialRequest で済んでいる）。 */
    public function store(StoreMaterialRequest $request)
    {
        Material::create($request->validated());
        return redirect()->route('materials.index')->with('success', '材料を作成しました');
    }

    /** 材料の編集画面を表示する。 */
    public function edit(Material $material)
    {
        return view('materials.edit', compact('material'));
    }

    /**
     * 材料を更新する。
     * レジン使用量の履歴で使われている材料は、記録の意味が変わってしまうため単位を変更できない。
     */
    public function update(UpdateMaterialRequest $request, Material $material)
    {
        $validated = $request->validated();
        $isUnitChanged = $validated['unit'] !== $material->unit;
        if ($isUnitChanged && ResinUsageItem::where('material_id', $material->id)->exists()) {
            return redirect()->route('materials.edit', $material)
                ->withErrors(['unit' => 'レジン使用量の履歴がある材料の単位は変更できません。']);
        }

        $material->update($validated);
        return redirect()->route('materials.index')->with('success', '材料を更新しました');
    }

    /** 材料を削除する。レジン使用量の履歴で使われている材料は削除できない。 */
    public function destroy(Material $material)
    {
        if (ResinUsageItem::where('material_id', $material->id)->exists()) {
            return redirect()->route('materials.index')
                ->with('status', 'レジン使用量の履歴がある材料は削除できません。');
        }

        $material->delete();
        return redirect()->route('materials.index')->with('success', '材料を削除しました');
    }
}
