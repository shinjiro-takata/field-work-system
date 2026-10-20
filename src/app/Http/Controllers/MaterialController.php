<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Material;
use App\Models\ResinUsageItem;
use App\Http\Requests\StoreMaterialRequest;
use App\Http\Requests\UpdateMaterialRequest;

class MaterialController extends Controller
{
    // 管理者のみアクセス可能
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $materials = Material::paginate(20);
        return view('materials.index', compact('materials'));
    }

    public function create()
    {
        return view('materials.create');
    }

    public function store(StoreMaterialRequest $request)
    {
        Material::create($request->validated());
        return redirect()->route('materials.index')->with('success', '材料を作成しました');
    }

    public function edit(Material $material)
    {
        return view('materials.edit', compact('material'));
    }

    public function update(UpdateMaterialRequest $request, Material $material)
    {
        $validated = $request->validated();
        if ($validated['unit'] !== $material->unit
            && ResinUsageItem::where('material_id', $material->id)->exists()) {
            return redirect()->route('materials.edit', $material)
                ->withErrors(['unit' => 'レジン使用量の履歴がある材料の単位は変更できません。']);
        }

        $material->update($validated);
        return redirect()->route('materials.index')->with('success', '材料を更新しました');
    }

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
