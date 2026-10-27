@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/logs.css') }}">
<link rel="stylesheet" href="{{ asset('css/users.css') }}">
@endsection

@section('content')
<h1>計量ログの修正</h1>

<div class="form-container">
    <div class="material-form-container">
        <h2>{{ $log->recipe->name }}</h2>

        <form action="{{ route('logs.update', $log) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label for="weighing_date">計量日時</label>
                <input type="datetime-local" id="weighing_date" name="weighed_at" value="{{ old('weighed_at', \Carbon\Carbon::parse($log->weighed_at)->format('Y-m-d\TH:i')) }}" required>
                @error('weighed_at')
                <div class="error">{{ $message }}</div>
                @enderror
            </div>

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

            @php
                $extraRows = old('extra_materials', $extraSelections->map(function ($m) {
                    return ['material_id' => $m->id, 'quantity' => $m->pivot->actual_quantity];
                })->all());
            @endphp
            @include('logs._extra-materials', ['selectableMaterials' => $selectableMaterials, 'extraRows' => $extraRows])

            <div class="form-group">
                <label for="remarks">備考</label>
                <textarea id="remarks" name="notes" class="form-textarea">{{ old('notes', $log->notes) }}</textarea>
                @error('notes')
                <div class="error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-actions">
                <button type="submit" class="btn">💾 修正を保存</button>
                <a href="{{ route('logs.index') }}" class="btn btn-secondary">キャンセル</a>
            </div>
        </form>
    </div>
</div>

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
                @php
                    $before = $editLog->before_data;
                    $after = $editLog->after_data;
                    $beforeMaterials = collect($before['materials'])->keyBy('material_id');
                    $changes = [];
                    if ($before['weighed_at'] !== $after['weighed_at']) {
                        $changes[] = '計量日時：' . $before['weighed_at'] . ' → ' . $after['weighed_at'];
                    }
                    if (($before['notes'] ?? '') !== ($after['notes'] ?? '')) {
                        $changes[] = '備考：' . (($before['notes'] ?? '') !== '' ? $before['notes'] : '（なし）') . ' → ' . (($after['notes'] ?? '') !== '' ? $after['notes'] : '（なし）');
                    }
                    $afterMaterials = collect($after['materials'])->keyBy('material_id');
                    foreach ($after['materials'] as $afterMaterial) {
                        $old = $beforeMaterials->get($afterMaterial['material_id']);
                        if (!$old) {
                            $changes[] = $afterMaterial['name'] . '：追加 ' . number_format($afterMaterial['actual_quantity'], 3) . ' ' . $afterMaterial['unit'];
                        } elseif ((float) $old['actual_quantity'] !== (float) $afterMaterial['actual_quantity']) {
                            $changes[] = $afterMaterial['name'] . '：' . number_format($old['actual_quantity'], 3) . ' → ' . number_format($afterMaterial['actual_quantity'], 3) . ' ' . $afterMaterial['unit'];
                        }
                    }
                    foreach ($before['materials'] as $beforeMaterial) {
                        if (!$afterMaterials->has($beforeMaterial['material_id'])) {
                            $changes[] = $beforeMaterial['name'] . '：削除（' . number_format($beforeMaterial['actual_quantity'], 3) . ' ' . $beforeMaterial['unit'] . '）';
                        }
                    }
                @endphp
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

<div class="action-buttons">
    <a href="{{ route('logs.index') }}" class="btn btn-secondary">← 計量ログ一覧に戻る</a>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.material-input').forEach(input => {
            const check = () => {
                const standard = parseFloat(input.dataset.standard);
                const tolerance = parseFloat(input.dataset.tolerance);
                const actual = parseFloat(input.value);
                const out = !isNaN(standard) && !isNaN(actual) && !isNaN(tolerance) && tolerance > 0
                    && (actual < standard - tolerance || actual > standard + tolerance);
                input.classList.toggle('out-of-tolerance', out);
            };
            input.addEventListener('input', check);
            check();
        });
    });
</script>
@endsection
