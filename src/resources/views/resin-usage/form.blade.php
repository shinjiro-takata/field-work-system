@extends('layouts.app')

@section('title', ($record ? '記録の修正' : '新規入力') . ' | レジン使用量確認表')
@section('brand', 'レジン使用量確認表')
@section('brand-link', route('resin-usage.index'))
@section('show-home', true)

@section('css')
<link rel="stylesheet" href="{{ asset('css/users.css') }}">
<link rel="stylesheet" href="{{ asset('css/logs.css') }}">
@endsection

@section('content')
<h1>{{ $record ? '記録の修正' : 'レジン使用量の新規入力' }}</h1>

<form action="{{ route('resin-usage.create') }}" method="GET" class="form-container">
    <div class="form-group">
        <label for="usage-date-picker">入力する日付</label>
        <input type="date" id="usage-date-picker" name="date" value="{{ $date }}" required onchange="this.form.submit()">
    </div>
</form>

<form action="{{ $record ? route('resin-usage.update', $record) : route('resin-usage.store') }}" method="POST" class="form-container">
    @csrf
    @if($record)
        @method('PUT')
    @else
        <input type="hidden" name="usage_date" value="{{ $date }}">
    @endif

    @if($record)
        <div class="form-group">
            <label>日付</label>
            <p>{{ $record->usage_date->format('Y年n月j日') }}</p>
        </div>
    @endif

    <div class="form-group">
        <label for="operator_name">確認者（作業者）</label>
        <input type="text" id="operator_name" name="operator_name" value="{{ $operatorName }}" maxlength="255" required>
        @error('operator_name')<div class="error">{{ $message }}</div>@enderror
    </div>

    <div class="materials-section">
        <h2>レジン使用量・在庫</h2>
        <p>使用量は配合手順確認書の計量ログから、選択した日付・材料ごとに自動集計します。</p>
        @foreach($materials as $material)
            @php
                $item = $record ? $record->items->firstWhere('material_id', $material->id) : null;
                $opening = $openingStocks[$material->id] ?? null;
                $used = $usage[$material->id] ?? 0;
            @endphp
            <section class="material-form-container resin-usage-item">
                <h3>{{ $material->name }}（{{ $material->unit }}）</h3>

                <div class="form-group">
                    <label>{{ in_array($material->id, $initialStockMaterials, true) ? '設定済み初回在庫' : '前回在庫' }}</label>
                    @if(is_null($opening))
                        <input
                            type="number"
                            name="items[{{ $material->id }}][opening_stock]"
                            value="{{ old('items.' . $material->id . '.opening_stock', $item ? $item->opening_stock : '') }}"
                            min="0"
                            step="0.001"
                            class="resin-opening"
                            required>
                        @error('items.' . $material->id . '.opening_stock')<div class="error">{{ $message }}</div>@enderror
                    @else
                        <p class="resin-opening" data-opening="{{ $opening }}">{{ number_format($opening, 3) }} {{ $material->unit }}</p>
                    @endif
                </div>

                <div class="form-group">
                    <label>当日の使用量（自動集計）</label>
                    <p>{{ number_format($used, 3) }} {{ $material->unit }}</p>
                </div>

                <div class="form-group">
                    <label for="replenished-{{ $material->id }}">補充数（{{ $material->unit }}）</label>
                    <input
                        type="number"
                        id="replenished-{{ $material->id }}"
                        name="items[{{ $material->id }}][replenished_quantity]"
                        value="{{ old('items.' . $material->id . '.replenished_quantity', $item ? $item->replenished_quantity : '0') }}"
                        min="0"
                        step="0.001"
                        required
                        class="resin-replenished"
                        data-used="{{ $used }}"
                        data-material="{{ $material->id }}">
                    @error('items.' . $material->id . '.replenished_quantity')<div class="error">{{ $message }}</div>@enderror
                </div>

                <div class="form-group">
                    <label>計算上の在庫量（前回在庫 + 補充数 - 使用量）</label>
                    <p><strong id="calculated-{{ $material->id }}">{{ $item ? number_format($item->calculated_stock, 3) : '—' }}</strong> {{ $material->unit }}</p>
                </div>

                <div class="form-group">
                    <label for="actual-{{ $material->id }}">目視で確認した実在庫量（{{ $material->unit }}）</label>
                    <input
                        type="number"
                        id="actual-{{ $material->id }}"
                        name="items[{{ $material->id }}][actual_stock]"
                        class="resin-actual"
                        value="{{ old('items.' . $material->id . '.actual_stock', $item ? $item->actual_stock : '') }}"
                        min="0"
                        step="0.001"
                        required>
                    <p>差異（実在庫 − 計算在庫）：<strong id="difference-{{ $material->id }}">—</strong> {{ $material->unit }}</p>
                    @error('items.' . $material->id . '.actual_stock')<div class="error">{{ $message }}</div>@enderror
                </div>

                <div class="form-group">
                    <label for="reason-{{ $material->id }}">差異の理由（計算在庫と実在庫が異なる場合は必須）</label>
                    <input
                        type="text"
                        id="reason-{{ $material->id }}"
                        name="items[{{ $material->id }}][adjustment_reason]"
                        value="{{ old('items.' . $material->id . '.adjustment_reason', $item ? $item->adjustment_reason : '') }}"
                        maxlength="255">
                    @error('items.' . $material->id . '.adjustment_reason')<div class="error">{{ $message }}</div>@enderror
                </div>

            </section>
        @endforeach
    </div>

    <div class="form-group">
        <input type="hidden" name="supervisor_confirmed" value="0">
        <label for="supervisor_confirmed">
            <input type="checkbox" id="supervisor_confirmed" name="supervisor_confirmed" value="1"
                {{ old('supervisor_confirmed', $record && $record->supervisor_confirmed_at ? '1' : '') ? 'checked' : '' }}>
            上司が確認しました
        </label>
    </div>
    <div class="form-group">
        <label for="supervisor_name">上司の確認者名</label>
        <input type="text" id="supervisor_name" name="supervisor_name"
            value="{{ old('supervisor_name', $record ? $record->supervisor_name : '') }}" maxlength="255">
        @error('supervisor_name')<div class="error">{{ $message }}</div>@enderror
    </div>
    @if($record && $record->supervisor_confirmed_at)
        <p>確認日時：{{ $record->supervisor_confirmed_at->format('Y年n月j日 H:i') }}</p>
    @endif

    <div class="form-actions">
        <button type="submit" class="btn">保存する</button>
        <a href="{{ route('resin-usage.history') }}" class="btn btn-secondary">履歴に戻る</a>
    </div>
</form>

<script>
    document.querySelectorAll('.resin-replenished').forEach(function (input) {
        const materialId = input.dataset.material;
        const openingElement = input.closest('.resin-usage-item').querySelector('.resin-opening');
        const openingInput = openingElement.matches('input') ? openingElement : null;
        const output = document.getElementById('calculated-' + materialId);
        const actualInput = document.getElementById('actual-' + materialId);
        const differenceOutput = document.getElementById('difference-' + materialId);

        function updateCalculatedStock() {
            const opening = openingInput ? parseFloat(openingInput.value) : parseFloat(openingElement.dataset.opening);
            const replenished = parseFloat(input.value);
            const used = parseFloat(input.dataset.used);
            const actual = parseFloat(actualInput.value);
            if (Number.isFinite(opening) && Number.isFinite(replenished)) {
                const calculated = opening + replenished - used;
                output.textContent = calculated.toFixed(3);
                differenceOutput.textContent = Number.isFinite(actual)
                    ? (actual - calculated > 0 ? '+' : '') + (actual - calculated).toFixed(3)
                    : '—';
                return;
            }

            output.textContent = '—';
            differenceOutput.textContent = '—';
        }

        input.addEventListener('input', updateCalculatedStock);
        actualInput.addEventListener('input', updateCalculatedStock);
        if (openingInput) {
            openingInput.addEventListener('input', updateCalculatedStock);
        }
        updateCalculatedStock();
    });
</script>
@endsection
