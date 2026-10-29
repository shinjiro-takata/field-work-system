@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/logs.css') }}">
<link rel="stylesheet" href="{{ asset('css/users.css') }}">
@endsection

@section('content')
{{-- ===== 画面タイトル ===== --}}
<h1>計量ログの修正</h1>

{{-- ===== 修正フォーム（PUT = 更新のリクエスト。送信先は logs.update） ===== --}}
<div class="form-container">
    <div class="material-form-container">
        <h2>{{ $log->recipe->name }}</h2>

        <form action="{{ route('logs.update', $log) }}" method="POST">
            @csrf
            @method('PUT')

            {{-- 計量日時（old() = 入力エラーで戻ったときに直前の入力値を再表示する） --}}
            <div class="form-group">
                <label for="weighing_date">計量日時</label>
                <input type="datetime-local" id="weighing_date" name="weighed_at" value="{{ old('weighed_at', \Carbon\Carbon::parse($log->weighed_at)->format('Y-m-d\TH:i')) }}" required>
                @error('weighed_at')
                <div class="error">{{ $message }}</div>
                @enderror
            </div>

            {{-- レシピに含まれる材料の実測量入力欄（基準量・許容範囲を横に表示） --}}
            <div class="materials-section">
                <h3>材料と数量</h3>
                <div class="materials-grid">
                    @foreach($log->materials->whereIn('id', $recipeMaterials->keys()) as $material)
                    @php $recipeMaterial = $recipeMaterials->get($material->id); @endphp
                    <div class="material-input-group">
                        <label for="material_{{ $material->id }}" class="material-label">
                            {{ $material->name }}
                            @if($recipeMaterial)
                            <span class="material-spec">{{ number_format($recipeMaterial->pivot->quantity, 3) }}{{ $material->unit }}</span>
                            @if($recipeMaterial->pivot->tolerance)
                            <span class="tolerance-spec">(±{{ $recipeMaterial->pivot->tolerance }}{{ $material->unit }})</span>
                            @endif
                            @endif
                        </label>
                        <input
                            type="number"
                            id="material_{{ $material->id }}"
                            name="materials[{{ $material->id }}]"
                            step="0.001"
                            min="0"
                            value="{{ old('materials.' . $material->id, $material->pivot->actual_quantity) }}"
                            required
                            class="material-input"
                            data-standard="{{ $recipeMaterial ? $recipeMaterial->pivot->quantity : '' }}"
                            data-tolerance="{{ $recipeMaterial ? ($recipeMaterial->pivot->tolerance ?? '') : '' }}">
                        <span class="material-unit">{{ $material->unit }}</span>
                        @error('materials.' . $material->id)
                        <div class="error">{{ $message }}</div>
                        @enderror
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- レシピ外の材料欄。既存の行（入力エラー時は直前の入力、なければ記録済みのレシピ外材料）を渡す --}}
            @php
                $extraRows = old('extra_materials', $extraSelections->map(function ($m) {
                    return ['material_id' => $m->id, 'quantity' => $m->pivot->actual_quantity];
                })->all());
            @endphp
            @include('logs._extra-materials', ['selectableMaterials' => $selectableMaterials, 'extraRows' => $extraRows])

            {{-- 備考（レシピ外材料を使った場合は理由を必須とする） --}}
            <div class="form-group">
                <label for="remarks">備考</label>
                <textarea id="remarks" name="notes" class="form-textarea">{{ old('notes', $log->notes) }}</textarea>
                @error('notes')
                <div class="error">{{ $message }}</div>
                @enderror
            </div>

            {{-- 保存・キャンセルボタン --}}
            <div class="form-actions">
                <button type="submit" class="btn">💾 修正を保存</button>
                <a href="{{ route('logs.index') }}" class="btn btn-secondary">キャンセル</a>
            </div>
        </form>
    </div>
</div>

{{-- ===== 修正履歴（いつ・誰が・何を変更したか） ===== --}}
<section class="log-edit-history">
    <h2>修正履歴</h2>
    @if($editLogs->isEmpty())
    <div class="info-box"><p>修正履歴はありません。</p></div>
    @else
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>修正日時</th>
                    <th>修正者</th>
                    <th>修正内容</th>
                </tr>
            </thead>
            <tbody>
                @foreach($editLogs as $editLog)
                {{-- 修正前後を比べた変更内容の文章リスト（組み立ては ProductionLogEditLog::changeDescriptions） --}}
                @php $changes = $editLog->changeDescriptions(); @endphp
                <tr>
                    <td>{{ $editLog->created_at->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $editLog->editor_name }}</td>
                    <td>
                        @foreach($changes as $change)
                        <div>{{ $change }}</div>
                        @endforeach
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</section>

{{-- ===== 一覧への戻りリンク ===== --}}
<div class="action-buttons">
    <a href="{{ route('logs.index') }}" class="btn btn-secondary">← 計量ログ一覧に戻る</a>
</div>

{{-- ===== JavaScript: 入力した実測量が許容範囲外なら入力欄に out-of-tolerance クラスを付けて強調する ===== --}}
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.material-input').forEach(input => {
            // 基準量±許容量の範囲外かを判定し、クラスの付け外しをする
            // （基準量・許容量が無い、または許容量が0以下の材料は判定しない）
            const check = () => {
                const standard = parseFloat(input.dataset.standard);
                const tolerance = parseFloat(input.dataset.tolerance);
                const actual = parseFloat(input.value);
                const out = !isNaN(standard) && !isNaN(actual) && !isNaN(tolerance) && tolerance > 0
                    && (actual < standard - tolerance || actual > standard + tolerance);
                input.classList.toggle('out-of-tolerance', out);
            };
            // 入力のたびに判定し、画面表示時にも1回判定する
            input.addEventListener('input', check);
            check();
        });
    });
</script>
@endsection
