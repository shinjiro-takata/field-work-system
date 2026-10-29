@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
@endsection

@section('content')
{{-- ===== 画面タイトル ===== --}}
<h1>配合手順確認書</h1>

{{-- ===== メニュー一覧（クリックで各機能へ移動するカード） ===== --}}
<div class="menu-grid">
    {{-- 全ユーザー共通のメニュー --}}
    <a href="{{ route('logs.index') }}" class="menu-item">
        <div class="menu-item__icon">📋</div>
        <div class="menu-item__title">計量ログの確認</div>
        <div class="menu-item__description">過去の計量データを検索・確認できます</div>
    </a>

    <a href="{{ route('logs.create') }}" class="menu-item">
        <div class="menu-item__icon">➕</div>
        <div class="menu-item__title">計量データの入力</div>
        <div class="menu-item__description">新しい計量データを入力します</div>
    </a>

    {{-- 一般ユーザーにはレシピの閲覧のみ、管理者にはレシピ・材料の管理メニューを出す --}}
    @if(!Auth::user()->is_admin)
    <a href="{{ route('recipes.index') }}" class="menu-item">
        <div class="menu-item__icon">📖</div>
        <div class="menu-item__title">レシピの確認</div>
        <div class="menu-item__description">レシピの詳細を確認できます</div>
    </a>
    @else
    <a href="{{ route('recipes.index') }}" class="menu-item admin-only">
        <div class="menu-item__icon">⚙️</div>
        <div class="menu-item__title">レシピ管理</div>
        <div class="menu-item__description">レシピを編集・削除できます</div>
    </a>

    <a href="{{ route('recipes.create') }}" class="menu-item admin-only">
        <div class="menu-item__icon">🆕</div>
        <div class="menu-item__title">新規レシピ作成</div>
        <div class="menu-item__description">新しいレシピを作成します</div>
    </a>

    <a href="{{ route('materials.index') }}" class="menu-item admin-only">
        <div class="menu-item__icon">🏷️</div>
        <div class="menu-item__title">材料管理</div>
        <div class="menu-item__description">材料の名前を変更・削除できます</div>
    </a>

    @endif
</div>

@endsection