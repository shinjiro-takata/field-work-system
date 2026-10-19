<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f7f8">
    <title>@yield('title', '設備点検') | Fieldnote</title>
    {{-- 共通スタイルと画面の動作は、専用のアセットファイルから読み込む。 --}}
    <link rel="stylesheet" href="{{ asset('css/equipment-check.css') }}?v={{ filemtime(public_path('css/equipment-check.css')) }}">
    <script src="{{ asset('js/equipment-check.js') }}?v={{ filemtime(public_path('js/equipment-check.js')) }}" defer></script>
</head>
<body>
{{-- アプリケーション共通のヘッダー。 --}}
<header class="topbar">
    <a class="brand" href="{{ route('home') }}">設備点検</a>
    {{-- 配合手順確認表への移動、ログインユーザー名、ログアウト。 --}}
    <nav class="topbar-nav">
        <a class="topbar-home" href="{{ route('portal') }}">ホーム</a>
        <span class="topbar-user">{{ Auth::user()->name }}</span>
        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button class="topbar-logout" type="submit">ログアウト</button>
        </form>
    </nav>
</header>
{{-- 点検入力、履歴、記録詳細の各画面をここに表示する。 --}}
<main>
    @if (session('status'))<div class="flash" role="status">{{ session('status') }}</div>@endif
    @yield('content')
</main>
</body>
</html>
