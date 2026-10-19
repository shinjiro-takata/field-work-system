@extends('layouts.equipment')

@section('title', $editing ? '点検記録の編集' : '点検入力')

@section('content')
    {{-- 画面タイトルと、履歴または記録確認画面へのリンクを表示する。 --}}
    <div class="page-heading">
        <div>
            <p class="eyebrow">{{ $editing ? 'EDIT INSPECTION' : 'FACILITY MANAGEMENT' }}</p>
            <h1>{{ $editing ? '点検記録を編集' : '日常点検' }}</h1>
            <p class="page-description">
                @if ($editing)
                    編集内容と編集者名が履歴に記録されます。
                @else
                    設備の状態を確認し、日々の点検記録を残しましょう。
                @endif
            </p>
        </div>
        @if ($editing)
            <a class="button" href="{{ route('inspections.show', $record) }}">確認画面に戻る</a>
        @else
            <div class="tabbar">
                <a class="tab-link active" href="{{ route('inspections.create') }}">点検入力</a>
                <a class="tab-link" href="{{ route('inspections.index') }}">点検履歴</a>
            </div>
        @endif
    </div>

    {{-- 新規登録と明示的な編集で共通の点検フォームを使用する。 --}}
    <form data-inspection-form method="POST" action="{{ $editing ? route('inspections.update', $record) : route('inspections.store') }}">
        @csrf
        @if ($editing) @method('PUT') @endif
        @if ($errors->any())
            <div class="error-box" role="alert">
                入力内容を確認してください。
                <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        <div class="panel">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">{{ $editing ? '点検内容の編集' : '点検内容' }}</h2>
                    <p class="panel-subtitle">
                        @if ($editing)
                            点検結果を変更し、編集者名を入力して保存してください。
                        @else
                            状態を選択してください。圧力計は実測値をMPaで入力できます。
                        @endif
                    </p>
                </div>
            </div>
            <div class="form-meta">
                <div>
                    <label class="field-label" for="inspection-date">点検日</label>
                    @if ($editing)
                        <input class="text-field" id="inspection-date" type="date" value="{{ $record->inspection_date->format('Y-m-d') }}" disabled>
                    @else
                        <input class="text-field" id="inspection-date" name="inspection_date" type="date" value="{{ old('inspection_date', now()->format('Y-m-d')) }}" required>
                    @endif
                </div>
                <div>
                    <span class="field-label">点検担当者</span>
                    <input class="text-field" type="text" value="{{ $editing ? $record->inspector : Auth::user()->name }}" disabled>
                </div>
                @if ($editing)
                    <div>
                        <span class="field-label">編集者</span>
                        <input class="text-field" type="text" value="{{ Auth::user()->name }}" disabled>
                    </div>
                @endif
            </div>

            @foreach ($groups as $groupIndex => $group)
                <section aria-label="{{ $group['name'] }}">
                    <div class="section-heading">
                        <h3 class="section-name"><span class="section-index">{{ str_pad($groupIndex + 1, 2, '0', STR_PAD_LEFT) }}</span>{{ $group['name'] }}</h3>
                        <span class="section-count">{{ count($group['items']) }}項目</span>
                    </div>
                    {{-- 目視点検には状態選択を、圧力計には数値入力欄を表示する。 --}}
                    @foreach ($group['items'] as $itemIndex => $item)
                        @php
                            $value = old('results.' . $item['id'], $record ? ($record->results[$item['id']] ?? []) : []);
                            $number = 1;
                            foreach (array_slice($groups, 0, $groupIndex) as $previousGroup) $number += count($previousGroup['items']);
                            $number += $itemIndex;
                        @endphp
                        <div class="check-row">
                            <span class="row-number">{{ str_pad($number, 2, '0', STR_PAD_LEFT) }}</span>
                            <div class="location-cell"><span class="cell-label">点検箇所</span><span class="cell-main">{{ $item['location'] }}</span></div>
                            <div class="item-cell"><span class="cell-label">点検項目</span><span class="cell-main">{{ $item['item'] }}</span></div>
                            <span class="method method-cell">{{ $item['method'] }}</span>
                            @if (($item['type'] ?? null) === 'pressure')
                                <div class="pressure-editor">
                                    <label class="cell-label" for="pressure-{{ $item['id'] }}">圧力計の実測値</label>
                                    <div class="pressure-field">
                                        <input class="pressure-input" id="pressure-{{ $item['id'] }}" type="number" step="any" name="results[{{ $item['id'] }}][pressure]" value="{{ is_array($value) ? ($value['pressure'] ?? '') : '' }}" placeholder="数値を入力" aria-label="{{ $group['name'] }}の圧力計の値">
                                        <span class="pressure-unit">MPa</span>
                                    </div>
                                </div>
                            @else
                                <div class="result-controls" role="group" aria-label="{{ $item['location'] }} {{ $item['item'] }}の点検結果">
                                    <label>
                                        <input class="result-radio" type="radio" name="results[{{ $item['id'] }}][status]" value="ok" {{ is_array($value) && ($value['status'] ?? '') === 'ok' ? 'checked' : '' }}>
                                        <span class="result-choice">良好</span>
                                    </label>
                                    <label>
                                        <input class="result-radio" type="radio" name="results[{{ $item['id'] }}][status]" value="ng" {{ is_array($value) && ($value['status'] ?? '') === 'ng' ? 'checked' : '' }}>
                                        <span class="result-choice">要対応</span>
                                    </label>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </section>
            @endforeach

            {{-- 任意のメモ欄と、フォームの保存操作を表示する。 --}}
            <div class="note-wrap">
                <label class="field-label" for="inspection-note">全体メモ（任意）</label>
                <textarea class="note-field" id="inspection-note" name="note" maxlength="1000" placeholder="気づいたこと、引き継ぎ事項など">{{ old('note', $record ? $record->note : '') }}</textarea>
            </div>
            <div class="panel-footer">
                <div class="footer-hint">入力内容はサーバーのデータベースに保存されます。<br>圧力計の値はマイナス値・小数も入力できます。</div>
                <div class="button-group">
                    @if ($editing)<a class="button" href="{{ route('inspections.show', $record) }}">キャンセル</a>@endif
                    <button class="button button-primary" type="submit">{{ $editing ? '変更を保存' : '点検を保存' }}</button>
                </div>
            </div>
        </div>
    </form>
@endsection
