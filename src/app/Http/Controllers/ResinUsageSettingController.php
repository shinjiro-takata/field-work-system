<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\ResinUsageSetting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * 管理者が「レジンとして管理する2種類の材料」と、その初期在庫を設定するコントローラ。
 * 設定は1件だけ（id = 1 固定）で、無ければ初回保存時に作られる。
 */
class ResinUsageSettingController extends Controller
{
    /** 設定画面を表示する。現在の設定と、選択肢にする材料一覧（名前順）を画面へ渡す。 */
    public function edit()
    {
        $setting = ResinUsageSetting::with(['materialOne', 'materialTwo'])->find(1);
        $materials = Material::orderBy('name')->get();

        return view('resin-usage.settings', compact('setting', 'materials'));
    }

    /**
     * 設定を保存する。
     * バリデーション（入力値のチェック）: 材料は materials テーブルに存在し、2つが別の材料であること。
     * 初期在庫は 0 以上の数値。保存後は設定画面へ戻る。
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'material_one_id' => ['required', 'integer', 'different:material_two_id', Rule::exists('materials', 'id')],
            'material_two_id' => ['required', 'integer', Rule::exists('materials', 'id')],
            'material_one_initial_stock' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'material_two_initial_stock' => ['required', 'numeric', 'min:0', 'max:999999999'],
        ]);

        // 設定は常に id = 1 の1行だけを使う。まだ無ければ新規作成する。
        $setting = ResinUsageSetting::find(1);
        if (!$setting) {
            $setting = new ResinUsageSetting();
            $setting->id = 1;
        }

        $setting->fill([
            'material_one_id' => $validated['material_one_id'],
            'material_two_id' => $validated['material_two_id'],
            'material_one_initial_stock' => $validated['material_one_initial_stock'],
            'material_two_initial_stock' => $validated['material_two_initial_stock'],
            'updated_by' => auth()->id(),
        ])->save();

        return redirect()->route('resin-usage.settings.edit')->with('status', 'レジン材料を設定しました。');
    }
}
