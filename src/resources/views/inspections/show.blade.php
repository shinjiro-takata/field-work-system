@extends('layouts.equipment')

@section('title', '点検記録の確認')

@section('content')
    {{-- Compute summary badges from the saved checklist values. --}}
    @php
        $checked = count($record->results ?? []);
        $issues = collect($record->results ?? [])->where('status', 'ng')->count();
    @endphp
    {{-- The record remains read-only until the user chooses the edit action. --}}
    <div class="page-heading">
        <div>
            <p class="eyebrow">INSPECTION RECORD</p>
            <h1>点検記録の確認</h1>
            <p class="page-description">この画面は確認専用です。変更する場合は「編集する」を押してください。</p>
        </div>
        <div class="button-group">
            <a class="button" href="{{ route('inspections.index') }}">履歴に戻る</a>
            <a class="button button-primary" href="{{ route('inspections.edit', $record) }}">編集する</a>
        </div>
    </div>
    <div class="panel">
        <div class="panel-header">
            <div><h2 class="panel-title">{{ $record->inspection_date->format('Y年n月j日') }} の点検</h2><p class="panel-subtitle">点検担当：{{ $record->inspector }}</p></div>
            @if ($issues)<span class="badge has-ng">要対応 {{ $issues }}件</span>@elseif ($checked < count($items))<span class="badge incomplete">未完了</span>@else<span class="badge">完了</span>@endif
        </div>
        @if ($record->last_edited_by)
            <div class="summary-row"><div class="summary-label-field">最終編集</div><div class="summary-content">{{ $record->last_edited_by }} ・ {{ $record->updated_at->format('Y年n月j日 H:i') }}</div></div>
        @endif
        {{-- Show all checklist items, including those that were left blank. --}}
        @foreach ($items as $id => $item)
            @if ($loop->first || $previousGroup !== $item['group'])
                @php $previousGroup = $item['group']; @endphp
                <div class="section-heading"><h3 class="section-name">{{ $item['group'] }}</h3></div>
            @endif
            @php $result = $record->results[$id] ?? []; @endphp
            <div class="check-row">
                <span class="row-number">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                <div class="location-cell"><span class="cell-label">点検箇所</span><span class="cell-main">{{ $item['location'] }}</span></div>
                <div class="item-cell"><span class="cell-label">点検項目</span><span class="cell-main">{{ $item['item'] }}</span></div>
                <span class="method method-cell">{{ $item['method'] }}</span>
                @if (($item['type'] ?? null) === 'pressure')
                    <div class="result-controls">{{ $result['pressure'] ?? '未入力' }}@if(isset($result['pressure']))<span class="pressure-unit">MPa</span>@endif</div>
                @else
                    <div class="result-controls">
                        @if (($result['status'] ?? null) === 'ok')<span class="badge">良好</span>
                        @elseif (($result['status'] ?? null) === 'ng')<span class="badge has-ng">要対応</span>
                        @else<span class="badge incomplete">未点検</span>@endif
                    </div>
                @endif
            </div>
        @endforeach
        <div class="summary-row"><div class="summary-label-field">全体メモ</div><div class="summary-content">{!! nl2br(e($record->note ?: 'なし')) !!}</div></div>
        {{-- Display the editor, timestamp, and field-level differences for each revision. --}}
        @if ($record->editLogs->isNotEmpty())
            <section class="edit-log">
                <h3>編集履歴</h3>
                @foreach ($record->editLogs as $log)
                    <div class="log-entry">
                        <div class="log-meta"><span>{{ $log->editor_name }} が編集</span><span class="log-date">{{ $log->created_at->format('Y年n月j日 H:i') }}</span></div>
                        @if ($log->changes)
                            <div class="log-changes">
                                @foreach ($log->changes as $field => $change)
                                    @if ($field === 'results')
                                        @foreach ($change as $label => $values)
                                            {{ $label }}：{{ $values['変更前'] }} → {{ $values['変更後'] }}
                                        @endforeach
                                    @else
                                        {{ $field === 'inspector' ? '点検担当者' : '全体メモ' }}：
                                        {{ $change['変更前'] ?: '未入力' }} → {{ $change['変更後'] ?: '未入力' }}
                                    @endif
                                @endforeach
                            </div>
                        @else
                            <div class="log-changes">内容の変更なし</div>
                        @endif
                    </div>
                @endforeach
            </section>
        @endif
    </div>
@endsection
