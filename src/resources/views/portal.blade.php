@extends('layouts.app')

@section('title', '現場業務システム')
@section('brand', '現場業務システム')

@section('css')
<link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
@endsection

@section('content')
{{-- 利用するアプリの選択。アプリが増えたらカードを追加する。 --}}
<div class="menu-grid">
    {{-- 設備点検アプリ --}}
    <a href="{{ route('home') }}" class="menu-item">
        <div class="menu-item__icon">🔧</div>
        <div class="menu-item__title">設備点検</div>
        <div class="menu-item__description">日常・週次・月次・定期の設備点検を記録します</div>
    </a>

    {{-- 配合手順確認書アプリ（計量ログ・レシピ） --}}
    <a href="{{ route('index') }}" class="menu-item">
        <div class="menu-item__icon">⚖️</div>
        <div class="menu-item__title">配合手順確認書</div>
        <div class="menu-item__description">計量ログの確認・入力、レシピ管理を行います</div>
    </a>

    {{-- レジン使用量確認表アプリ --}}
    <a href="{{ route('resin-usage.index') }}" class="menu-item">
        <div class="menu-item__icon">🧪</div>
        <div class="menu-item__title">レジン使用量確認表</div>
        <div class="menu-item__description">レジン使用量の履歴確認・新規入力を行います</div>
    </a>

    {{-- 管理者のみ: アカウント管理 --}}
    @if(Auth::user()->is_admin)
    <a href="{{ route('users.index') }}" class="menu-item admin-only">
        <div class="menu-item__icon">👥</div>
        <div class="menu-item__title">アカウント管理</div>
        <div class="menu-item__description">ユーザーアカウントを管理します</div>
    </a>
    @endif
</div>
@endsection
