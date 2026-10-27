@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/logs.css') }}">
@endsection

@section('content')
<h1>計量データの入力</h1>

<div class="form-container">
    <!-- Form1: レシピ選択 -->
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

    <!-- Form2: 材料入力（recipe がある場合だけ表示） -->
    @if(isset($recipe) && $recipe)
    <div class="material-form-container">
        <h2>{{ $recipe->name }}</h2>

        <form action="{{ route('logs.store') }}" method="POST">
            @csrf
            <input type="hidden" name="recipe_id" value="{{ $recipe->id }}">

            <div class="form-group">
                <label for="weighing_date">計量日時</label>
                <input type="datetime-local" id="weighing_date" name="weighed_at" value="{{ old('weighed_at', $defaultDateTime ?? '') }}" required>
                @error('weighed_at')
                <div class="error">{{ $message }}</div>
                @enderror
            </div>

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

            @include('logs._extra-materials', [
                'selectableMaterials' => $selectableMaterials,
                'extraRows' => old('extra_materials', []),
            ])

            <div class="form-group">
                <label for="remarks">備考</label>
                <textarea id="remarks" name="notes" class="form-textarea">{{ old('notes') }}</textarea>
                @error('notes')
                <div class="error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-actions">
                <button type="submit" class="btn">💾 保存</button>
                <a href="{{ route('logs.index') }}" class="btn btn-secondary">キャンセル</a>
            </div>
        </form>
    </div>
    @else
    <div class="info-box">
        <p>レシピを選択して、材料の計量データを入力してください。</p>
    </div>
    @endif
</div>

<div class="action-buttons">
    <a href="{{ route('index') }}" class="btn btn-secondary">← トップに戻る</a>
</div>

<script>
    // 許容範囲チェック - DOMContentLoaded後に実行
    document.addEventListener('DOMContentLoaded', function() {
        const setupToleranceCheck = () => {
            document.querySelectorAll('.material-input').forEach(input => {
                const checkTolerance = () => {
                    const standard = parseFloat(input.dataset.standard);
                    const tolerance = parseFloat(input.dataset.tolerance);
                    const actual = parseFloat(input.value);

                    console.log('Checking:', {
                        standard,
                        tolerance,
                        actual
                    });

                    if (isNaN(standard) || isNaN(actual)) {
                        input.classList.remove('out-of-tolerance');
                        return;
                    }

                    // 許容範囲が設定されている場合のみチェック
                    if (!isNaN(tolerance) && tolerance > 0) {
                        const minValue = standard - tolerance;
                        const maxValue = standard + tolerance;

                        console.log('Range:', {
                            minValue,
                            maxValue,
                            actual
                        });

                        if (actual < minValue || actual > maxValue) {
                            input.classList.add('out-of-tolerance');
                        } else {
                            input.classList.remove('out-of-tolerance');
                        }
                    } else {
                        input.classList.remove('out-of-tolerance');
                    }
                };

                input.addEventListener('input', checkTolerance);
                // ページロード時にもチェック
                checkTolerance();
            });
        };

        setupToleranceCheck();
    });
</script>

@endsection