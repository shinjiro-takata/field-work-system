@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/logs.css') }}">
@endsection

@section('content')
{{-- ===== 画面タイトル ===== --}}
<h1>計量データの入力</h1>

<div class="form-container">
    {{-- ===== フォーム1: レシピ選択（選ぶと自動送信され、選んだレシピの入力欄が表示される） ===== --}}
    <form action="{{ route('logs.create') }}" method="GET" class="recipe-selection-form">
        <div class="form-group">
            <label for="recipe_id">レシピを選択</label>
            <select id="recipe_id" name="recipe_id" required class="form-select" onchange="if (this.value) this.form.submit()">
                <option value="">-- レシピを選択してください --</option>
                @foreach($recipes as $r)
                <option value="{{ $r->id }}" {{ (isset($selectedRecipeId) && $selectedRecipeId == $r->id) ? 'selected' : '' }}>{{ $r->name }}</option>
                @endforeach
            </select>
            @error('recipe_id')
            <div class="error">{{ $message }}</div>
            @enderror
        </div>
    </form>

    {{-- ===== フォーム2: 材料入力（レシピが選ばれている場合だけ表示） ===== --}}
    @if(isset($recipe) && $recipe)
    <div class="material-form-container">
        <h2>{{ $recipe->name }}</h2>

        <form action="{{ route('logs.store') }}" method="POST">
            @csrf
            <input type="hidden" name="recipe_id" value="{{ $recipe->id }}">

            {{-- 計量日時（初期値は現在日時。old() = 入力エラーで戻ったときは直前の入力値） --}}
            <div class="form-group">
                <label for="weighing_date">計量日時</label>
                <input type="datetime-local" id="weighing_date" name="weighed_at" value="{{ old('weighed_at', $defaultDateTime ?? '') }}" required>
                @error('weighed_at')
                <div class="error">{{ $message }}</div>
                @enderror
            </div>

            {{-- レシピの各材料の実測量入力欄（基準量・許容範囲を横に表示） --}}
            <div class="materials-section">
                <h3>材料と数量</h3>
                <div class="materials-grid">
                    @foreach($recipe->materials as $material)
                    <div class="material-input-group">
                        <label for="material_{{ $material->id }}" class="material-label">
                            {{ $material->name }}
                            <span class="material-spec">{{ $material->formatted_quantity }}{{ $material->unit }}</span>
                            @if($material->pivot->tolerance)
                            <span class="tolerance-spec">(±{{ $material->pivot->tolerance }}{{ $material->unit }})</span>
                            @endif
                        </label>
                        <input
                            type="number"
                            id="material_{{ $material->id }}"
                            name="materials[{{ $material->id }}]"
                            step="0.001"
                            value="{{ old('materials.' . $material->id, 0) }}"
                            required
                            class="material-input"
                            data-standard="{{ $material->formatted_quantity }}"
                            data-tolerance="{{ $material->pivot->tolerance ?? '' }}">
                        <span class="material-unit">{{ $material->unit }}</span>
                        @error('materials.' . $material->id)
                        <div class="error">{{ $message }}</div>
                        @enderror
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- レシピ外の材料欄（部分ビュー） --}}
            @include('logs._extra-materials', [
                'selectableMaterials' => $selectableMaterials,
                'extraRows' => old('extra_materials', []),
            ])

            {{-- 備考（レシピ外材料を使った場合は理由を必須とする） --}}
            <div class="form-group">
                <label for="remarks">備考</label>
                <textarea id="remarks" name="notes" class="form-textarea">{{ old('notes') }}</textarea>
                @error('notes')
                <div class="error">{{ $message }}</div>
                @enderror
            </div>

            {{-- 保存・キャンセルボタン --}}
            <div class="form-actions">
                <button type="submit" class="btn">💾 保存</button>
                <a href="{{ route('logs.index') }}" class="btn btn-secondary">キャンセル</a>
            </div>
        </form>
    </div>
    @else
    {{-- レシピ未選択のときの案内 --}}
    <div class="info-box">
        <p>レシピを選択して、材料の計量データを入力してください。</p>
    </div>
    @endif
</div>

<div class="action-buttons">
    <a href="{{ route('index') }}" class="btn btn-secondary">← トップに戻る</a>
</div>

{{-- ===== JavaScript: 入力した実測量が許容範囲外なら入力欄に out-of-tolerance クラスを付けて強調する ===== --}}
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.material-input').forEach(input => {
            // 基準量±許容量の範囲外かを判定し、クラスの付け外しをする
            const checkTolerance = () => {
                const standard = parseFloat(input.dataset.standard);
                const tolerance = parseFloat(input.dataset.tolerance);
                const actual = parseFloat(input.value);

                // 基準量・実測量が数値でなければ判定しない
                if (isNaN(standard) || isNaN(actual)) {
                    input.classList.remove('out-of-tolerance');
                    return;
                }

                // 許容範囲が設定されている（0より大きい）場合のみチェック
                if (!isNaN(tolerance) && tolerance > 0) {
                    const minValue = standard - tolerance;
                    const maxValue = standard + tolerance;
                    const isOut = actual < minValue || actual > maxValue;
                    input.classList.toggle('out-of-tolerance', isOut);
                } else {
                    input.classList.remove('out-of-tolerance');
                }
            };

            // 入力のたびに判定し、画面表示時にも1回判定する
            input.addEventListener('input', checkTolerance);
            checkTolerance();
        });
    });
</script>

@endsection