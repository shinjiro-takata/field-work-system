@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/recipes.css') }}">
@endsection

@section('content')
{{-- ===== 画面タイトル ===== --}}
<h1>レシピ管理</h1>

{{-- 管理者のみ: 新規作成ボタン --}}
@if(Auth::user()->is_admin)
<div class="action-buttons">
    <a href="{{ route('recipes.create') }}" class="btn">🆕 新規レシピ作成</a>
</div>
@endif

{{-- ===== レシピのカード一覧（レシピ1件=カード1枚、材料と基準量を表示） ===== --}}
<div class="recipes-grid">
    @forelse($recipes as $recipe)
    <div class="recipe-card">
        <div class="recipe-card__header">
            <h3 class="recipe-card__title">{{ $recipe->name }}</h3>
        </div>

        <div class="recipe-card__body">
            <div class="materials-list">
                <h4 class="materials-list__title">材料</h4>
                <ul>
                    @foreach($recipe->materials as $material)
                    <li>
                        <span class="material-name">{{ $material->name }}</span>
                        <span class="material-quantity">{{ $material->pivot->quantity }}{{ $material->unit }}</span>
                    </li>
                    @endforeach
                </ul>
            </div>
        </div>

        {{-- 管理者のみ: 編集・削除（削除は確認ダイアログを出す） --}}
        @if(Auth::user()->is_admin)
        <div class="recipe-card__actions">
            <a href="{{ route('recipes.edit', $recipe->id) }}" class="action-link">編集</a>
            <form action="{{ route('recipes.destroy', $recipe->id) }}" method="POST" class="delete-form">
                @csrf
                @method('DELETE')
                <button type="submit" class="action-link action-link-danger" onclick="return confirm('このレシピを削除しますか？');">削除</button>
            </form>
        </div>
        @endif
    </div>
    {{-- レシピが1件も無いときの表示 --}}
    @empty
    <div class="empty-state">
        <p>レシピが登録されていません。</p>
        @if(Auth::user()->is_admin)
        <a href="{{ route('recipes.create') }}" class="btn">新規レシピを作成</a>
        @endif
    </div>
    @endforelse
</div>

<div class="action-buttons">
    <a href="{{ route('index') }}" class="btn btn-secondary">← トップに戻る</a>
</div>

@endsection