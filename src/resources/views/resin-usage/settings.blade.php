@extends('layouts.app')

@section('title', 'レジン材料の設定 | レジン使用量確認表')
@section('brand', 'レジン使用量確認表')
@section('brand-link', route('resin-usage.index'))
@section('show-home', true)

@section('css')
<link rel="stylesheet" href="{{ asset('css/users.css') }}">
@endsection

@section('content')
<h1>使用量を集計するレジン材料の設定</h1>
<p>配合手順確認書の計量ログから、選択した2種類の材料の実使用量を日付ごとに合計します。レシピの配合値は参照しません。</p>
<p>初回入力ではここで設定した初回在庫を使い、2回目以降はその材料の最終在庫確認量を前回在庫として使います。設定を変更すると、以後の新規記録から新しい材料が使われます。過去の記録は登録時の材料のまま保持され、表示・修正できます。</p>

<form action="{{ route('resin-usage.settings.update') }}" method="POST" class="form-container">
    @csrf
    @method('PUT')

    <div class="form-group">
        <label for="material_one_id">レジンの種類 1</label>
        <select id="material_one_id" name="material_one_id" required>
            <option value="">材料を選択してください</option>
            @foreach($materials as $material)
                <option value="{{ $material->id }}"
                    data-unit="{{ $material->unit }}"
                    {{ (string) old('material_one_id', $setting ? $setting->material_one_id : '') === (string) $material->id ? 'selected' : '' }}>
                    {{ $material->name }}（{{ $material->unit }}）
                </option>
            @endforeach
        </select>
        @error('material_one_id')<div class="error">{{ $message }}</div>@enderror
    </div>

    <div class="form-group">
        <label for="material_one_initial_stock">レジンの種類 1 の初回在庫（<span id="material-one-unit">{{ $setting && $setting->materialOne ? $setting->materialOne->unit : '単位' }}</span>）</label>
        <input type="number" id="material_one_initial_stock" name="material_one_initial_stock"
            value="{{ old('material_one_initial_stock', $setting ? $setting->material_one_initial_stock : '') }}"
            min="0" step="0.001" required>
        @error('material_one_initial_stock')<div class="error">{{ $message }}</div>@enderror
    </div>

    <div class="form-group">
        <label for="material_two_id">レジンの種類 2</label>
        <select id="material_two_id" name="material_two_id" required>
            <option value="">材料を選択してください</option>
            @foreach($materials as $material)
                <option value="{{ $material->id }}"
                    data-unit="{{ $material->unit }}"
                    {{ (string) old('material_two_id', $setting ? $setting->material_two_id : '') === (string) $material->id ? 'selected' : '' }}>
                    {{ $material->name }}（{{ $material->unit }}）
                </option>
            @endforeach
        </select>
        @error('material_two_id')<div class="error">{{ $message }}</div>@enderror
    </div>

    <div class="form-group">
        <label for="material_two_initial_stock">レジンの種類 2 の初回在庫（<span id="material-two-unit">{{ $setting && $setting->materialTwo ? $setting->materialTwo->unit : '単位' }}</span>）</label>
        <input type="number" id="material_two_initial_stock" name="material_two_initial_stock"
            value="{{ old('material_two_initial_stock', $setting ? $setting->material_two_initial_stock : '') }}"
            min="0" step="0.001" required>
        @error('material_two_initial_stock')<div class="error">{{ $message }}</div>@enderror
    </div>

    <div class="form-actions">
        <button type="submit" class="btn">設定を保存</button>
        <a href="{{ route('resin-usage.index') }}" class="btn btn-secondary">戻る</a>
    </div>
</form>

<script>
    [
        ['material_one_id', 'material-one-unit'],
        ['material_two_id', 'material-two-unit']
    ].forEach(function ([selectId, unitId]) {
        const select = document.getElementById(selectId);
        const unit = document.getElementById(unitId);

        function updateUnit() {
            unit.textContent = select.selectedOptions[0].dataset.unit || '単位';
        }

        select.addEventListener('change', updateUnit);
        updateUnit();
    });
</script>
@endsection
