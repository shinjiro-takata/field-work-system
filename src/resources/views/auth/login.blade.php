@extends('layouts.app')

@section('title', '現場業務システム')
@section('brand', '現場業務システム')

@section('content')
{{-- ログインフォーム（送信先 /login で認証される。@csrf = 送信の正当性を確認する合言葉） --}}
<form method="POST" action="/login">
    @csrf

    {{-- メールアドレス（ログインID。画面表示時に自動でカーソルが入る） --}}
    <div>
        <label for="email">メールアドレス</label>
        <input id="email" type="email" name="email" required value="{{ old('email') }}" autofocus>
        @error('email')
            <span>{{ $message }}</span>
        @enderror
    </div>
    {{-- パスワード --}}
    <div>
        <label for="password">パスワード</label>
        <input id="password" type="password" name="password" required>
        @error('password')
            <span>{{ $message }}</span>
        @enderror
    </div>
    <button type="submit">
        ログイン
    </button>
</form>
@endsection