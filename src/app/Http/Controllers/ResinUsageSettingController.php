<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\ResinUsageSetting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ResinUsageSettingController extends Controller
{
    public function edit()
    {
        $setting = ResinUsageSetting::with(['materialOne', 'materialTwo'])->find(1);
        $materials = Material::orderBy('name')->get();

        return view('resin-usage.settings', compact('setting', 'materials'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'material_one_id' => ['required', 'integer', 'different:material_two_id', Rule::exists('materials', 'id')],
            'material_two_id' => ['required', 'integer', Rule::exists('materials', 'id')],
            'material_one_initial_stock' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'material_two_initial_stock' => ['required', 'numeric', 'min:0', 'max:999999999'],
        ]);

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
