@extends('layouts.equipment')

@section('title', '点検履歴')

@section('content')
    {{-- ページの見出しと、点検入力・履歴の切り替えリンク。 --}}
    <div class="page-heading">
        <div>
            <p class="eyebrow">INSPECTION HISTORY</p>
            <h1>点検履歴</h1>
            <p class="page-description">保存された記録を確認できます。編集する場合は記録の詳細画面から操作してください。</p>
        </div>
        <div class="tabbar">
            <a class="tab-link" href="{{ route('inspections.create') }}">点検入力</a>
            <a class="tab-link active" href="{{ route('inspections.index') }}">点検履歴</a>
        </div>
    </div>
    {{-- 保存済み記録の件数と、CSV出力へのリンク。CSVは表計算ソフトで開ける形式。 --}}
    <div class="panel">
        <div class="panel-header">
            <div><h2 class="panel-title">保存済みの記録</h2><p class="panel-subtitle">全 {{ $records->total() }} 件</p></div>
            <a class="button" href="{{ route('inspections.export') }}">
                <svg viewBox="0 0 24 24" fill="none"><path d="M12 3v12m0 0 4-4m-4 4-4-4M5 17v3h14v-3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                CSV出力
            </a>
        </div>
        @if ($records->isEmpty())
            <div class="history-empty"><strong>点検履歴はまだありません</strong>点検結果を保存すると、ここに表示されます。</div>
        @else
            {{-- 履歴の各行から確認画面へ移動する。編集は詳細画面を経由して行う。 --}}
            <div class="history-list">
                @foreach ($records as $record)
                    @php
                        $checked = count($record->results ?? []);
                        $issues = collect($record->results ?? [])->where('status', 'ng')->count();
                    @endphp
                    <div class="history-row">
                        <div class="history-date">{{ $record->inspection_date->format('Y年n月j日') }}</div>
                        <div class="history-person">担当：{{ $record->inspector }}</div>
                        @if ($issues)
                            <span class="badge has-ng">要対応 {{ $issues }}件</span>
                        @elseif ($checked < $itemCount)
                            <span class="badge incomplete">未完了</span>
                        @else
                            <span class="badge">完了</span>
                        @endif
                        <div class="history-count">{{ $checked }} / {{ $itemCount }} 項目</div>
                        <a class="history-open" href="{{ route('inspections.show', $record) }}">記録を確認</a>
                    </div>
                @endforeach
            </div>
            @if ($records->hasPages())
                <div class="pagination">
                    @if ($records->onFirstPage())<span>前へ</span>@else<a href="{{ $records->previousPageUrl() }}">前へ</a>@endif
                    <span class="current">{{ $records->currentPage() }} / {{ $records->lastPage() }}</span>
                    @if ($records->hasMorePages())<a href="{{ $records->nextPageUrl() }}">次へ</a>@else<span>次へ</span>@endif
                </div>
            @endif
        @endif
    </div>
@endsection
