<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', '配合手順確認表')</title>
    <link rel="stylesheet" href="{{ asset('css/sanitize.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @yield('css')
</head>

<body>
    <nav class="navbar">
        <div class="navbar__container">
            <div class="navbar__brand">
                @hasSection('brand')
                <a href="@yield('brand-link', route('portal'))" class="navbar__logo">@yield('brand')</a>
                @else
                <a href="{{ route('index') }}" class="navbar__logo">配合手順確認書</a>
                @endif
            </div>
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

    <main class="main-content">
        <div class="container">
            @if(session('status'))
            <div class="alert alert-success">
                {{ session('status') }}
            </div>
            @endif

            @if($errors->any())
            <div class="alert alert-danger">
                <ul>
                    @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            @yield('content')
        </div>
    </main>

    @unless(View::hasSection('brand'))
    <footer class="footer">
        <p>&copy; 2026 配合手順確認表</p>
    </footer>
    @endunless
</body>

</html>