@extends('layouts.app')

@section('title', '現場業務システム')
@section('brand', '現場業務システム')

@section('css')
<link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
@endsection

@section('content')
{{-- 利用するアプリの選択。アプリが増えたらカードを追加する。 --}}
<div class="menu-grid">
    <a href="{{ route('home') }}" class="menu-item">
        <div class="menu-item__icon">🔧</div>
        <div class="menu-item__title">設備点検</div>
        <div class="menu-item__description">日常・週次・月次・定期の設備点検を記録します</div>
    </a>

    <a href="{{ route('index') }}" class="menu-item">
        <div class="menu-item__icon">⚖️</div>
        <div class="menu-item__title">配合手順確認書</div>
        <div class="menu-item__description">計量ログの確認・入力、レシピ管理を行います</div>
    </a>
</div>
@endsection
