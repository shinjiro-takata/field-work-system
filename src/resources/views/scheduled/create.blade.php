@extends('layouts.equipment')

@section('title', $editing ? $type['name'] . '記録の編集' : $type['name'])

@section('content')
    {{-- 画面タイトルと、履歴または記録確認画面へのリンクを表示する。 --}}
    <div class="page-heading">
        <div>
            <p class="eyebrow">{{ $editing ? 'EDIT INSPECTION' : 'FACILITY MANAGEMENT' }}</p>
            <h1>{{ $editing ? $type['name'] . '記録を編集' : $type['name'] }}</h1>
            <p class="page-description">
                @if ($editing)
                    編集内容と編集者名が履歴に記録されます。
                @else
                    {{ $type['subtitle'] }}
                @endif
            </p>
        </div>
        @if ($editing)
            <a class="button" href="{{ route('inspections.' . $type['key'] . '.show', $record) }}">確認画面に戻る</a>
        @else
            <div class="tabbar">
                <a class="tab-link active" href="{{ route('inspections.' . $type['key'] . '.create') }}">点検入力</a>
                <a class="tab-link" href="{{ route('inspections.' . $type['key'] . '.index') }}">点検履歴</a>
            </div>
        @endif
    </div>

    {{-- 新規登録と編集で共通のフォームを使い、送信先だけを切り替える。 --}}
    {{-- 入力エラーがある場合は、サーバーから返された内容をまとめて表示する。 --}}
    <form data-inspection-form method="POST" action="{{ $editing ? route('inspections.' . $type['key'] . '.update', $record) : route('inspections.' . $type['key'] . '.store') }}">
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
                            状態を選択してください。数値項目は実測値を入力できます。
                        @endif
                    </p>
                </div>
            </div>
            {{-- 点検日・担当者を表示し、編集時は編集者も確認できるようにする。 --}}
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

            {{-- チェックリストをグループごとに表示し、項目の入力形式に合わせて欄を切り替える。 --}}
            @php $itemNumber = 0; @endphp
            @foreach ($groups as $groupIndex => $group)
                <section aria-label="{{ $group['name'] }}">
                    <div class="section-heading">
                        <h3 class="section-name"><span class="section-index">{{ str_pad($groupIndex + 1, 2, '0', STR_PAD_LEFT) }}</span>{{ $group['name'] }}</h3>
                        <span class="section-count">{{ count($group['items']) }}項目</span>
                    </div>
                    @foreach ($group['items'] as $item)
                        @php
                            $value = old('results.' . $item['id'], $record ? ($record->results[$item['id']] ?? []) : []);
                            $number = ++$itemNumber;
                        @endphp
                        <div class="check-row {{ ($item['type'] ?? null) === 'oil' ? 'check-row-oil' : '' }}">
                            <span class="row-number">{{ str_pad($number, 2, '0', STR_PAD_LEFT) }}</span>
                            <div class="location-cell"><span class="cell-label">点検箇所</span><span class="cell-main">{{ $item['location'] }}</span></div>
                            @if (($item['type'] ?? null) !== 'oil')<div class="item-cell"><span class="cell-label">点検項目</span><span class="cell-main">{{ $item['item'] }}</span></div>@endif
                            <span class="method method-cell">{{ $item['method'] }}</span>
                            @if (($item['type'] ?? null) === 'oil')
                                <div class="oil-editor">
                                    {{-- 選択肢が複数ある機器だけ、補充か交換かを選べる。 --}}
                                    <div class="result-controls" role="group" aria-label="{{ $item['location'] }}の作業の種類">
                                        @foreach ($item['actions'] as $action)
                                            <label>
                                                <input class="result-radio" type="radio" name="results[{{ $item['id'] }}][action]" value="{{ $action }}" {{ (is_array($value) && ($value['action'] ?? '') === $action) || count($item['actions']) === 1 ? 'checked' : '' }}>
                                                <span class="result-choice">{{ \App\Support\PeriodicChecklist::actionLabels()[$action] }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    <div class="pressure-field">
                                        <input class="pressure-input" id="value-{{ $item['id'] }}" type="number" step="any" name="results[{{ $item['id'] }}][value]" value="{{ is_array($value) ? ($value['value'] ?? '') : '' }}" placeholder="量を入力" aria-label="{{ $item['location'] }}の量">
                                        <span class="pressure-unit">{{ $item['unit'] }}</span>
                                    </div>
                                </div>
                            @elseif (($item['type'] ?? null) === 'numeric')
                                <div class="pressure-editor">
                                    <label class="cell-label" for="value-{{ $item['id'] }}">{{ $item['item'] }}の実測値</label>
                                    <div class="pressure-field">
                                        <input class="pressure-input" id="value-{{ $item['id'] }}" type="number" step="any" name="results[{{ $item['id'] }}][value]" value="{{ is_array($value) ? ($value['value'] ?? '') : '' }}" placeholder="数値を入力" aria-label="{{ $group['name'] }}の{{ $item['item'] }}の値">
                                        @if (!empty($item['unit']))
                                            <span class="pressure-unit">{{ $item['unit'] }}</span>
                                        @endif
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

            {{-- 任意の全体メモと、入力内容を保存する操作を表示する。 --}}
            <div class="note-wrap">
                <label class="field-label" for="inspection-note">全体メモ（任意）</label>
                <textarea class="note-field" id="inspection-note" name="note" maxlength="1000" placeholder="気づいたこと、引き継ぎ事項など">{{ old('note', $record ? $record->note : '') }}</textarea>
            </div>
            <div class="panel-footer">
                <div class="footer-hint">入力内容はサーバーのデータベースに保存されます。<br>{!! nl2br(e($type['footnote'] ?? '※異常が発生した場合は修理依頼書を作成し設備担当者まで連絡')) !!}</div>
                <div class="button-group">
                    @if ($editing)<a class="button" href="{{ route('inspections.' . $type['key'] . '.show', $record) }}">キャンセル</a>@endif
                    <button class="button button-primary" type="submit">{{ $editing ? '変更を保存' : '点検を保存' }}</button>
                </div>
            </div>
        </div>
    </form>
@endsection
