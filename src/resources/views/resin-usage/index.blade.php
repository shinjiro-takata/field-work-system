@extends('layouts.app')

@section('title', 'レジン使用量確認表')
@section('brand', 'レジン使用量確認表')
@section('brand-link', route('resin-usage.index'))
@section('show-home', true)

@section('css')
<link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
@endsection

@section('content')
<h1>レジン使用量確認表</h1>

<div class="menu-grid">
    <a href="{{ route('resin-usage.history') }}" class="menu-item">
        <div class="menu-item__icon">📋</div>
        <div class="menu-item__title">履歴の確認</div>
        <div class="menu-item__description">登録済みのレジン使用量を確認します</div>
    </a>

    <a href="{{ route('resin-usage.create') }}" class="menu-item">
        <div class="menu-item__icon">➕</div>
        <div class="menu-item__title">新規入力</div>
        <div class="menu-item__description">新しいレジン使用量を入力します</div>
    </a>

    @if(Auth::user()->is_admin)
    <a href="{{ route('resin-usage.settings.edit') }}" class="menu-item admin-only">
        <div class="menu-item__icon">⚙️</div>
        <div class="menu-item__title">レジン材料の設定</div>
        <div class="menu-item__description">使用量を集計する2種類の材料を選択します</div>
    </a>
    @endif
</div>
@endsection
