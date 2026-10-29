<!DOCTYPE html>
{{-- 全画面共通のレイアウト。各画面は @extends('layouts.app') で読み込み、
     @section('content') に本文を、@section('css') に専用CSSを書く。 --}}
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {{-- @yield('名前', 初期値) = 各画面が @section で渡した内容を差し込む（未指定なら初期値） --}}
    <title>@yield('title', '配合手順確認表')</title>
    <link rel="stylesheet" href="{{ asset('css/sanitize.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @yield('css')
</head>

<body>
    {{-- ===== 上部ナビゲーションバー ===== --}}
    <nav class="navbar">
        <div class="navbar__container">
            {{-- 左: ロゴ。brand セクションが指定された画面（ポータル・設備点検など）はその名称を表示する --}}
            <div class="navbar__brand">
                @hasSection('brand')
                <a href="@yield('brand-link', route('portal'))" class="navbar__logo">@yield('brand')</a>
                @else
                <a href="{{ route('index') }}" class="navbar__logo">配合手順確認書</a>
                @endif
            </div>
            {{-- 右: ログイン中はホームボタン・ユーザー名・ログアウト、未ログインならログインボタン --}}
            <div class="navbar__user">
                @if(Auth::check())
                @if(!View::hasSection('brand') || View::hasSection('show-home'))
                <a href="{{ route('portal') }}" class="navbar__home-btn">ホーム</a>
                @endif
                <span class="navbar__user-name">{{ Auth::user()->name }}</span>
                <form action="{{ route('logout') }}" method="POST" class="navbar__logout-form">
                    @csrf
                    <button type="submit" class="navbar__logout-btn">ログアウト</button>
                </form>
                @elseif(!View::hasSection('brand'))
                <a href="{{ route('login') }}" class="btn" style="margin: 0;">ログイン</a>
                @endif
            </div>
        </div>
    </nav>

    {{-- ===== メイン領域 ===== --}}
    <main class="main-content">
        <div class="container">
            {{-- 処理結果のメッセージ（保存しました等。リダイレクト時に with('status', ...) で渡される） --}}
            @if(session('status'))
            <div class="alert alert-success">
                {{ session('status') }}
            </div>
            @endif

            {{-- 入力チェック（バリデーション）のエラー一覧 --}}
            @if($errors->any())
            <div class="alert alert-danger">
                <ul>
                    @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            {{-- 各画面の本文 --}}
            @yield('content')
        </div>
    </main>

    {{-- ===== フッター（brand 指定のある画面では非表示） ===== --}}
    @unless(View::hasSection('brand'))
    <footer class="footer">
        <p>&copy; 2026 配合手順確認表</p>
    </footer>
    @endunless
</body>

</html>