@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/recipes.css') }}">
@endsection

@section('content')
{{-- ===== 画面タイトル ===== --}}
<h1>レシピ編集</h1>

<div class="form-container">
    <form action="{{ route('recipes.update', $recipe->id) }}" method="POST" class="recipe-form">
        {{-- @csrf = フォーム送信の正当性を確認する合言葉（トークン）を埋め込む。@method('PUT') = 更新リクエストとして送る --}}
        @csrf
        @method('PUT')

        {{-- レシピ名（必須） --}}
        <div class="form-group">
            <label for="name">レシピ名 <span class="required">*</span></label>
            <input type="text" id="name" name="name" value="{{ old('name', $recipe->name) }}" required class="form-input">
            @error('name')
            <div class="error">{{ $message }}</div>
            @enderror
        </div>

        {{-- 登録済みの材料から選ぶ。現在レシピに含まれる材料にはチェックと現在の基準量・許容範囲が入っている。
             チェックを外した材料はレシピから外れる --}}
        <div class="form-section">
            <h3>既存の材料</h3>
            <p style="font-size: 13px; color: #666; margin-bottom: 15px;">✓をつけた材料がレシピに含まれます。チェックを外すと削除されます。</p>
            <div class="materials-checklist">
                @foreach($materials as $material)
                <div class="checkbox-group">
                    <label class="checkbox-label">
                        <input
                            type="checkbox"
                            name="material_ids[]"
                            value="{{ $material->id }}"
                            {{ in_array($material->id, old('material_ids', $recipeMaterialIds)) ? 'checked' : '' }}>
                        <span class="material-name">{{ $material->name }}</span>
                        <span class="material-unit">({{ $material->unit }})</span>
                    </label>
                    <input
                        type="number"
                        name="materials[{{ $material->id }}]"
                        step="0.001"
                        value="{{ old('materials.' . $material->id, $recipeMaterials[$material->id] ?? 0) }}"
                        placeholder="数量を入力"
                        class="quantity-input">
                    <input
                        type="number"
                        name="tolerances[{{ $material->id }}]"
                        step="0.001"
                        value="{{ old('tolerances.' . $material->id, $recipeTolerance[$material->id] ?? '') }}"
                        placeholder="許容範囲"
                        class="tolerance-input">
                    @error('materials.' . $material->id)
                    <div class="error">{{ $message }}</div>
                    @enderror
                </div>
                @endforeach
            </div>
        </div>

        {{-- 材料マスタに無い材料をその場で作ってレシピに加える欄（材料名と単位が両方入力されたときだけ有効） --}}
        <div class="form-section">
            <h3>新しい材料を追加</h3>
            <p style="font-size: 13px; color: #666; margin-bottom: 15px;">新しい材料を作成して、このレシピに追加します。</p>
            <div class="new-material-section">
                <div class="form-group">
                    <label for="new_material_name">材料名</label>
                    <input
                        type="text"
                        id="new_material_name"
                        name="new_material_name"
                        value="{{ old('new_material_name', '') }}"
                        placeholder="例: 塩、砂糖など"
                        class="form-input">
                    @error('new_material_name')
                    <div class="error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="new_material_unit">単位</label>
                    <input
                        type="text"
                        id="new_material_unit"
                        name="new_material_unit"
                        value="{{ old('new_material_unit', '') }}"
                        placeholder="例: g, ml, 個など"
                        class="form-input">
                    @error('new_material_unit')
                    <div class="error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="new_material_quantity">このレシピでの数量</label>
                    <input
                        type="number"
                        id="new_material_quantity"
                        name="new_material_quantity"
                        step="0.001"
                        value="{{ old('new_material_quantity', '') }}"
                        placeholder="例: 100"
                        class="form-input">
                    @error('new_material_quantity')
                    <div class="error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="new_material_tolerance">許容範囲</label>
                    <input
                        type="number"
                        id="new_material_tolerance"
                        name="new_material_tolerance"
                        step="0.001"
                        value="{{ old('new_material_tolerance', '') }}"
                        placeholder="例: 5"
                        class="form-input">
                    @error('new_material_tolerance')
                    <div class="error">{{ $message }}</div>
                    @enderror
                </div>

                <p style="font-size: 12px; color: #999; margin-top: 10px;">
                    💡 材料名と単位が空白の場合は、新しい材料は追加されません
                </p>
            </div>
        </div>

        {{-- 更新・キャンセルボタン --}}
        <div class="form-actions">
            <button type="submit" class="btn">✏️ 更新</button>
            <a href="{{ route('recipes.index') }}" class="btn btn-secondary">キャンセル</a>
        </div>
    </form>
</div>

<div class="action-buttons">
    <a href="{{ route('index') }}" class="btn btn-secondary">← トップに戻る</a>
</div>

@endsection