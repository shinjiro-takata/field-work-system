@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/logs.css') }}">
@endsection

@section('content')
<h1>計量ログ一覧</h1>

<div class="filter-section">
    <form action="{{ route('logs.index') }}" method="GET" class="search-form">
        <div class="search-form__group">
            <label for="start_date" class="search-form__label">開始日</label>
            <input class="search-form__input search-form__input--date" type="date" id="start_date" name="start_date" value="{{ request('start_date') }}">
        </div>

        <div class="search-form__group">
            <label for="end_date" class="search-form__label">終了日</label>
            <input class="search-form__input search-form__input--date" type="date" id="end_date" name="end_date" value="{{ request('end_date') }}">
        </div>

        <div class="search-form__group">
            <label for="recipe_id" class="search-form__label">レシピ</label>
            <select id="recipe_id" name="recipe_id" class="search-form__select">
                <option value="">全てのレシピ</option>
                @foreach($recipes as $recipe)
                <option value="{{ $recipe->id }}" {{ request('recipe_id') == $recipe->id ? 'selected' : '' }}>{{ $recipe->name }}</option>
                @endforeach
            </select>
        </div>

        <button class="search-form__button" type="submit">検索</button>
    </form>

    <div class="action-section">
        <a href="{{ route('logs.create') }}" class="btn">➕ 新規入力</a>
        <form action="{{ route('logs.export') }}" method="GET" class="export-form">
            <input type="hidden" name="start_date" value="{{ request('start_date') }}">
            <input type="hidden" name="end_date" value="{{ request('end_date') }}">
            <input type="hidden" name="recipe_id" value="{{ request('recipe_id') }}">
            <button type="submit" class="btn btn-secondary">📥 Excelエクスポート</button>
        </form>
    </div>
</div>

<div class="table-responsive">
    <h2>計量ログ一覧</h2>
    <table>
        <thead>
            <tr>
                <th>日時</th>
                <th>レシピ</th>
                <th>備考</th>
                @foreach($allMaterials as $material)
                <th>{{ $material->name }}</th>
                @endforeach
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <tr class="total-row">
                <td colspan="3" data-label="合計"><strong>合計</strong></td>
                @foreach($allMaterials as $material)
                <td data-label="{{ $material->name }}">
                    <strong>
                        {{ number_format($logs->sum(function($log) use($material) { return $log->materials->where('id', $material->id)->first()?->pivot->actual_quantity ?? 0; }), 3) }}
                    </strong>
                </td>
                @endforeach
                <td></td>
            </tr>
            @forelse($logs as $log)
            <tr>
                <td data-label="日時">{{ $log->weighed_at }}</td>
                <td data-label="レシピ">{{ $log->recipe->name }}</td>
                <td data-label="備考">
                    @if(filled($log->notes))
                    <button type="button" class="btn btn-secondary note-open" data-note="{{ $log->notes }}">有</button>
                    @else
                    -
                    @endif
                </td>
                @foreach($allMaterials as $material)
                <td data-label="{{ $material->name }}" class="{{ ($log->material_tolerances[$material->id] ?? false) ? 'out-of-tolerance-log' : '' }}">
                    {{ $log->materials->where('id', $material->id)->first()?->pivot->actual_quantity ?? '-' }}
                    @if($log->off_recipe_materials[$material->id] ?? false)
                    <br><small>レシピ外</small>
                    @endif
                </td>
                @endforeach
                <td data-label="操作">
                    <a href="{{ route('logs.edit', $log) }}" class="btn btn-secondary">修正</a>
                    @if($log->edit_logs_count)
                    <br><small>修正済み（{{ $log->edit_logs_count }}回）</small>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="{{ 4 + count($allMaterials) }}" class="text-center">ログデータがありません</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="action-buttons">
    <a href="{{ route('index') }}" class="btn btn-secondary">← トップに戻る</a>
</div>

<dialog id="note-dialog" style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); margin: 0; max-width: 480px; width: 90%; max-height: 80vh; overflow-y: auto; border: 1px solid #ccc; border-radius: 8px; padding: 20px;">
    <h3>備考</h3>
    <p id="note-dialog-body" style="white-space: pre-wrap; word-break: break-word;"></p>
    <div class="form-actions">
        <button type="button" id="note-dialog-close" class="btn btn-secondary">閉じる</button>
    </div>
</dialog>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const dialog = document.getElementById('note-dialog');
        const body = document.getElementById('note-dialog-body');
        document.querySelectorAll('.note-open').forEach(function(button) {
            button.addEventListener('click', function() {
                body.textContent = button.dataset.note;
                dialog.showModal();
            });
        });
        document.getElementById('note-dialog-close').addEventListener('click', function() {
            dialog.close();
        });
        // 小窓の外側をクリックしても閉じる
        dialog.addEventListener('click', function(e) {
            if (e.target === dialog) {
                dialog.close();
            }
        });
    });
</script>

@endsection